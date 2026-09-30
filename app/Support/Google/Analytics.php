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

    public static function report(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('google.ga4.report');
        }

        return Cache::remember('google.ga4.report', now()->addHours(3), function () {
            $property = self::property() ?: throw new GoogleException('Choose your Analytics property first.');
            $run = fn (array $body) => GoogleApi::post("https://analyticsdata.googleapis.com/v1beta/{$property}:runReport", $body);
            $now = ['startDate' => '28daysAgo', 'endDate' => 'yesterday'];
            $before = ['startDate' => '56daysAgo', 'endDate' => '29daysAgo'];
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

            return [
                'now' => $pick(0),
                'before' => $pick(1),
                'daily' => array_map(fn ($r) => [
                    'date' => preg_replace('/(\d{4})(\d{2})(\d{2})/', '$1-$2-$3', $r['dimensionValues'][0]['value']),
                    'users' => (int) $r['metricValues'][0]['value'],
                    'sessions' => (int) $r['metricValues'][1]['value'],
                ], $daily['rows'] ?? []),
                'pages' => $table('pagePath', ['screenPageViews', 'activeUsers'], 15),
                'channels' => $table('sessionDefaultChannelGroup', ['sessions', 'activeUsers'], 8),
                'sources' => $table('sessionSource', ['sessions'], 10),
                'devices' => $table('deviceCategory', ['activeUsers'], 4),
                'cities' => $table('city', ['activeUsers'], 10),
                'events' => $table('eventName', ['eventCount'], 10, ['filter' => ['fieldName' => 'eventName', 'inListFilter' => ['values' => ['generate_lead', 'click_whatsapp', 'click_call', 'click_email']]]]),
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }
}
