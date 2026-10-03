<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Notifications\SiteMonitorAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * "Is the website up?" every 5 minutes (monitor:sites). The four sites watch each other in a ring
 * (each checks the next one's /up page, which also checks its database), and each checks its own
 * database. After two failed checks in a row (about 10 minutes) the hidden admin gets an email, and
 * another one when it works again, with how long it was down.
 *
 * Everything it needs (state, recipients) is kept in the file cache, so an alert can still be sent
 * when the database is the thing that is down.
 */
class SiteMonitor
{
    private const STATE = 'monitor.state';

    private const RECIPIENTS = 'monitor.recipients';

    private const FAILS_BEFORE_ALERT = 2;

    private static function cache()
    {
        return Cache::store('file');
    }

    /** The site this one watches: the next one in config admin.monitor_sites. */
    public static function watched(): ?string
    {
        $sites = array_values(config('admin.monitor_sites', []));
        $own = self::host((string) config('app.url'));
        foreach ($sites as $i => $url) {
            if (self::host($url) === $own) {
                return count($sites) > 1 ? $sites[($i + 1) % count($sites)] : null;
            }
        }

        return null;
    }

    /** @return array<string, string> check name => problem ('' when it works) */
    public static function checks(): array
    {
        $out = [];
        try {
            DB::select('select 1');
            $out['This website\'s database'] = '';
        } catch (\Throwable $e) {
            $out['This website\'s database'] = 'The database does not answer: ' . mb_substr($e->getMessage(), 0, 120);
        }

        if ($url = self::watched()) {
            try {
                $res = Http::timeout(20)->withUserAgent('Tasfia-site-monitor')->get(rtrim($url, '/') . '/up');
                $out[self::host($url)] = $res->successful() ? '' : 'The website answered with error ' . $res->status() . '.';
            } catch (\Throwable $e) {
                $out[self::host($url)] = 'The website does not answer (' . mb_substr($e->getMessage(), 0, 80) . ').';
            }
        }

        return $out;
    }

    /** One run: check, update the state, email on a change. Returns the checks with their problems. */
    public static function run(): array
    {
        $checks = self::checks();
        if (($checks['This website\'s database'] ?? '') === '') {
            self::rememberRecipients(); // only possible while the database works
        }

        $state = self::cache()->get(self::STATE, []);
        foreach ($checks as $name => $problem) {
            $s = $state[$name] ?? ['fails' => 0, 'down_since' => null, 'alerted' => false];
            if ($problem !== '') {
                $s['fails']++;
                $s['down_since'] ??= now()->toIso8601String();
                $s['problem'] = $problem;
                if ($s['fails'] >= self::FAILS_BEFORE_ALERT && !$s['alerted']) {
                    self::alert("Down: {$name}", [
                        "**{$name}** has not worked for about " . ($s['fails'] * 5) . ' minutes.',
                        'Problem: ' . $problem,
                        'You get another email as soon as it works again.',
                    ]);
                    $s['alerted'] = true;
                }
            } else {
                if ($s['alerted']) {
                    $minutes = max(1, (int) round(now()->diffInMinutes($s['down_since'] ?? now(), true)));
                    self::alert("Working again: {$name}", ["**{$name}** works again. It was down for about {$minutes} minutes."]);
                    self::log($name, $s['down_since'], now()->toIso8601String(), $s['problem'] ?? '');
                }
                $s = ['fails' => 0, 'down_since' => null, 'alerted' => false];
            }
            $state[$name] = $s;
        }
        self::cache()->forever(self::STATE, $state);
        self::cache()->forever('monitor.last_run', now()->toIso8601String());

        return $checks;
    }

    /** For System → Server check: the current state and the last incidents. */
    public static function status(): array
    {
        return [
            'watched' => ($w = self::watched()) ? self::host($w) : null,
            'last_run' => self::cache()->get('monitor.last_run'),
            'state' => self::cache()->get(self::STATE, []),
            'incidents' => array_slice(array_reverse(self::cache()->get('monitor.log', [])), 0, 10),
        ];
    }

    private static function log(string $name, ?string $from, string $to, string $problem): void
    {
        $log = self::cache()->get('monitor.log', []);
        $log[] = ['name' => $name, 'down' => $from, 'up' => $to, 'problem' => $problem];
        self::cache()->forever('monitor.log', array_slice($log, -50));
    }

    private static function rememberRecipients(): void
    {
        try {
            $emails = User::where('is_hidden', true)->where('is_active', true)->role(config('admin.super_role'))
                ->pluck('email')->filter()->values()->all();
            if ($emails) {
                self::cache()->forever(self::RECIPIENTS, $emails);
            }
        } catch (\Throwable) {
            // keep the list from the last run
        }
    }

    private static function alert(string $subject, array $lines): void
    {
        $to = self::cache()->get(self::RECIPIENTS, []);
        Log::warning('Site monitor: ' . $subject, ['lines' => $lines]);
        if (!$to) {
            return;
        }
        try {
            SystemSettings::applyMail();
            $lines[] = 'Checked from ' . self::host((string) config('app.url')) . ' at ' . now(config('admin.timezone'))->format('D j M, g:i A') . ' (' . config('admin.timezone_label') . ').';
            Notification::route('mail', $to)->notify(new SiteMonitorAlert($subject, $lines));
        } catch (\Throwable $e) {
            Log::error('Site monitor email failed', ['error' => $e->getMessage()]);
        }
    }

    private static function host(string $url): string
    {
        return strtolower(preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)));
    }
}
