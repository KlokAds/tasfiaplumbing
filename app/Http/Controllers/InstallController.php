<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-time installer for a fresh upload: asks for the MySQL details, writes them to .env,
 * loads the bundled website data into an empty database and runs any newer migrations.
 * Once it has finished it writes a lock file and never opens again, even if the database
 * is down later (otherwise anyone could point the site at their own database).
 */
class InstallController extends Controller
{
    /** Bundled data, loaded only into an empty database and deleted after a successful install. */
    public const DATA = 'install/website-data.sql';
    public const LOCK = 'framework/installed.lock';

    public static function isLocked(): bool
    {
        return is_file(storage_path(self::LOCK));
    }

    /** The installer may run when it has never finished and the database is not usable yet. */
    public static function isOpen(): bool
    {
        if (self::isLocked()) {
            return false;
        }
        if (self::notConfigured()) {
            return true;
        }
        try {
            DB::connection()->getPdo();

            return false;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function notConfigured(): bool
    {
        $default = config('database.default');

        return in_array($default, ['mysql', 'mariadb'], true) && blank(config("database.connections.{$default}.database"));
    }

    public function show()
    {
        abort_unless(self::isOpen(), 404);

        return view('install', ['hasData' => is_file(database_path(self::DATA))]);
    }

    public function store(Request $request)
    {
        abort_unless(self::isOpen(), 404);
        @set_time_limit(600);

        $data = $request->validate([
            'host' => 'required|string|max:190',
            'port' => 'required|integer|between:1,65535',
            'database' => 'required|string|max:64',
            'username' => 'required|string|max:64',
            'password' => 'nullable|string|max:190',
        ]);
        $data['password'] = (string) ($data['password'] ?? '');

        // 1. Can we reach the database with these details?
        try {
            $pdo = new \PDO(
                "mysql:host={$data['host']};port={$data['port']};dbname={$data['database']};charset=utf8mb4",
                $data['username'],
                $data['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 10]
            );
        } catch (\Throwable $e) {
            return back()->withInput($request->except('password'))
                ->withErrors(['database' => 'Could not connect with these details. Check the database name, username and password in hPanel → Databases. (' . $this->safe($e->getMessage(), $data['password']) . ')']);
        }

        // 2. Save them in .env so the site uses them from now on.
        if (!$this->writeEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['host'],
            'DB_PORT' => (string) $data['port'],
            'DB_DATABASE' => $data['database'],
            'DB_USERNAME' => $data['username'],
            'DB_PASSWORD' => $data['password'],
        ])) {
            return back()->withInput($request->except('password'))
                ->withErrors(['database' => 'The .env file could not be saved. In File Manager, make sure .env exists and is writable (permission 644), then try again.']);
        }

        // 3. Load the website data into an empty database.
        $imported = false;
        $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $file = database_path(self::DATA);
        if ($tables === 0 && is_file($file)) {
            try {
                $this->import($pdo, $file);
                $imported = true;
            } catch (\Throwable $e) {
                Log::error('Installer import failed', ['error' => $e->getMessage()]);

                return back()->withInput($request->except('password'))
                    ->withErrors(['database' => 'The website data could not be loaded: ' . $this->safe($e->getMessage(), $data['password']) . ' Empty the database in phpMyAdmin and try again.']);
            }
        }

        // 4. Use the new connection now and bring the tables up to date.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $data['host'],
            'database.connections.mysql.port' => $data['port'],
            'database.connections.mysql.database' => $data['database'],
            'database.connections.mysql.username' => $data['username'],
            'database.connections.mysql.password' => $data['password'],
        ]);
        DB::purge('mysql');
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::error('Installer migrate failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['database' => 'The database tables could not be prepared: ' . $this->safe($e->getMessage(), $data['password'])]);
        }
        rescue(fn () => Artisan::call('config:clear'), null, false);

        // 5. Close the installer for good and remove the bundled data from the server.
        @file_put_contents(storage_path(self::LOCK), json_encode(['installed_at' => now()->toIso8601String(), 'imported' => $imported]));
        if ($imported) {
            @unlink($file);
        }
        $hasUsers = rescue(fn () => DB::table('users')->exists(), false, false);

        return view('install', ['done' => true, 'imported' => $imported, 'hasUsers' => $hasUsers, 'hasData' => false]);
    }

    /** Run a mysqldump file statement by statement (dumps end every statement with ";" at the end of a line). */
    private function import(\PDO $pdo, string $file): void
    {
        $pdo->exec('SET NAMES utf8mb4');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $handle = fopen($file, 'r');
        $sql = '';
        while (($line = fgets($handle)) !== false) {
            $trim = rtrim($line);
            if ($sql === '' && ($trim === '' || str_starts_with($trim, '--'))) {
                continue;
            }
            $sql .= $line;
            if (str_ends_with($trim, ';')) {
                $pdo->exec($sql);
                $sql = '';
            }
        }
        fclose($handle);
        if (trim($sql) !== '') {
            $pdo->exec($sql);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Replace (or add) KEY=value lines in .env, quoting values so any password works. */
    private function writeEnv(array $values): bool
    {
        $path = base_path('.env');
        if (!is_file($path) || !is_writable($path)) {
            return false;
        }
        $content = (string) file_get_contents($path);
        foreach ($values as $key => $value) {
            // Single quotes keep the value literal ($, #, spaces, backslashes); a value that itself
            // contains a single quote goes in double quotes with its special characters escaped.
            $quoted = match (true) {
                $value === '' => '',
                !str_contains($value, "'") => "'" . $value . "'",
                default => '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"',
            };
            $line = $key . '=' . $quoted;
            $content = preg_match("/^{$key}=.*$/m", $content)
                ? preg_replace_callback("/^{$key}=.*$/m", fn () => $line, $content, 1)
                : rtrim($content) . "\n{$line}\n";
        }

        return file_put_contents($path, $content) !== false;
    }

    private function safe(string $message, string $password): string
    {
        return mb_substr($password !== '' ? str_replace($password, '••••', $message) : $message, 0, 300);
    }
}
