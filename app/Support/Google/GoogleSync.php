<?php

namespace App\Support\Google;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Background work with Google, run by the server cron (google:sync every few minutes).
 * Each task has its own interval, chosen in Admin → Insights → Google → Automatic updates:
 *  - reports: fetch Search Console + Analytics ahead of time, so the reports page opens instantly;
 *  - index:   check which pages Google has indexed (oldest checks first);
 *  - reviews: import Google Business Profile reviews.
 * Intervals are in hours; 0 switches a task off.
 */
class GoogleSync
{
    public const TASKS = [
        'reports' => [
            'label' => 'Search & visitor reports',
            'help' => 'Loads the numbers from Search Console and Analytics ahead of time, so the reports page opens at once.',
            'default' => 6,
            'options' => [1, 3, 6, 12, 24],
        ],
        'index' => [
            'label' => 'Index status check',
            'help' => 'Asks Google which pages are indexed. Pages checked longest ago go first.',
            'default' => 24,
            'options' => [0, 6, 12, 24, 72, 168],
        ],
        'reviews' => [
            'label' => 'Google reviews import',
            'help' => 'Brings new Google reviews into Admin → Reviews.',
            'default' => 24,
            'options' => [0, 6, 12, 24, 168],
        ],
    ];

    /** How many pages one index run checks (Google allows about 2,000 a day per site). */
    public const INDEX_BATCH_OPTIONS = [25, 50, 100, 150, 300];

    public static function every(string $task): int
    {
        $value = SiteSetting::get("google.sync.{$task}_every");

        return $value === null ? self::TASKS[$task]['default'] : max(0, (int) $value);
    }

    public static function indexBatch(): int
    {
        return (int) SiteSetting::get('google.sync.index_batch', 150);
    }

    public static function last(string $task): ?Carbon
    {
        $t = SiteSetting::get("google.sync.{$task}_last");

        return $t ? Carbon::parse($t) : null;
    }

    public static function next(string $task): ?Carbon
    {
        $every = self::every($task);
        if (!$every || !self::available($task)) {
            return null;
        }
        $last = self::last($task);

        return $last ? $last->copy()->addHours($every) : now();
    }

    /** The task has what it needs (Google connected and the matching property chosen). */
    public static function available(string $task): bool
    {
        if (!GoogleApi::connected()) {
            return false;
        }

        return match ($task) {
            'reports' => (bool) (SearchConsole::property() || Analytics::property()),
            'index' => (bool) SearchConsole::property(),
            'reviews' => (bool) BusinessProfile::selected(),
            default => false,
        };
    }

    public static function due(string $task): bool
    {
        $next = self::next($task);

        return $next !== null && $next->lte(now());
    }

    /** How long a fetched report stays fresh: until the next scheduled refresh, plus a margin. */
    public static function reportHours(): int
    {
        return max(3, self::every('reports') + 1);
    }

    /**
     * Runs one task now and records the result. $seconds limits a run started from the admin,
     * so the page answers well inside the web server's time limit.
     */
    public static function run(string $task, ?int $seconds = null): array
    {
        $started = microtime(true);
        try {
            $summary = match ($task) {
                'reports' => self::runReports(),
                'index' => SearchConsole::inspectBatch(self::indexBatch(), $seconds) . ' pages checked',
                'reviews' => BusinessProfile::sync() . ' reviews imported',
            };
            $ok = true;
        } catch (\Throwable $e) {
            $summary = mb_substr($e->getMessage(), 0, 250);
            $ok = false;
            Log::warning('Google sync failed', ['task' => $task, 'error' => $summary]);
        }

        SiteSetting::putMany([
            "google.sync.{$task}_last" => now()->toIso8601String(),
            "google.sync.{$task}_result" => $summary,
            "google.sync.{$task}_ok" => $ok ? '1' : '0',
        ]);

        return ['ok' => $ok, 'summary' => $summary, 'seconds' => round(microtime(true) - $started, 1)];
    }

    /** Every task whose time has come (called by the cron). */
    public static function runDue(): array
    {
        $done = [];
        foreach (array_keys(self::TASKS) as $task) {
            if (self::due($task)) {
                $done[$task] = self::run($task);
            }
        }

        return $done;
    }

    /** For the admin: settings, last and next run of every task, and whether the cron runs. */
    public static function status(): array
    {
        $tasks = [];
        foreach (self::TASKS as $key => $t) {
            $last = self::last($key);
            $next = self::next($key);
            $tasks[] = [
                'key' => $key,
                'label' => $t['label'],
                'help' => $t['help'],
                'every' => self::every($key),
                'options' => $t['options'],
                'available' => self::available($key),
                'last' => $last?->toIso8601String(),
                'next' => $next?->toIso8601String(),
                'result' => SiteSetting::get("google.sync.{$key}_result"),
                'ok' => SiteSetting::get("google.sync.{$key}_ok", '1') === '1',
            ];
        }
        $seen = (int) Cache::get('system.scheduler_seen', 0);

        return [
            'tasks' => $tasks,
            'index_batch' => self::indexBatch(),
            'index_batch_options' => self::INDEX_BATCH_OPTIONS,
            'cron_ok' => $seen && $seen > time() - 600,
            'cron_seen' => $seen ? date('c', $seen) : null,
        ];
    }

    private static function runReports(): string
    {
        $parts = [];
        if (SearchConsole::property()) {
            SearchConsole::report(true);
            $parts[] = 'Search Console';
        }
        if (Analytics::property()) {
            Analytics::report(true);
            $parts[] = 'Analytics';
        }
        Cache::forget('google.dashboard');

        return $parts ? implode(' and ', $parts) . ' updated' : 'Nothing to update';
    }
}
