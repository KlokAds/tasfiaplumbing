<?php

namespace App\Support;

use App\Http\Controllers\Api\ManageApiController;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;

/**
 * Updating this website's code from GitHub, asked for from the company's billing app. PHP on this host may
 * not start programs, so the update itself is done by the server's update job (a cron job that runs
 * bin/site-update.sh every minute): this class leaves the request in storage/app/code-update, and the job
 * pulls with fast-forward only (a changed file on the server stops it, nothing is overwritten), rebuilds the
 * caches and writes what happened back there.
 */
class CodeUpdate
{
    public static function dir(): string
    {
        return config('deploy.code_update_dir') ?: storage_path('app/code-update');
    }

    /** False when an update is already waiting or running. */
    public static function request(string $by): bool
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_file("{$dir}/request.json") || is_file("{$dir}/running.json")) {
            return false;
        }

        return (bool) file_put_contents("{$dir}/request.json", json_encode(['at' => now()->toIso8601String(), 'by' => mb_substr($by, 0, 120)]));
    }

    /** The version running here, a waiting or running update, the last one, and whether the update job runs. */
    public static function state(): array
    {
        $dir = self::dir();
        $read = fn ($f) => is_file("{$dir}/{$f}") ? (json_decode((string) @file_get_contents("{$dir}/{$f}"), true) ?: []) : null;
        $seen = @filemtime("{$dir}/runner-seen");

        return [
            'version' => ManageApiController::version(),
            'waiting' => $read('request.json'),
            'running' => $read('running.json'),
            'last' => $read('result.json'),
            'job_seen_at' => $seen ? date(DATE_ATOM, $seen) : null,
        ];
    }

    /** What GitHub has that is not running here yet (newest first), read through GitHub's API. */
    public static function incoming(): array
    {
        $version = ManageApiController::version();
        $repo = self::repo();
        if (!$version || !$repo) {
            return ['ok' => false, 'message' => 'This website is not linked to a GitHub repository.'];
        }
        $branch = SiteSetting::get('deploy.branch') ?: config('deploy.branch');
        $http = Http::acceptJson()->timeout(15)->withHeaders(['User-Agent' => 'tasfia-site']);
        if ($token = ApiKeys::value('github')) {
            $http = $http->withToken($token);
        }
        $r = $http->get("https://api.github.com/repos/{$repo}/compare/{$version['hash']}..." . rawurlencode($branch));
        if (!$r->successful()) {
            return ['ok' => false, 'message' => $r->status() === 403 ? 'GitHub is busy for this server. Try again in a few minutes, or save a GitHub access token.' : "GitHub did not answer ({$r->status()})."];
        }

        return [
            'ok' => true,
            'repo' => $repo,
            'branch' => $branch,
            'behind' => (int) $r->json('ahead_by'),
            'ahead' => (int) $r->json('behind_by'),
            'commits' => collect($r->json('commits') ?? [])->reverse()->take(20)->map(fn ($c) => [
                'commit' => substr((string) $c['sha'], 0, 7),
                'title' => mb_substr(strtok((string) data_get($c, 'commit.message'), "\n") ?: '', 0, 160),
                'at' => data_get($c, 'commit.committer.date'),
            ])->values(),
        ];
    }

    /** "Owner/Name" of the GitHub repository: the saved address, else the folder's own "origin". */
    private static function repo(): ?string
    {
        $url = SiteSetting::get('deploy.repo_url');
        if (!$url && preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', (string) @file_get_contents(base_path('.git/config')), $m)) {
            $url = $m[1];
        }

        return $url && preg_match('#github\.com[/:]([\w.-]+/[\w.-]+?)(\.git)?/?$#', $url, $m) ? $m[1] : null;
    }
}
