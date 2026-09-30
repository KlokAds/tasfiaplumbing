<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a fresh copy of the site work with only the database details filled in .env:
 *  1. creates APP_KEY when it is empty,
 *  2. shows a clear page (not a crash) when the database cannot be reached,
 *  3. runs new database migrations automatically (once, under a lock),
 *  4. uses the address the site was opened with when no production URL is set,
 *  5. sends the first visitor to /setup to create the owner account.
 * The result is remembered in storage/framework/setup-state.json, so a normal request
 * only compares a short signature and never touches the database here.
 */
class AutoSetup
{
    private const STATE = 'framework/setup-state.json';

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $this->ensureAppKey();

        $state = $this->state();
        $signature = $this->migrationSignature();

        if (($state['migrations'] ?? null) !== $signature) {
            try {
                DB::connection()->getPdo();
            } catch (\Throwable $e) {
                return response()->view('errors.setup', ['error' => $this->safeMessage($e->getMessage())], 503);
            }

            if (config('deploy.auto_migrate', true)) {
                $ok = $this->migrate();
                if ($ok !== true) {
                    return response()->view('errors.setup', ['error' => $ok, 'migrate' => true], 503);
                }
            }
            $state['migrations'] = $signature;
            $this->saveState($state);
        }

        // No production URL saved in admin: build links from the address actually used.
        if (!$request->is('up') && blank(rescue(fn () => \App\Models\SiteSetting::get('system.app_url'), null, false))) {
            config(['app.url' => $request->getSchemeAndHttpHost()]);
        }

        // First run: nobody can sign in yet, so create the owner account.
        if (empty($state['owner'])) {
            $hasUser = rescue(fn () => DB::table('users')->exists(), true, false);
            if ($hasUser) {
                $state['owner'] = true;
                $this->saveState($state);
            } elseif (!$request->is('setup', 'setup/*', 'build/*', 'up')) {
                return redirect('/setup');
            }
        }

        return $next($request);
    }

    private function ensureAppKey(): void
    {
        if (filled(config('app.key'))) {
            return;
        }
        $key = 'base64:' . base64_encode(random_bytes(32));
        $env = base_path('.env');
        if (is_file($env) && is_writable($env)) {
            $content = file_get_contents($env);
            $content = preg_match('/^APP_KEY=.*$/m', $content)
                ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content)
                : rtrim($content) . "\nAPP_KEY={$key}\n";
            file_put_contents($env, $content);
        }
        config(['app.key' => $key]);
        app()->forgetInstance('encrypter');
    }

    /** true, or a message explaining why the update failed. */
    private function migrate(): true|string
    {
        $lockFile = storage_path('framework/migrate.lock');
        $lock = fopen($lockFile, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            return 'Another database update is running. Refresh in a minute.';
        }
        try {
            Artisan::call('migrate', ['--force' => true]);
            Log::info('Automatic database update', ['output' => trim(Artisan::output())]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Automatic database update failed', ['error' => $e->getMessage()]);

            return $this->safeMessage($e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Changes whenever a migration file is added, so updates run exactly once after a deploy. */
    private function migrationSignature(): string
    {
        $files = glob(database_path('migrations/*.php')) ?: [];

        return md5(implode('|', array_map('basename', $files)));
    }

    /** State is kept per database, so pointing .env at a new database sets that one up too. */
    private function dbKey(): string
    {
        $c = config('database.connections.' . config('database.default'), []);

        return md5(config('database.default') . '|' . ($c['host'] ?? '') . '|' . ($c['port'] ?? '') . '|' . ($c['database'] ?? ''));
    }

    private function state(): array
    {
        $file = storage_path(self::STATE);
        $all = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];

        return $all[$this->dbKey()] ?? [];
    }

    private function saveState(array $state): void
    {
        $file = storage_path(self::STATE);
        $all = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $all[$this->dbKey()] = $state;
        @file_put_contents($file, json_encode($all));
    }

    /** Never show the database password, even if a driver puts it in the message. */
    private function safeMessage(string $message): string
    {
        $password = (string) config('database.connections.' . config('database.default') . '.password');

        return mb_substr($password !== '' ? str_replace($password, '••••', $message) : $message, 0, 400);
    }
}
