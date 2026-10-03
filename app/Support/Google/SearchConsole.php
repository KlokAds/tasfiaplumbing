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

    /** Search Console data is about 2 days behind and kept for 16 months. */
    public static function range(?\Illuminate\Http\Request $request = null): ReportRange
    {
        return $request ? ReportRange::fromRequest($request, 2, 16) : ReportRange::make(ReportRange::DEFAULT, null, null, 2, 16);
    }

    /** The chosen period vs the period before it: totals, daily (or weekly) clicks, top queries, pages and devices. */
    public static function report(bool $fresh = false, ?ReportRange $range = null): array
    {
        $range ??= self::range();
        $key = 'google.gsc.report' . $range->cacheSuffix();
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addHours(GoogleSync::reportHours()), function () use ($range) {
            $site = self::property() ?: throw new GoogleException('Choose your Search Console property first.');
            $start = $range->start;
            $end = $range->end;
            $prevStart = $range->previousStart();
            $prevEnd = $range->previousEnd();

            $q = fn ($s, $e, array $dims = [], int $limit = 25) => GoogleApi::post(self::API . '/sites/' . rawurlencode($site) . '/searchAnalytics/query', array_filter([
                'startDate' => $s->toDateString(),
                'endDate' => $e->toDateString(),
                'dimensions' => $dims ?: null,
                'rowLimit' => $limit,
                'dataState' => $range->recent(2) ? 'all' : 'final', // today / yesterday: the fresh numbers
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

            $daily = $range->fillDays(array_map(fn ($r) => ['date' => $r['keys'][0], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions']], $q($start, $end, ['date'], 500)), ['clicks', 'impressions']);

            return [
                'range' => [$start->toDateString(), $end->toDateString()],
                'now' => $total($q($start, $end)),
                'before' => $total($q($prevStart, $prevEnd)),
                'daily' => $range->weekly() ? ReportRange::toWeeks($daily, ['clicks', 'impressions']) : $daily,
                'queries' => $rows($q($start, $end, ['query'], 100)),
                'pages' => array_map(fn ($r) => $r + ['path' => parse_url($r['key'], PHP_URL_PATH) ?: '/'], $rows($q($start, $end, ['page'], 100))),
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
