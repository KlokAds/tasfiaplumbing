<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeployLog;
use App\Models\SiteSetting;
use App\Support\SeoAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * One-click "pull from GitHub and apply". Only fixed commands from config/deploy.php run;
 * nothing from the request is ever passed to a shell.
 */
class SystemUpdateController extends Controller
{
    private const LOCK = 'system-update';

    public function index()
    {
        return Inertia::render('Admin/System/Update', [
            'status' => $this->status(),
            'steps' => array_map(fn ($s) => $s['label'], $this->steps($this->isConnected() ? 'update' : 'connect')),
            'logs' => DeployLog::with('user:id,name')->latest('id')->take(20)->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'action' => $l->action,
                    'status' => $l->status,
                    'user' => $l->user?->name,
                    'commit_before' => $l->commit_before,
                    'commit_after' => $l->commit_after,
                    'created_at' => $l->created_at?->toIso8601String(),
                    'finished_at' => $l->finished_at?->toIso8601String(),
                ]),
            'environment' => app()->environment(),
            'config' => [
                'remote' => config('deploy.remote'),
                'branch' => $this->branch(),
                'build_assets' => config('deploy.build_assets'),
                'run_composer' => config('deploy.run_composer'),
            ],
            'github' => [
                'repo_url' => $this->repoUrl(),
                'branch' => $this->branch(),
                'has_token' => (bool) $this->token(),
                'username' => SiteSetting::get('deploy.username'),
                'can_run' => function_exists('proc_open'),
            ],
        ]);
    }

    /** Repository URL, branch and (for private repos) an access token, saved from admin. */
    public function saveGithub(Request $request)
    {
        $short = trim((string) $request->input('repo_url'));
        if (preg_match('#^[\w.-]+/[\w.-]+$#', $short)) {
            $request->merge(['repo_url' => 'https://github.com/' . $short]);
        }
        $data = $request->validate([
            'repo_url' => ['required', 'string', 'max:300', 'regex:#^https://(github\.com|gitlab\.com|bitbucket\.org)/[\w.-]+/[\w.-]+?(\.git)?/?$#'],
            'username' => 'nullable|string|max:100',
            'branch' => ['required', 'string', 'max:100', 'regex:#^[\w./-]+$#'],
        ], ['repo_url.regex' => 'Use User/Repo (e.g. KlokAds/tasfiaplumbing) or the full https address.']);

        $values = [
            'deploy.repo_url' => rtrim(preg_replace('#\.git$#', '', rtrim($data['repo_url'], '/')), '/') . '.git',
            'deploy.branch' => $data['branch'],
            'deploy.username' => trim((string) ($data['username'] ?? '')),
        ];
        // The access token is saved in System → Settings → API keys.
        SiteSetting::putMany($values);

        // Keep the local "origin" in step with the saved address (the token is never written to .git/config).
        if ($this->isRepo()) {
            $this->git(['remote', 'get-url', config('deploy.remote')])->successful()
                ? $this->git(['remote', 'set-url', config('deploy.remote'), $values['deploy.repo_url']])
                : $this->git(['remote', 'add', config('deploy.remote'), $values['deploy.repo_url']]);
        }

        return back()->with('success', 'GitHub details saved.');
    }

    public function check(): JsonResponse
    {
        if (!$this->isConnected()) {
            return response()->json(['ok' => false, 'message' => 'This site is not connected to GitHub yet.'], 422);
        }

        $remote = config('deploy.remote');
        $branch = $this->branch();
        $fetch = $this->git(['fetch', $remote, $branch]);
        if (!$fetch->successful()) {
            return response()->json(['ok' => false, 'message' => trim($fetch->errorOutput()) ?: 'git fetch failed.'], 422);
        }

        $range = "HEAD..{$remote}/{$branch}";
        $behind = (int) trim($this->git(['rev-list', '--count', $range])->output());
        $ahead = (int) trim($this->git(['rev-list', '--count', "{$remote}/{$branch}..HEAD"])->output());
        $incoming = array_values(array_filter(explode("\n", trim($this->git(['log', '--pretty=format:%h|%an|%ar|%s', '-30', $range])->output()))));

        return response()->json([
            'ok' => true,
            'behind' => $behind,
            'ahead' => $ahead,
            'incoming' => array_map(function ($line) {
                [$hash, $author, $when, $subject] = array_pad(explode('|', $line, 4), 4, '');
                return compact('hash', 'author', 'when', 'subject');
            }, $incoming),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function deploy(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        if (!Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password is incorrect.']);
        }
        $action = in_array($request->input('action'), ['connect', 'migrate'], true) ? $request->input('action') : 'update';
        if ($action === 'update' && !$this->isConnected()) {
            return response()->json(['message' => 'This folder is not connected to GitHub yet. Use "Connect to GitHub" first.'], 422);
        }
        if ($action === 'connect' && (!$this->repoUrl() || $this->isConnected())) {
            return response()->json(['message' => $this->isConnected() ? 'Already connected. Use "Update now".' : 'Save the repository address first.'], 422);
        }
        if (!function_exists('proc_open')) {
            return response()->json(['message' => 'The server does not allow PHP to run programs (proc_open is disabled). Ask the hosting to enable it, or run the update over SSH.'], 422);
        }

        $lock = Cache::lock(self::LOCK, config('deploy.step_timeout') * 6);
        if (!$lock->get()) {
            return response()->json(['message' => 'Another update is already running.'], 409);
        }

        $log = DeployLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'status' => 'running',
            'commit_before' => $this->commit(),
            'output' => '',
        ]);

        ignore_user_abort(true);
        set_time_limit(0);

        try {
            $ok = true;
            foreach ($this->steps($action) as $step) {
                $log->appendOutput("\n$ {$step['display']}\n");
                $result = Process::path(base_path())
                    ->timeout(config('deploy.step_timeout'))
                    ->env($this->processEnv())
                    ->run($step['command']);
                $log->appendOutput(trim($result->output() . "\n" . $result->errorOutput()) . "\n");
                if (!$result->successful()) {
                    $log->appendOutput("\n✖ Step failed (exit code {$result->exitCode()}). Update stopped; later steps were not run.\n");
                    $ok = false;
                    break;
                }
            }
            $log->update([
                'status' => $ok ? 'success' : 'failed',
                'commit_after' => $this->commit(),
                'finished_at' => now(),
            ]);
            if ($ok) {
                SeoAudit::flush();
            }
        } catch (\Throwable $e) {
            $log->appendOutput("\n✖ " . $e->getMessage() . "\n");
            $log->update(['status' => 'failed', 'finished_at' => now()]);
        } finally {
            $lock->release();
        }

        return response()->json(['id' => $log->id, 'status' => $log->status]);
    }

    public function log(DeployLog $log): JsonResponse
    {
        return response()->json([
            'id' => $log->id,
            'status' => $log->status,
            'output' => $log->output,
            'commit_before' => $log->commit_before,
            'commit_after' => $log->commit_after,
            'finished_at' => $log->finished_at?->toIso8601String(),
        ]);
    }

    public function latest(): JsonResponse
    {
        $log = DeployLog::latest('id')->first();

        return $log ? $this->log($log) : response()->json(null);
    }

    private function steps(string $action = 'update'): array
    {
        $remote = config('deploy.remote');
        $branch = $this->branch();
        $git = config('deploy.git_binary');
        $php = $this->phpBinary();
        $production = app()->environment('production');

        if ($action === 'migrate') {
            return array_map(fn ($s) => $s + ['display' => implode(' ', array_map(fn ($p) => $p === $php ? 'php' : $p, $s['command']))], [
                ['label' => 'Update database (migrate)', 'command' => [$php, 'artisan', 'migrate', '--force']],
                ['label' => 'Clear caches', 'command' => [$php, 'artisan', 'optimize:clear']],
            ]);
        }

        $steps = $action === 'connect'
            ? [
                // First connection: turn this folder into a copy of the repository. Files git ignores
                // (.env, uploads, vendor, the database) are left exactly as they are.
                ['label' => 'Start git in the site folder', 'command' => [$git, 'init']],
                ['label' => "Link to {$this->repoUrl()}", 'command' => $this->isRepo() && $this->git(['remote', 'get-url', $remote])->successful()
                    ? [$git, 'remote', 'set-url', $remote, $this->repoUrl() ?: '-']
                    : [$git, 'remote', 'add', $remote, $this->repoUrl() ?: '-']],
                ['label' => "Download {$branch} from GitHub", 'command' => [$git, 'fetch', $remote, $branch]],
                ['label' => 'Replace the code with the GitHub version', 'command' => [$git, 'reset', '--hard', "{$remote}/{$branch}"]],
                ['label' => "Track {$branch}", 'command' => [$git, 'checkout', '-B', $branch, '--track', "{$remote}/{$branch}"]],
            ]
            : [
                ['label' => "Download latest code (git pull {$remote} {$branch})", 'command' => [$git, 'pull', '--ff-only', $remote, $branch]],
            ];
        if (config('deploy.run_composer')) {
            $args = [...$this->composerCommand($php), 'install', '--no-interaction', '--no-progress', '--optimize-autoloader'];
            if ($production) {
                $args[] = '--no-dev';
            }
            $steps[] = ['label' => 'Install PHP packages (composer install)', 'command' => $args];
        }
        $steps[] = ['label' => 'Update database (migrate)', 'command' => [$php, 'artisan', 'migrate', '--force']];
        if (config('deploy.build_assets')) {
            $npm = config('deploy.npm_binary');
            $steps[] = ['label' => 'Install JS packages (npm ci)', 'command' => [$npm, 'ci', '--no-audit', '--no-fund']];
            $steps[] = ['label' => 'Build frontend assets (npm run build)', 'command' => [$npm, 'run', 'build']];
        }
        $steps[] = ['label' => 'Clear caches', 'command' => [$php, 'artisan', 'optimize:clear']];
        if ($production) {
            $steps[] = ['label' => 'Rebuild caches (config, routes, views)', 'command' => [$php, 'artisan', 'optimize']];
        }

        return array_map(fn ($s) => $s + ['display' => implode(' ', array_map(
            fn ($p) => $p === $php ? 'php' : $p,
            $s['command']
        ))], $steps);
    }

    private function status(): array
    {
        $gitVersion = $this->git(['--version']);
        if (!$gitVersion->successful()) {
            return ['git' => false, 'repo' => false, 'message' => 'git is not installed or not on PATH for the web server user.'];
        }
        if (!$this->isConnected()) {
            return ['git' => true, 'repo' => false, 'message' => 'This site is not connected to GitHub yet. Fill in the repository below and click "Connect to GitHub".'];
        }

        $remoteUrl = trim($this->git(['remote', 'get-url', config('deploy.remote')])->output());
        $dirty = array_values(array_filter(explode("\n", trim($this->git(['status', '--porcelain', '--untracked-files=no'])->output()))));

        return [
            'git' => true,
            'repo' => true,
            'branch' => trim($this->git(['rev-parse', '--abbrev-ref', 'HEAD'])->output()),
            'commit' => $this->commit(),
            'commit_subject' => trim($this->git(['log', '-1', '--pretty=%s'])->output()),
            'commit_date' => trim($this->git(['log', '-1', '--pretty=%cI'])->output()),
            'remote_url' => preg_replace('#//[^@/]+@#', '//***@', $remoteUrl),
            'modified_files' => array_slice($dirty, 0, 20),
            'modified_count' => count($dirty),
        ];
    }

    /** A repository with at least one commit checked out (a failed first connect is not "connected"). */
    private function isConnected(): bool
    {
        return $this->isRepo() && $this->git(['rev-parse', '--verify', 'HEAD'])->successful();
    }

    private function isRepo(): bool
    {
        // The site folder itself must be the repository root (a repo in a parent folder does not count).
        $top = trim($this->git(['rev-parse', '--show-toplevel'])->output());

        return $top !== '' && realpath($top) === realpath(base_path());
    }

    private function commit(): ?string
    {
        $hash = trim($this->git(['rev-parse', '--short', 'HEAD'])->output());

        return $hash !== '' ? $hash : null;
    }

    private function git(array $args)
    {
        try {
            return Process::path(base_path())->timeout(120)->env($this->processEnv())->run([config('deploy.git_binary'), ...$args]);
        } catch (\Throwable $e) {
            return new FakeProcessResult(exitCode: 1, errorOutput: $e->getMessage());
        }
    }

    private function phpBinary(): string
    {
        $configured = config('deploy.php_binary');
        if ($configured) {
            return $configured;
        }
        $name = strtolower(basename(PHP_BINARY));
        if (!str_contains($name, 'fpm') && !str_contains($name, 'cgi') && !str_contains($name, 'lsphp')) {
            return PHP_BINARY;
        }
        // The web server runs PHP through FPM / CGI / LiteSpeed (lsphp): use the command-line PHP
        // of the same version next to it (e.g. /opt/alt/php85/usr/bin/php on Hostinger).
        $cli = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php';

        return is_file($cli) && is_executable($cli) ? $cli : 'php';
    }

    /**
     * Composer as a command. When it is a PHP script (composer.phar, or Hostinger's
     * /usr/local/bin/composer), run it with the site's PHP, because the default "php" on
     * shared hosting is often older than the packages need.
     */
    private function composerCommand(string $php): array
    {
        $composer = (string) config('deploy.composer_binary');
        $path = str_contains($composer, '/') || str_contains($composer, '\\')
            ? $composer
            : (new \Symfony\Component\Process\ExecutableFinder())->find($composer);
        if ($path && is_file($path)) {
            $head = (string) @file_get_contents($path, false, null, 0, 64);
            if (str_ends_with(strtolower($path), '.phar') || str_starts_with($head, '<?php') || preg_match('/^#!.*\bphp\b/', $head)) {
                return [$php, $path];
            }
        }

        return [$composer];
    }

    private function processEnv(): array
    {
        $env = [
            'COMPOSER_HOME' => getenv('COMPOSER_HOME') ?: storage_path('app/composer'),
            'GIT_TERMINAL_PROMPT' => '0',
        ];
        // Private repository: send the token as a header through git's environment config
        // (not visible in the process list and never saved in .git/config).
        if ($token = $this->token()) {
            $host = parse_url((string) $this->repoUrl(), PHP_URL_HOST) ?: 'github.com';
            $env += [
                'GIT_CONFIG_COUNT' => '1',
                'GIT_CONFIG_KEY_0' => "http.https://{$host}/.extraheader",
                'GIT_CONFIG_VALUE_0' => 'AUTHORIZATION: basic ' . base64_encode((SiteSetting::get('deploy.username') ?: 'x-access-token') . ':' . $token),
            ];
        }

        return $env;
    }

    private function repoUrl(): ?string
    {
        $saved = SiteSetting::get('deploy.repo_url');
        if ($saved) {
            return $saved;
        }
        $origin = $this->isRepo() ? trim($this->git(['remote', 'get-url', config('deploy.remote')])->output()) : '';

        return $origin !== '' ? preg_replace('#//[^@/]+@#', '//', $origin) : null;
    }

    private function branch(): string
    {
        return SiteSetting::get('deploy.branch') ?: config('deploy.branch');
    }

    private function token(): ?string
    {
        $stored = SiteSetting::get('deploy.token');
        if (!$stored) {
            return env('DEPLOY_TOKEN') ?: null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }
}
