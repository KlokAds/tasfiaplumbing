<?php

namespace App\Support\Google;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/** Google Analytics 4 (Data API): visitors, pages, traffic sources, devices and lead events. */
class Analytics
{
    /** @return array<int, array{id: string, name: string, account: string}> */
    public static function properties(): array
    {
        $out = [];
        $data = GoogleApi::get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', ['pageSize' => 200]);
        foreach ($data['accountSummaries'] ?? [] as $acc) {
            foreach ($acc['propertySummaries'] ?? [] as $p) {
                $out[] = ['id' => $p['property'], 'name' => $p['displayName'] ?? $p['property'], 'account' => $acc['displayName'] ?? ''];
            }
        }

        return $out;
    }

    public static function property(): ?string
    {
        return SiteSetting::get('google.ga4_property') ?: null;
    }

    /** Analytics is complete up to yesterday; reports here go back at most 26 months. */
    public static function range(?\Illuminate\Http\Request $request = null): ReportRange
    {
        return $request ? ReportRange::fromRequest($request, 1, 26) : ReportRange::make(ReportRange::DEFAULT, null, null, 1, 26);
    }

    /** The chosen period vs the period before it: visitors, pages, sources, devices, cities and lead events. */
    public static function report(bool $fresh = false, ?ReportRange $range = null): array
    {
        $range ??= self::range();
        $key = 'google.ga4.report' . $range->cacheSuffix();
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addHours(GoogleSync::reportHours()), function () use ($range) {
            $property = self::property() ?: throw new GoogleException('Choose your Analytics property first.');
            $run = fn (array $body) => GoogleApi::post("https://analyticsdata.googleapis.com/v1beta/{$property}:runReport", $body);
            $now = ['startDate' => $range->start->toDateString(), 'endDate' => $range->end->toDateString()];
            $before = ['startDate' => $range->previousStart()->toDateString(), 'endDate' => $range->previousEnd()->toDateString()];
            $metricNames = ['activeUsers', 'sessions', 'screenPageViews', 'engagementRate', 'averageSessionDuration'];

            $totals = $run(['dateRanges' => [$now, $before], 'metrics' => array_map(fn ($m) => ['name' => $m], $metricNames)]);
            $pick = function (int $range) use ($totals, $metricNames) {
                foreach ($totals['rows'] ?? [] as $row) {
                    // With 2 date ranges GA adds a "dateRange" dimension: date_range_0 / date_range_1.
                    if (($row['dimensionValues'][0]['value'] ?? 'date_range_0') === "date_range_{$range}") {
                        return array_combine($metricNames, array_map(fn ($v) => (float) $v['value'], $row['metricValues']));
                    }
                }

                return array_fill_keys($metricNames, 0);
            };

            $table = function (string $dimension, array $metrics, int $limit = 10, ?array $filter = null) use ($run, $now) {
                $res = $run(array_filter([
                    'dateRanges' => [$now],
                    'dimensions' => [['name' => $dimension]],
                    'metrics' => array_map(fn ($m) => ['name' => $m], $metrics),
                    'orderBys' => [['metric' => ['metricName' => $metrics[0]], 'desc' => true]],
                    'limit' => $limit,
                    'dimensionFilter' => $filter,
                ]));

                return array_map(fn ($r) => ['key' => $r['dimensionValues'][0]['value']] + array_combine($metrics, array_map(fn ($v) => (float) $v['value'], $r['metricValues'])), $res['rows'] ?? []);
            };

            $daily = $run([
                'dateRanges' => [$now],
                'dimensions' => [['name' => 'date']],
                'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
                'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
            ]);
            $days = array_map(fn ($r) => [
                'date' => preg_replace('/(\d{4})(\d{2})(\d{2})/', '$1-$2-$3', $r['dimensionValues'][0]['value']),
                'users' => (int) $r['metricValues'][0]['value'],
                'sessions' => (int) $r['metricValues'][1]['value'],
            ], $daily['rows'] ?? []);
            $days = $range->fillDays($days, ['users', 'sessions']);

            return [
                'now' => $pick(0),
                'before' => $pick(1),
                'daily' => $range->weekly() ? ReportRange::toWeeks($days, ['users', 'sessions']) : $days,
                'pages' => $table('pagePath', ['screenPageViews', 'activeUsers'], 100),
                'channels' => $table('sessionDefaultChannelGroup', ['sessions', 'activeUsers'], 10),
                'sources' => $table('sessionSource', ['sessions'], 50),
                'devices' => $table('deviceCategory', ['activeUsers'], 4),
                'cities' => $table('city', ['activeUsers'], 50),
                'events' => $table('eventName', ['eventCount'], 10, ['filter' => ['fieldName' => 'eventName', 'inListFilter' => ['values' => ['generate_lead', 'click_whatsapp', 'click_call', 'click_email']]]]),
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }
}
