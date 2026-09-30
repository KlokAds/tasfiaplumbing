<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Content quality check in four parts, each scored 0–100:
 *
 *  SEO     classic ranking basics: title, description, keyword, length, headings, links, images.
 *  AEO     answer engines (featured snippets, Google AI Overviews, voice): a direct answer up top,
 *          question headings, FAQs, lists/tables, a clear price.
 *  GEO     generative engines (ChatGPT, Perplexity, Gemini) that quote and cite pages: concrete
 *          facts and numbers, local context, sources, freshness, the brand named in the text.
 *  E-E-A-T experience, expertise, authority, trust: a real author with a job title and bio,
 *          first-hand experience, warranty/prices/response time, dated content.
 *
 * Input is a plain array so the same check runs on a saved record and on an unsaved
 * editor form (live panel). Every failed check carries a tip that says what to do.
 */
class ContentQuality
{
    public const PILLARS = [
        'seo' => 'SEO',
        'aeo' => 'Answer engines (AEO)',
        'geo' => 'AI search (GEO)',
        'eeat' => 'Trust (E-E-A-T)',
    ];

    private const LOCAL_TERMS = ['singapore', 'hdb', 'condo', 'bto', 'landed', 'executive condominium', 'town council', 'mcst',
        'jurong', 'tampines', 'woodlands', 'bedok', 'punggol', 'sengkang', 'yishun', 'ang mo kio', 'bukit', 'toa payoh', 'clementi',
        'pasir ris', 'hougang', 'serangoon', 'bishan', 'choa chu kang', 'queenstown', 'kallang', 'geylang', 'marine parade'];

    private const AUTHORITY_HOSTS = ['gov.sg', 'bca.gov.sg', 'hdb.gov.sg', 'pub.gov.sg', 'nea.gov.sg', 'ema.gov.sg', 'spgroup.com.sg',
        'scdf.gov.sg', 'ura.gov.sg', 'mom.gov.sg', 'nparks.gov.sg', 'wikipedia.org', 'who.int', 'edu.sg'];

    private const EXPERIENCE_PHRASES = ['we ', 'our team', 'our technician', 'our engineer', 'in our experience', 'we have', "we've", 'we found',
        'we recommend', 'on site', 'on-site', 'last month', 'last year', 'recently', 'case study', 'customer', 'client', 'project'];

    /**
     * @param array{type?: string, name?: string, meta_title?: string, meta_desc?: string, excerpt?: string, body?: string,
     *              focus_keyword?: string, slug?: string, image?: string, faqs?: array, author?: array|null,
     *              updated_at?: string|null, has_service?: bool, prices?: int, warranty?: string, response_time?: string} $in
     */
    public static function analyze(array $in): array
    {
        $type = $in['type'] ?? 'article';
        $body = (string) ($in['body'] ?? '');
        $text = self::plain($body);
        $lower = Str::lower($text);
        $words = str_word_count($text);
        $title = trim((string) (($in['meta_title'] ?? '') ?: ($in['name'] ?? '')));
        $desc = trim((string) (($in['meta_desc'] ?? '') ?: ($in['excerpt'] ?? '')));
        $excerpt = trim((string) ($in['excerpt'] ?? ''));
        $kw = Str::lower(trim((string) ($in['focus_keyword'] ?? '')));
        $faqs = collect($in['faqs'] ?? [])->filter(fn ($f) => trim($f['question'] ?? '') !== '' && trim($f['answer'] ?? '') !== '');
        $minWords = config("seo.audit.min_words.{$type}", 300);

        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $body, $h2);
        preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $body, $h23);
        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $body, $links);
        preg_match_all('/<img\s[^>]*>/i', $body, $imgs);
        $internal = collect($links[1])->filter(fn ($h) => str_starts_with($h, '/') || str_contains($h, parse_url(config('app.url'), PHP_URL_HOST) ?: '#none#'));
        $external = collect($links[1])->filter(fn ($h) => str_starts_with($h, 'http') && !$internal->contains($h));
        $authority = $external->filter(fn ($h) => Str::contains(Str::lower((string) parse_url($h, PHP_URL_HOST)), self::AUTHORITY_HOSTS));
        $imgsNoAlt = collect($imgs[0])->filter(fn ($t) => !preg_match('/\salt=["\'][^"\']+["\']/i', $t))->count();
        $firstPara = preg_match('/<p[^>]*>(.*?)<\/p>/is', $body, $fp) ? self::plain($fp[1]) : Str::words($text, 60, '');
        $questions = collect($h23[1])->map(fn ($h) => trim(self::plain($h)))->filter(fn ($h) => str_ends_with($h, '?') || preg_match('/^(how|what|why|when|which|who|can|should|is|are|do|does)\b/i', $h));
        $numbers = preg_match_all('/\b\d+(?:[.,]\d+)?\s?(?:%|sqft|sq ft|m2|mm|cm|kw|hp|litre|liter|years?|months?|days?|hours?|mins?|units?|rooms?)?\b/i', $text);
        $hasPrice = (bool) preg_match('/S?\$\s?\d/', $text . ' ' . $excerpt . ' ' . $faqs->pluck('answer')->join(' '));
        $localHits = collect(self::LOCAL_TERMS)->filter(fn ($t) => str_contains($lower, $t))->count();
        $brand = Str::lower(Str::before(trim((string) \App\Models\SiteSetting::get('business.brand_name', config('app.name'))) . ' ', ' '));
        $author = $in['author'] ?? null;
        $updated = !empty($in['updated_at']) ? \Carbon\Carbon::parse($in['updated_at']) : null;
        $experience = collect(self::EXPERIENCE_PHRASES)->filter(fn ($p) => str_contains($lower, $p))->count();

        $kwIn = fn (string $haystack) => $kw !== '' && collect(preg_split('/\s+/', $kw))->filter(fn ($w) => strlen($w) > 2)->every(fn ($w) => str_contains(Str::lower($haystack), $w));

        $checks = [
            'seo' => [
                self::check(3, mb_strlen($title) >= 30 && mb_strlen($title) <= 60, 'Title is 30–60 characters', 'Write a title of 30–60 characters (now ' . mb_strlen($title) . '). Put the main keyword first.'),
                self::check(2, mb_strlen($desc) >= 70 && mb_strlen($desc) <= 160, 'Meta description is 70–160 characters', 'Write a 70–160 character description that answers the search and gives a reason to click.'),
                self::check(2, $kw !== '', 'Focus keyword set', 'Set the one search phrase this page should rank for.'),
                self::check(2, $kwIn($title), 'Keyword in title', 'Use the focus keyword in the title.'),
                self::check(2, $kwIn(Str::words($text, 100, '')), 'Keyword in the first 100 words', 'Mention the focus keyword early in the first paragraph.'),
                self::check(3, $words >= $minWords, "At least {$minWords} words", "Add useful detail: {$words} words now, aim for {$minWords}+ (steps, costs, causes, examples)."),
                self::check(2, count($h2[0]) >= 2, 'Two or more H2 sections', 'Split the text into sections with H2 headings.'),
                self::check(1, !preg_match('/<h1/i', $body), 'No extra H1 in the body', 'Remove the H1 from the body; the page title is already the H1.'),
                self::check(2, $internal->count() >= ($type === 'article' ? 2 : 1), 'Links to other pages on the site', 'Link to related services or articles (at least ' . ($type === 'article' ? 2 : 1) . ').'),
                self::check(1, $imgsNoAlt === 0, 'All images have alt text', "{$imgsNoAlt} image(s) have no alt text. Describe what is in the photo."),
                self::check(1, !empty($in['image']), 'Featured image set', 'Add a featured image (used for sharing and in Google Discover).'),
            ],
            'aeo' => [
                self::check(3, mb_strlen($excerpt) >= 80, 'Direct answer at the top', 'Write a 1–2 sentence answer to the main question (with the price range) in the summary field.'),
                self::check(2, str_word_count($firstPara) > 0 && str_word_count($firstPara) <= 70, 'Short first paragraph', 'Start with a short paragraph (under 70 words) that answers the question directly.'),
                self::check(2, $questions->count() >= 1, 'Question-style headings', 'Phrase at least one H2/H3 as a question people ask, e.g. “How much does … cost?”'),
                self::check(3, $faqs->count() >= 3, 'Three or more FAQs', 'Add at least 3 FAQs with short, complete answers (now ' . $faqs->count() . ').'),
                self::check(2, (bool) preg_match('/<(ul|ol|table)\b/i', $body), 'Lists or a table', 'Use a bullet list, numbered steps or a price table; answer engines lift these.'),
                self::check(2, $hasPrice, 'Clear price in S$', 'State a price range in S$ (e.g. S$80–S$150); it is the most asked question.'),
            ],
            'geo' => [
                self::check(3, $numbers >= 5, 'Concrete facts and numbers', 'Add specific figures: prices, sizes, durations, warranty years, how many jobs done.'),
                self::check(2, $localHits >= 2, 'Local Singapore context', 'Mention Singapore specifics: HDB/condo, areas, local rules, climate.'),
                self::check(2, $authority->count() >= 1, 'Cites an official source', 'Link to an official source (e.g. BCA, HDB, PUB, NEA, SP Group) where you mention rules or facts.'),
                self::check(2, $brand !== '' && str_contains($lower, $brand), 'Brand named in the text', 'Name the business in the text so AI answers can attribute the advice to you.'),
                self::check(2, $updated && $updated->gt(now()->subMonths(12)), 'Updated in the last 12 months', 'Review and update the content; AI search prefers fresh pages.'),
                self::check(1, $words >= 250 && count($h23[0]) >= 3, 'Clear structure to quote from', 'Use 3+ headed sections so each part can be quoted on its own.'),
            ],
            'eeat' => $type === 'article'
                ? [
                    self::check(3, !empty($author['name']), 'Named author', 'Articles need a real author. The writer is set automatically.'),
                    self::check(2, !empty($author['job_title']), 'Author job title', 'Add a job title in My account (e.g. “Licensed plumber, 10 years”).'),
                    self::check(2, !empty($author['bio']), 'Author bio', 'Add a 2–3 sentence bio with experience and licences in My account.'),
                    self::check(3, $experience >= 3, 'First-hand experience', 'Share what your team sees on real jobs: “On HDB jobs we often find…”, photos, a short case.'),
                    self::check(2, !empty($in['has_service']), 'Linked to a service', 'Link the article to the service it supports so readers can act.'),
                ]
                : [
                    self::check(3, ($in['prices'] ?? 0) > 0, 'Prices listed', 'Add real price ranges in the price list.'),
                    self::check(2, !empty($in['warranty']), 'Warranty stated', 'State the warranty (e.g. 90 days workmanship).'),
                    self::check(2, !empty($in['response_time']), 'Response time stated', 'State how fast you respond (e.g. same day).'),
                    self::check(3, $experience >= 3, 'First-hand experience', 'Describe how your team actually does the job and what you see on site.'),
                ],
        ];

        $pillars = [];
        foreach ($checks as $key => $list) {
            $total = array_sum(array_column($list, 'weight'));
            $got = array_sum(array_map(fn ($c) => $c['ok'] ? $c['weight'] : 0, $list));
            $pillars[$key] = ['label' => self::PILLARS[$key], 'score' => $total ? (int) round($got / $total * 100) : 100, 'checks' => $list];
        }

        return [
            'score' => (int) round(collect($pillars)->avg('score')),
            'pillars' => $pillars,
            'stats' => ['words' => $words, 'faqs' => $faqs->count(), 'internal_links' => $internal->count(), 'sources' => $authority->count()],
        ];
    }

    public static function forArticle(\App\Models\BlogDetail $b, ?array $faqs = null): array
    {
        $author = $b->author;

        return self::analyze([
            'type' => 'article', 'name' => $b->name, 'meta_title' => $b->meta_title, 'meta_desc' => $b->meta_desc,
            'excerpt' => $b->excerpt, 'body' => $b->desc, 'focus_keyword' => $b->focus_keyword, 'slug' => $b->slug, 'image' => $b->image,
            'faqs' => $faqs ?? $b->faqs->map->only(['question', 'answer'])->all(),
            'author' => $author ? ['name' => $author->name, 'job_title' => $author->job_title, 'bio' => $author->bio] : ($b->auth_name ? ['name' => $b->auth_name] : null),
            'updated_at' => ($b->content_updated_at ?? $b->updated_at)?->toIso8601String(),
            'has_service' => (bool) $b->primary_service_id,
        ]);
    }

    public static function forService(\App\Models\ServiceDetail $s, ?array $faqs = null): array
    {
        return self::analyze([
            'type' => 'service', 'name' => $s->name, 'meta_title' => $s->meta_title, 'meta_desc' => $s->meta_desc,
            'excerpt' => $s->short_summary, 'body' => $s->desc, 'focus_keyword' => $s->focus_keyword, 'slug' => $s->slug, 'image' => $s->image,
            'faqs' => $faqs ?? $s->faqs->map->only(['question', 'answer'])->all(),
            'updated_at' => ($s->content_updated_at ?? $s->updated_at)?->toIso8601String(),
            'prices' => $s->prices()->count(), 'warranty' => $s->warranty, 'response_time' => $s->response_time,
        ]);
    }

    private static function check(int $weight, bool $ok, string $label, string $tip): array
    {
        return ['ok' => $ok, 'label' => $label, 'tip' => $ok ? null : $tip, 'weight' => $weight];
    }

    private static function plain(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ' ', $html)))));
    }
}
