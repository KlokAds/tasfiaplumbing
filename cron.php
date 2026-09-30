<?php

/*
 * Cron entry point for shared hosting.
 *
 * Hostinger's cron runs "/usr/bin/php", which is an older PHP than this site needs, so this
 * file (written for old PHP too) finds a PHP 8.3+ binary and starts the Laravel scheduler.
 * hPanel > Advanced > Cron Jobs, every minute:
 *     /usr/bin/php /home/<user>/domains/<domain>/<site folder>/cron.php
 * It lives outside public/, so it can never be opened from the web.
 */

if (PHP_SAPI !== 'cli') {
    exit(0);
}

$base = __DIR__;

// Nothing to do until the site has a database (the installer fills it in on the first visit).
$env = @file_get_contents($base . '/.env');
if ($env === false) {
    exit(0);
}
$connection = preg_match('/^DB_CONNECTION=["\']?([^"\'\r\n]*)/m', $env, $m) ? trim($m[1]) : '';
$database = preg_match('/^DB_DATABASE=["\']?([^"\'\r\n]*)/m', $env, $m) ? trim($m[1]) : '';
if ($connection !== 'sqlite' && $database === '') {
    exit(0);
}

$php = PHP_VERSION_ID >= 80300 ? PHP_BINARY : null;
if ($php === null && function_exists('shell_exec')) {
    foreach (array('/opt/alt/php85/usr/bin/php', '/opt/alt/php84/usr/bin/php', '/opt/alt/php83/usr/bin/php', '/usr/local/bin/php') as $candidate) {
        if (is_executable($candidate) && (int) shell_exec(escapeshellarg($candidate) . ' -r "echo PHP_VERSION_ID;"') >= 80300) {
            $php = $candidate;
            break;
        }
    }
}
if ($php === null) {
    fwrite(STDERR, "cron.php: no PHP 8.3 or newer found for the scheduler\n");
    exit(1);
}

// This PHP is new enough: run the scheduler right here (hosts often disable passthru/proc_open).
if ($php === PHP_BINARY) {
    chdir($base);
    $_SERVER['argv'] = $argv = array('artisan', 'schedule:run', '--no-interaction');
    $_SERVER['argc'] = $argc = 3;
    require $base . '/artisan';
    exit(0);
}

if (!function_exists('passthru')) {
    fwrite(STDERR, "cron.php: passthru is disabled; point the cron job at PHP 8.3+ directly\n");
    exit(1);
}
passthru(escapeshellarg($php) . ' ' . escapeshellarg($base . '/artisan') . ' schedule:run --no-interaction 2>&1', $code);
exit($code);
