<?php

namespace App\Support\Google;

use App\Models\IndexStatus;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/** Google Search Console: clicks, impressions, queries, pages, and index status per URL. */
class SearchConsole
{
    private const API = 'https://www.googleapis.com/webmasters/v3';

    /** @return array<int, array{url: string, permission: string}> */
    public static function sites(): array
    {
        return collect(GoogleApi::get(self::API . '/sites')['siteEntry'] ?? [])
            ->reject(fn ($s) => ($s['permissionLevel'] ?? '') === 'siteUnverifiedUser')
            ->map(fn ($s) => ['url' => $s['siteUrl'], 'permission' => $s['permissionLevel'] ?? ''])
            ->values()->all();
    }

    public static function property(): ?string
    {
        return SiteSetting::get('google.gsc_property') ?: null;
    }

    /** 28 days vs the 28 before, daily clicks, top queries and pages. Cached 6 hours. */
    public static function report(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('google.gsc.report');
        }

        return Cache::remember('google.gsc.report', now()->addHours(6), function () {
            $site = self::property() ?: throw new GoogleException('Choose your Search Console property first.');
            // Search Console data is 2–3 days behind.
            $end = now()->subDays(2)->startOfDay();
            $start = $end->copy()->subDays(27);
            $prevEnd = $start->copy()->subDay();
            $prevStart = $prevEnd->copy()->subDays(27);

            $q = fn ($s, $e, array $dims = [], int $limit = 25) => GoogleApi::post(self::API . '/sites/' . rawurlencode($site) . '/searchAnalytics/query', array_filter([
                'startDate' => $s->toDateString(),
                'endDate' => $e->toDateString(),
                'dimensions' => $dims ?: null,
                'rowLimit' => $limit,
                'dataState' => 'final',
            ]))['rows'] ?? [];

            $total = fn ($rows) => [
                'clicks' => (int) ($rows[0]['clicks'] ?? 0),
                'impressions' => (int) ($rows[0]['impressions'] ?? 0),
                'ctr' => round(($rows[0]['ctr'] ?? 0) * 100, 1),
                'position' => round($rows[0]['position'] ?? 0, 1),
            ];
            $rows = fn ($list) => array_map(fn ($r) => [
                'key' => $r['keys'][0],
                'clicks' => (int) $r['clicks'],
                'impressions' => (int) $r['impressions'],
                'ctr' => round($r['ctr'] * 100, 1),
                'position' => round($r['position'], 1),
            ], $list);

            return [
                'range' => [$start->toDateString(), $end->toDateString()],
                'now' => $total($q($start, $end)),
                'before' => $total($q($prevStart, $prevEnd)),
                'daily' => array_map(fn ($r) => ['date' => $r['keys'][0], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions']], $q($start, $end, ['date'], 60)),
                'queries' => $rows($q($start, $end, ['query'], 25)),
                'pages' => array_map(fn ($r) => $r + ['path' => parse_url($r['key'], PHP_URL_PATH) ?: '/'], $rows($q($start, $end, ['page'], 25))),
                'devices' => $rows($q($start, $end, ['device'], 5)),
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /** Ask Google whether one URL is indexed and store the answer. */
    public static function inspect(string $url): IndexStatus
    {
        $site = self::property() ?: throw new GoogleException('Choose your Search Console property first.');
        $status = IndexStatus::firstOrNew(['url' => $url]);

        try {
            $r = GoogleApi::post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $url,
                'siteUrl' => $site,
            ])['inspectionResult']['indexStatusResult'] ?? [];
            $status->fill([
                'verdict' => $r['verdict'] ?? null,
                'coverage' => $r['coverageState'] ?? null,
                'robots' => $r['robotsTxtState'] ?? null,
                'fetch' => $r['pageFetchState'] ?? null,
                'google_canonical' => $r['googleCanonical'] ?? null,
                'last_crawl' => $r['lastCrawlTime'] ?? null,
                'error' => null,
            ]);
        } catch (GoogleException $e) {
            if ($e->getCode() === 429) {
                throw $e; // daily quota used up: stop the batch
            }
            $status->error = mb_substr($e->getMessage(), 0, 290);
        }
        $status->checked_at = now();
        $status->save();

        return $status;
    }

    /**
     * Daily batch: new URLs first, then the ones checked longest ago.
     * Google allows 2,000 inspections a day; 150 keeps well inside that.
     */
    /**
     * Checks the pages checked longest ago. $seconds stops early (a button click must finish well
     * inside the web server's time limit; the daily job runs without one).
     */
    public static function inspectBatch(int $limit = 150, ?int $seconds = null): int
    {
        $urls = \App\Support\Sitemap::urls()->map(fn ($u) => url($u['loc']))->all();
        $known = IndexStatus::whereIn('url', $urls)->pluck('checked_at', 'url');
        $queue = collect($urls)->sortBy(fn ($u) => $known->get($u)?->timestamp ?? 0)->take($limit);
        $stopAt = $seconds ? microtime(true) + $seconds : null;

        $done = 0;
        foreach ($queue as $url) {
            if ($stopAt && microtime(true) >= $stopAt) {
                break;
            }
            try {
                self::inspect($url);
                $done++;
            } catch (GoogleException $e) {
                break;
            }
        }
        IndexStatus::whereNotIn('url', $urls ?: ['-'])->delete(); // pages removed from the site

        return $done;
    }
}
