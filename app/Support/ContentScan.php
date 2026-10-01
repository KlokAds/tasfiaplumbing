<?php

namespace App\Support;

use App\Models\BlogDetail;
use App\Models\GoogleReview;
use App\Models\PageSeo;
use App\Models\Price;
use App\Models\ProjectDetail;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Support\Google\GoogleApi;
use App\Support\Google\SearchConsole;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Daily scan of the whole site (cron, see routes/console.php; or "Re-scan now" in the
 * Writing guide). It scores every live article and service with ContentQuality and turns
 * the result into a plan:
 * - the checks that fail most, weighted by how many points they cost, with the weakest pages;
 * - the articles to update this week (low score, old, or getting Google traffic);
 * - new article ideas from Search Console questions no page answers yet;
 * - the services to improve and the other pages and settings that need attention.
 * The result is kept until the next scan, so opening the guide never waits for a scan.
 */
class ContentScan
{
    public const CACHE_KEY = 'content.scan';
    private const PAGES_PER_ISSUE = 5;
    private const ARTICLES_PER_WEEK = 5;
    private const STALE_MONTHS = 12;
    private const QUESTION_STOPWORDS = ['singapore', 'near', 'best', 'cheap', 'cost', 'price', 'service', 'services', 'company', 'with', 'from', 'what', 'much', 'does', 'that', 'this', 'your'];

    /** The last scan; runs one only if there is none yet (or $refresh). */
    public static function get(bool $refresh = false): array
    {
        if ($refresh || !Cache::has(self::CACHE_KEY)) {
            return self::refresh();
        }

        return Cache::get(self::CACHE_KEY);
    }

    public static function refresh(): array
    {
        $scan = self::run();
        Cache::forever(self::CACHE_KEY, $scan);

        return $scan;
    }

    private static function run(): array
    {
        $types = ['article' => self::bucket(), 'service' => self::bucket()];
        $articles = [];
        $authors = [];

        BlogDetail::where('status', BlogDetail::PUBLISHED)
            ->with(['author:id,name,job_title,bio', 'faqs'])
            ->chunkById(200, function ($blogs) use (&$types, &$articles, &$authors) {
                foreach ($blogs as $b) {
                    $q = ContentQuality::forArticle($b);
                    self::add($types['article'], $b->name, '/admin/blogs?edit=' . $b->id, $q);
                    $articles[] = [
                        'name' => $b->name,
                        'url' => '/admin/blogs?edit=' . $b->id,
                        'path' => rtrim($b->publicPath(), '/') ?: '/',
                        'score' => $q['score'],
                        'updated' => $b->content_updated_at ?? $b->updated_at,
                        'missing' => self::missing($q),
                    ];
                    $a = $b->author;
                    if ($a && (blank($a->job_title) || blank($a->bio))) {
                        $authors[$a->id] ??= ['name' => $a->name, 'articles' => 0, 'job_title' => !blank($a->job_title), 'bio' => !blank($a->bio)];
                        $authors[$a->id]['articles']++;
                    }
                }
            });

        $services = [];
        foreach (ServiceDetail::where('is_active', true)->with('faqs')->get() as $s) {
            $q = ContentQuality::forService($s);
            self::add($types['service'], $s->name, '/admin/services?edit=' . $s->id, $q);
            $services[] = ['name' => $s->name, 'url' => '/admin/services?edit=' . $s->id, 'score' => $q['score'], 'missing' => self::missing($q)];
        }

        $search = self::search();

        return [
            'generated_at' => now()->toIso8601String(),
            'search_connected' => $search !== null,
            'types' => collect($types)->map(fn ($t) => self::summary($t))->all(),
            'authors' => collect($authors)->sortByDesc('articles')->values()->all(),
            'plan' => [
                'articles' => self::articlesToUpdate($articles, $search),
                'ideas' => self::articleIdeas($search, collect($articles)->pluck('name')->concat(collect($services)->pluck('name'))),
                'services' => collect($services)->where('score', '<', 80)->sortBy('score')->take(5)->values()->all(),
                'site' => self::siteTasks(),
            ],
        ];
    }

    // ---------- Scores and gaps ----------

    private static function bucket(): array
    {
        return ['pages' => [], 'sums' => ['score' => 0, 'seo' => 0, 'aeo' => 0, 'geo' => 0, 'eeat' => 0], 'issues' => []];
    }

    private static function add(array &$bucket, string $name, string $url, array $quality): void
    {
        $i = count($bucket['pages']);
        $bucket['pages'][] = ['name' => $name, 'url' => $url, 'score' => $quality['score']];
        $bucket['sums']['score'] += $quality['score'];
        foreach ($quality['pillars'] as $key => $pillar) {
            $bucket['sums'][$key] = ($bucket['sums'][$key] ?? 0) + $pillar['score'];
            foreach ($pillar['checks'] as $c) {
                if ($c['ok']) {
                    continue;
                }
                $id = $key . '|' . $c['label'];
                $bucket['issues'][$id] ??= ['pillar' => $key, 'label' => $c['label'], 'tip' => self::generalTip((string) $c['tip']), 'weight' => $c['weight'], 'pages' => []];
                $bucket['issues'][$id]['pages'][] = $i;
            }
        }
    }

    /** The three failed checks that cost the most points on one page. */
    private static function missing(array $quality): array
    {
        return collect($quality['pillars'])->flatMap(fn ($p) => $p['checks'])->where('ok', false)
            ->sortByDesc('weight')->take(3)->pluck('label')->values()->all();
    }

    private static function summary(array $bucket): array
    {
        $total = count($bucket['pages']);
        if ($total === 0) {
            return ['total' => 0, 'avg' => null, 'issues' => []];
        }

        // Most costly gaps first. Each gap lists the weakest pages that have it and are not already
        // listed under a gap above, so the same few pages are not repeated under every gap.
        $shown = [];
        $issues = collect($bucket['issues'])
            ->sortByDesc(fn ($issue) => count($issue['pages']) * $issue['weight'])
            ->map(function ($issue) use ($bucket, $total, &$shown) {
                $count = count($issue['pages']);
                $pages = collect($issue['pages'])->sortBy(fn ($i) => $bucket['pages'][$i]['score']);
                $fresh = $pages->reject(fn ($i) => isset($shown[$i]))->take(self::PAGES_PER_ISSUE);
                $pick = $fresh->isNotEmpty() ? $fresh : $pages->take(self::PAGES_PER_ISSUE);
                foreach ($pick as $i) {
                    $shown[$i] = true;
                }

                return [
                    'pillar' => $issue['pillar'],
                    'label' => $issue['label'],
                    'tip' => $issue['tip'],
                    'weight' => $issue['weight'],
                    'count' => $count,
                    'share' => (int) round($count / $total * 100),
                    'pages' => $pick->map(fn ($i) => $bucket['pages'][$i])->values()->all(),
                ];
            })->values()->all();

        return [
            'total' => $total,
            'avg' => collect($bucket['sums'])->map(fn ($sum) => (int) round($sum / $total))->all(),
            'issues' => $issues,
        ];
    }

    // ---------- Plan ----------

    /** Search Console pages (by path) and queries, or null when Google is not connected. */
    private static function search(): ?array
    {
        try {
            if (!GoogleApi::connected() || !SearchConsole::property()) {
                return null;
            }
            $report = SearchConsole::report();
        } catch (\Throwable) {
            return null;
        }

        return [
            'pages' => collect($report['pages'] ?? [])->keyBy(fn ($p) => rtrim($p['path'], '/') ?: '/')->all(),
            'queries' => $report['queries'] ?? [],
        ];
    }

    /**
     * Articles to update this week. Pages Google already shows come first (an update there pays off
     * soonest), then old pages, then low scores. Pages changed in the last 30 days are left alone.
     */
    private static function articlesToUpdate(array $articles, ?array $search): array
    {
        return collect($articles)
            ->reject(fn ($a) => $a['updated'] && Carbon::parse($a['updated'])->gt(now()->subDays(30)))
            ->filter(fn ($a) => $a['score'] < 80)
            ->map(function ($a) use ($search) {
                $priority = 100 - $a['score'];
                $reasons = [];
                if ($hit = $search['pages'][$a['path']] ?? null) {
                    $priority += 30 + min(40, $hit['impressions'] / 25);
                    $reasons[] = number_format($hit['impressions']) . ' Google impressions, ' . number_format($hit['clicks']) . ' clicks';
                }
                $months = $a['updated'] ? (int) Carbon::parse($a['updated'])->diffInMonths(now()) : null;
                if ($months === null || $months >= self::STALE_MONTHS) {
                    $priority += 20;
                    $reasons[] = $months === null ? 'Never updated' : "Not updated for {$months} months";
                }
                $reasons[] = "Score {$a['score']}";

                return ['name' => $a['name'], 'url' => $a['url'], 'score' => $a['score'], 'reasons' => $reasons, 'missing' => $a['missing'], 'priority' => $priority];
            })
            ->sortByDesc('priority')->take(self::ARTICLES_PER_WEEK)
            ->map(fn ($a) => collect($a)->except('priority')->all())
            ->values()->all();
    }

    /**
     * Search Console questions with impressions that no article or service title covers yet.
     * Searches for the business itself are skipped, and near-identical searches ("door repair",
     * "door repairs") count once, so the list holds real, different topics only.
     */
    private static function articleIdeas(?array $search, $titles): array
    {
        if (!$search) {
            return [];
        }
        $titles = $titles->map(fn ($t) => Str::lower((string) $t))->all();
        $brand = collect(preg_split('/[^a-z0-9]+/', Str::lower((string) SiteSetting::get('business.brand_name', config('app.name')))))
            ->filter(fn ($w) => strlen($w) > 3 && !in_array($w, self::QUESTION_STOPWORDS, true))->values();
        // "repairs" and "repair" are the same topic.
        $stem = fn (string $w) => strlen($w) > 4 && str_ends_with($w, 's') ? substr($w, 0, -1) : $w;

        return collect($search['queries'])
            ->filter(fn ($q) => $q['impressions'] >= 10)
            ->map(fn ($q) => $q + ['words' => collect(preg_split('/[^a-z0-9]+/', Str::lower($q['key'])))
                ->filter(fn ($w) => strlen($w) > 3 && !in_array($w, self::QUESTION_STOPWORDS, true))
                ->map($stem)->unique()->sort()->values()])
            ->filter(fn ($q) => $q['words']->isNotEmpty())
            // A search made only of the business name is someone looking for you, not a topic.
            ->reject(fn ($q) => $brand->isNotEmpty() && $q['words']->diff($brand->map($stem))->isEmpty())
            ->reject(function ($q) use ($titles) {
                foreach ($titles as $t) {
                    if ($q['words']->every(fn ($w) => str_contains($t, $w))) {
                        return true;
                    }
                }

                return false;
            })
            ->sortByDesc('impressions')
            ->unique(fn ($q) => $q['words']->join(' '))
            ->take(8)
            ->map(fn ($q) => ['query' => $q['key'], 'impressions' => $q['impressions'], 'position' => $q['position']])
            ->values()->all();
    }

    /** Other pages and settings: the open Website checklist items (contact, about, 404s, cron...) plus regular upkeep. */
    private static function siteTasks(): array
    {
        $tasks = [];
        try {
            foreach (SiteChecklist::groups() as $group) {
                foreach ($group['items'] as $item) {
                    if (!$item['ok']) {
                        $tasks[] = ['label' => $item['label'], 'detail' => $item['detail'], 'url' => $item['fix'], 'level' => $item['level'] === 'must' ? 'must' : 'should'];
                    }
                }
            }
        } catch (\Throwable) {
            // The checklist is a bonus here; the rest of the plan still works without it.
        }

        $missingSeo = collect(config('seo.static_pages', []))
            ->filter(function ($page, $key) {
                $row = PageSeo::where('key', $key)->first();

                return !$row || blank($row->meta_title) || blank($row->meta_desc);
            })->pluck('label');
        if ($missingSeo->isNotEmpty()) {
            $tasks[] = ['label' => 'Page SEO for fixed pages', 'detail' => 'No own meta title or description yet: ' . $missingSeo->join(', ') . '.', 'url' => '/admin/page-seo', 'level' => 'should'];
        }

        $stalePrices = Price::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('last_reviewed_at')->orWhere('last_reviewed_at', '<', now()->subDays(Price::STALE_AFTER_DAYS)))->count();
        if ($stalePrices) {
            $tasks[] = ['label' => 'Check your prices', 'detail' => "{$stalePrices} prices not checked for " . Price::STALE_AFTER_DAYS . '+ days. Open each one and save it if it is still right.', 'url' => '/admin/pricing', 'level' => 'should'];
        }

        $lastProject = ProjectDetail::max('created_at');
        if (!$lastProject || Carbon::parse($lastProject)->lt(now()->subDays(60))) {
            $tasks[] = ['label' => 'Add a recent project', 'detail' => $lastProject ? 'No new project for ' . (int) Carbon::parse($lastProject)->diffInDays(now()) . ' days. Real recent jobs build trust.' : 'No projects yet. Add real jobs with photos.', 'url' => '/admin/projects', 'level' => 'should'];
        }

        try {
            $unanswered = GoogleReview::whereNull('reply')->where('is_hidden', false)->where('reviewed_at', '>=', now()->subDays(90))->count();
            if ($unanswered) {
                $tasks[] = ['label' => 'Reply to Google reviews', 'detail' => "{$unanswered} reviews from the last 90 days have no reply yet.", 'url' => '/admin/reviews', 'level' => 'should'];
            }
        } catch (\Throwable) {
            // reviews table not in use on this site
        }

        try {
            foreach (SeoAudit::overview()['by_type'] ?? [] as $type => $t) {
                if (in_array($type, ['location', 'category'], true) && ($t['with_errors'] ?? 0) > 0) {
                    $tasks[] = ['label' => ($type === 'location' ? 'Area pages' : 'Service categories') . ' with SEO errors', 'detail' => "{$t['with_errors']} of {$t['total']} need fixing (thin text, missing title or description).", 'url' => '/admin/seo/health?type=' . $type, 'level' => 'should'];
                }
            }
        } catch (\Throwable) {
        }

        return collect($tasks)->sortBy(fn ($t) => $t['level'] === 'must' ? 0 : 1)->values()->all();
    }

    /** Tips are written for one page ("now 0", "0 words now"); drop the page-specific part. */
    public static function generalTip(string $tip): string
    {
        $tip = preg_replace('/\s*\(now \d+\)/', '', $tip);
        $tip = preg_replace('/\b\d+ words now, aim for/', 'aim for', $tip);

        return preg_replace('/^\d+ image\(s\) have no alt text\./', 'Every image needs alt text.', $tip);
    }
}
