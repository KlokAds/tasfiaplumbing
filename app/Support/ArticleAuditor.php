<?php

namespace App\Support;

use App\Models\ArticleAudit;
use App\Models\BlogDetail;
use App\Models\Redirect;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Support\Google\GoogleApi;
use App\Support\Google\SearchConsole;
use Illuminate\Support\Collection;

/**
 * Article audit: every live article grouped by topic, with its length, quality score, overlap with
 * other articles, Search Console clicks and impressions (16 months) and a suggested action:
 * keep, update, give it a new angle, merge into the strongest article (301), or noindex.
 *
 * It only writes the article_audits table. Approvers decide in Articles → Audit; nothing on the
 * website changes here.
 */
class ArticleAuditor
{
    private const API = 'https://www.googleapis.com/webmasters/v3';

    /** Words per compared chunk, and 1 in SAMPLE chunks kept (enough to estimate overlap, light on memory). */
    private const SHINGLE = 8;

    private const SAMPLE = 5;

    /** A chunk found in more articles than this is boilerplate ("contact us today …") and ignored. */
    private const COMMON = 25;

    public static function thresholds(): array
    {
        return [
            'keep_clicks' => (int) config('admin.audit.keep_clicks', 10),
            'seen_impressions' => (int) config('admin.audit.seen_impressions', 200),
            'thin_words' => (int) config('admin.audit.thin_words', 300),
            'merge_overlap' => (int) config('admin.audit.merge_overlap', 40),
        ];
    }

    /** @return array{articles: int, search_console: bool, error: ?string} */
    public static function run(): array
    {
        @set_time_limit(600);
        $t = self::thresholds();
        $articles = BlogDetail::with('author')->where('status', BlogDetail::PUBLISHED)->get();
        [$search, $error] = self::searchData($articles);

        // Topic groups, and the service pages whose search an article targets too.
        $services = ServiceDetail::where('is_active', true)->get(['id', 'name', 'slug'])
            ->mapWithKeys(fn ($s) => [self::serviceKey((string) $s->name) => $s]);
        $groupOf = $articles->mapWithKeys(fn ($b) => [$b->id => self::groupKey($b)]);
        $groups = $groupOf->countBy();

        $overlap = self::overlap($articles);

        $rows = $articles->map(function (BlogDetail $b) use ($search, $groupOf, $groups, $services, $overlap) {
            $s = $search[$b->id] ?? null;

            return [
                'article' => $b,
                'group_key' => $groupOf[$b->id],
                'group_size' => $groups[$groupOf[$b->id]],
                'words' => SeoAudit::wordCount((string) $b->desc),
                'score' => (int) (rescue(fn () => ContentQuality::forArticle($b)['score'], 0, false)),
                'dup_percent' => $overlap[$b->id][1] ?? 0,
                'dup_article_id' => $overlap[$b->id][0] ?? null,
                'clicks' => (int) ($s['clicks'] ?? 0),
                'impressions' => (int) ($s['impressions'] ?? 0),
                'position' => !empty($s['impressions']) ? round($s['position_sum'] / $s['impressions'], 1) : null,
                'top_query' => $s['top_query'] ?? null,
                'service' => $services[self::serviceKey($groupOf[$b->id])] ?? null,
            ];
        });

        // Strength: clicks, impressions, quality, length; an article only merges into a stronger one.
        $rank = $rows->sortBy([['clicks', 'desc'], ['impressions', 'desc'], ['score', 'desc'], ['words', 'desc']])->values()
            ->mapWithKeys(fn ($r, $i) => [$r['article']->id => $i])->all();

        $plan = [];
        foreach ($rows->groupBy('group_key') as $members) {
            $ordered = $members->sortBy(fn ($r) => $rank[$r['article']->id])->values();
            foreach ($ordered as $r) {
                $plan[$r['article']->id] = self::suggest($r, $ordered->first(), $rows, $t, $rank);
            }
        }
        // A merge into an article that is itself merged goes straight to the last one (no redirect chains).
        foreach ($plan as $id => [$suggestion, $target]) {
            for ($i = 0; $suggestion === 'merge' && $target && ($plan[$target][0] ?? null) === 'merge' && $plan[$target][1] && $i < 10; $i++) {
                $target = $plan[$target][1];
            }
            $plan[$id][1] = $target;
        }

        $now = now();
        foreach ($rows as $r) {
            [$suggestion, $target, $reason] = $plan[$r['article']->id];
            ArticleAudit::updateOrCreate(['article_id' => $r['article']->id], [
                'group_key' => mb_substr($r['group_key'], 0, 190),
                'group_size' => $r['group_size'],
                'words' => $r['words'],
                'score' => max(0, min(100, $r['score'])),
                'dup_percent' => $r['dup_percent'],
                'dup_article_id' => $r['dup_article_id'],
                'clicks' => $r['clicks'],
                'impressions' => $r['impressions'],
                'position' => $r['position'],
                'top_query' => $r['top_query'] ? mb_substr($r['top_query'], 0, 190) : null,
                'service_id' => $r['service']?->id,
                'suggestion' => $suggestion,
                'target_article_id' => $target,
                'reason' => mb_substr($reason, 0, 500),
                'computed_at' => $now,
            ]);
        }

        SiteSetting::putMany([
            'audit.computed_at' => $now->toIso8601String(),
            'audit.search_console' => $search === null ? '0' : '1',
            'audit.error' => (string) $error,
        ]);

        return ['articles' => $articles->count(), 'search_console' => $search !== null, 'error' => $error];
    }

    /** @return array{0: string, 1: ?int, 2: string} suggestion, article to merge into, reason */
    private static function suggest(array $r, array $leader, Collection $rows, array $t, array $rank): array
    {
        $isLeader = $r['article']->id === $leader['article']->id;
        $clicks = $r['clicks'];
        $seen = $r['impressions'] >= $t['seen_impressions'];
        $service = $r['service'];

        if ($service && $clicks < $t['keep_clicks']) {
            return $seen
                ? ['retarget', null, "Targets the same search as the service page “{$service->name}”. Google shows it ({$r['impressions']} impressions) but it competes with the money page: give it its own angle (cost, problems, HDB/condo…) and link to the service page."]
                : ['merge', null, "Targets the same search as the service page “{$service->name}” and has {$clicks} clicks in 16 months. Move any useful part into the service page, then 301 this URL to it."];
        }

        if ($r['group_size'] === 1 || $isLeader) {
            $lead = $r['group_size'] > 1 ? "Strongest of {$r['group_size']} articles on “{$r['group_key']}” ({$clicks} clicks). " : '';
            if ($r['words'] < 150 && $clicks === 0 && $r['impressions'] < 10) {
                return ['noindex', null, $lead . "Very short ({$r['words']} words) and Google never shows it. Rewrite it properly, or keep it off Google."];
            }
            if ($r['words'] < $t['thin_words']) {
                return ['update', null, $lead . "Thin: {$r['words']} words. Add real detail (prices, steps, what we see on jobs) to 600+ words."];
            }
            if ($r['score'] < 60) {
                return ['update', null, $lead . "Content quality {$r['score']}/100. Fix the checks in the editor (direct answer, FAQs, service link, author)."];
            }

            return ['keep', null, $lead ?: 'Its own topic and in good shape. Keep it.'];
        }

        $leaderName = '“' . $leader['article']->name . '”';
        if ($clicks >= $t['keep_clicks']) {
            return ['retarget', null, "Has {$clicks} clicks, so keep it, but give it its own angle so it stops competing with {$leaderName}."];
        }
        if ($seen) {
            return ['retarget', null, "Google shows it ({$r['impressions']} impressions) but it rarely gets clicks next to {$leaderName}. Give it its own angle, or merge it."];
        }
        $dupId = $r['dup_article_id'];
        if ($dupId && $r['dup_percent'] >= $t['merge_overlap'] && $dupId !== $r['article']->id) {
            $dup = $rows->first(fn ($x) => $x['article']->id === $dupId);
            if ($dup && ($rank[$dupId] ?? PHP_INT_MAX) < $rank[$r['article']->id]) {
                return ['merge', $dupId, "{$r['dup_percent']}% the same text as “{$dup['article']->name}” and {$clicks} clicks in 16 months. Merge the useful parts into it, then 301."];
            }
        }

        return ['merge', $leader['article']->id, "Same topic as {$leaderName}, which is stronger, and {$clicks} clicks in 16 months. Merge the useful parts into it, then 301."];
    }

    /** The topic of an article: its focus keyword, or one suggested from the title, without "singapore" and plurals. */
    public static function groupKey(BlogDetail $b): string
    {
        $base = filled($b->focus_keyword) ? (string) $b->focus_keyword : FocusKeyword::suggest((string) $b->name);

        return self::normalize($base) ?: 'other';
    }

    /** "Door Repair Services" and the topic "door repair" are the same search. */
    private static function serviceKey(string $text): string
    {
        $words = array_diff(explode(' ', self::normalize($text)), ['service', 'servicing']);

        return implode(' ', $words) ?: self::normalize($text);
    }

    public static function normalize(string $text): string
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map(fn ($w) => strlen($w) > 4 && str_ends_with($w, 's') && !str_ends_with($w, 'ss') ? substr($w, 0, -1) : $w, $words);

        return implode(' ', array_values(array_filter($words, fn ($w) => !in_array($w, ['singapore', 'sg', 'in', 'the', 'and', 'for', 'of'], true))));
    }

    /**
     * Search Console clicks, impressions, position and top search per article over 16 months.
     * Old URLs (/blog/…, redirected paths) count for the article they lead to.
     *
     * @return array{0: ?array<int, array>, 1: ?string}
     */
    private static function searchData(Collection $articles): array
    {
        $site = SearchConsole::property();
        if (!$site || !GoogleApi::connected()) {
            return [null, 'Search Console is not connected (Insights → Google). The audit uses length, quality and overlap only.'];
        }

        $slugs = $articles->mapWithKeys(fn ($b) => [mb_strtolower((string) $b->slug) => $b->id])->all();
        $redirects = Redirect::where('is_active', true)->pluck('to_path', 'from_path')->all();
        $query = fn (array $dims, int $start) => GoogleApi::post(self::API . '/sites/' . rawurlencode($site) . '/searchAnalytics/query', [
            'startDate' => now()->subMonths(16)->addDays(2)->toDateString(),
            'endDate' => now()->subDays(2)->toDateString(),
            'dimensions' => $dims,
            'rowLimit' => 25000,
            'startRow' => $start,
        ])['rows'] ?? [];

        try {
            $out = [];
            foreach ($query(['page'], 0) as $row) {
                if ($id = self::articleFor($row['keys'][0], $slugs, $redirects)) {
                    $out[$id] ??= ['clicks' => 0, 'impressions' => 0, 'position_sum' => 0.0, 'top_query' => null, 'top' => -1];
                    $out[$id]['clicks'] += (int) $row['clicks'];
                    $out[$id]['impressions'] += (int) $row['impressions'];
                    $out[$id]['position_sum'] += (float) $row['position'] * (int) $row['impressions'];
                }
            }
            // The search that brings each article the most clicks (then impressions).
            for ($start = 0; $start < 100000; $start += 25000) {
                $rows = $query(['page', 'query'], $start);
                foreach ($rows as $row) {
                    $id = self::articleFor($row['keys'][0], $slugs, $redirects);
                    if ($id && isset($out[$id])) {
                        $weight = (int) $row['clicks'] * 100000 + (int) $row['impressions'];
                        if ($weight > $out[$id]['top']) {
                            [$out[$id]['top'], $out[$id]['top_query']] = [$weight, $row['keys'][1]];
                        }
                    }
                }
                if (count($rows) < 25000) {
                    break;
                }
            }

            return [$out, null];
        } catch (\Throwable $e) {
            return [null, 'Search Console did not answer: ' . mb_substr($e->getMessage(), 0, 200)];
        }
    }

    private static function articleFor(string $url, array $slugs, array $redirects): ?int
    {
        $path = rtrim(rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?: '/')), '/') ?: '/';
        for ($i = 0; $i < 3 && isset($redirects[$path]); $i++) {
            $path = rtrim((string) (parse_url((string) $redirects[$path], PHP_URL_PATH) ?: '/'), '/') ?: '/';
        }

        return preg_match('#^/blogs?/([^/]+)$#', $path, $m) ? ($slugs[mb_strtolower($m[1])] ?? null) : null;
    }

    /**
     * For each article, the other article that shares the most text, and the share (%).
     * Sampled 8-word chunks in an inverted index, so 2,000 articles fit in little memory.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private static function overlap(Collection $articles): array
    {
        $sets = [];
        $index = [];
        foreach ($articles as $b) {
            $sets[$b->id] = self::shingles((string) $b->desc);
            foreach ($sets[$b->id] as $h => $_) {
                $index[$h][] = $b->id;
            }
        }

        $out = [];
        foreach ($sets as $id => $set) {
            if (count($set) < 5) {
                continue;
            }
            $counts = [];
            foreach ($set as $h => $_) {
                $others = $index[$h];
                if (count($others) > self::COMMON) {
                    continue;
                }
                foreach ($others as $other) {
                    if ($other !== $id) {
                        $counts[$other] = ($counts[$other] ?? 0) + 1;
                    }
                }
            }
            if ($counts) {
                arsort($counts);
                $best = array_key_first($counts);
                $out[$id] = [$best, (int) min(100, round(100 * $counts[$best] / count($set)))];
            }
        }

        return $out;
    }

    private static function shingles(string $html): array
    {
        $text = html_entity_decode(strip_tags(preg_replace('#</(p|h[1-6]|li|tr|div)>|<br\s*/?>#i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = preg_split('/[^a-z0-9$]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        for ($i = 0, $n = count($words) - self::SHINGLE; $i <= $n; $i++) {
            $h = crc32(implode(' ', array_slice($words, $i, self::SHINGLE)));
            if ($h % self::SAMPLE === 0) {
                $out[$h] = true;
            }
        }

        return $out;
    }
}
