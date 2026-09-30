<?php

namespace App\Support;

use App\Models\BlogDetail;
use App\Models\Location;
use App\Models\ServiceCategory;
use App\Models\ServiceDetail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Single rule set for the publish checklist. Every admin list, the SEO Health page
 * and the dashboard read from here, so a rule changes in one place only.
 */
class SeoAudit
{
    public const CACHE_KEY = 'seo.audit.overview';

    public static function service(ServiceDetail $s, array $dupTitles = []): array
    {
        $c = config('seo.audit');
        $issues = self::common($s->name, $s->meta_title, $s->meta_desc, $s->desc, $s->image, $s->noindex, $dupTitles, $c['min_words']['service']);

        if (!$s->category_id) {
            $issues[] = self::warn('no_category', 'Not in a service category (breaks the silo / breadcrumb).');
        }
        if (blank($s->short_summary)) {
            $issues[] = self::warn('no_summary', 'No answer-first summary (1–2 lines AI engines can quote).');
        }
        if (($s->prices_count ?? $s->prices()->count()) === 0) {
            $issues[] = self::warn('no_price', 'No price in the price list.');
        }
        $faqs = $s->faqs_count ?? $s->faqs()->count();
        if ($faqs < $c['min_faqs']['service']) {
            $issues[] = self::warn('few_faqs', "Only {$faqs} FAQ(s); plan needs at least {$c['min_faqs']['service']}.");
        }
        if (blank($s->response_time) || blank($s->warranty)) {
            $issues[] = self::warn('no_facts', 'Response time or warranty missing (quick facts box).');
        }

        return self::result($issues);
    }

    public static function article(BlogDetail $b, array $dupTitles = []): array
    {
        $c = config('seo.audit');
        $issues = self::common($b->name, $b->meta_title, $b->meta_desc, $b->desc, $b->image, $b->noindex, $dupTitles, $c['min_words']['article']);

        if (!$b->primary_service_id) {
            $issues[] = self::error('no_service', 'Not linked to a service page (every article must support a money page).');
        } elseif (!Str::contains((string) $b->desc, ['/service/', '/services/'])) {
            $issues[] = self::warn('no_internal_link', 'Body has no link to a service page.');
        }

        return self::result($issues);
    }

    public static function location(Location $l): array
    {
        $c = config('seo.audit');
        $issues = self::common($l->name, $l->meta_title, $l->meta_desc, trim($l->intro . ' ' . $l->description), $l->image, $l->noindex, [], $c['min_words']['location']);

        if (blank($l->intro)) {
            $issues[] = self::error('no_intro', 'No unique local intro (location pages without it are doorway pages).');
        }
        if (empty($l->property_types)) {
            $issues[] = self::warn('no_property_types', 'Property types not set (HDB / condo / landed).');
        }
        $faqs = $l->faqs_count ?? $l->faqs()->count();
        if ($faqs < $c['min_faqs']['location']) {
            $issues[] = self::warn('few_faqs', "Only {$faqs} local FAQ(s); plan needs at least {$c['min_faqs']['location']}.");
        }

        return self::result($issues);
    }

    public static function category(ServiceCategory $cat): array
    {
        $c = config('seo.audit');
        $issues = self::common($cat->name, $cat->meta_title, $cat->meta_desc, trim($cat->intro . ' ' . $cat->description), $cat->image, $cat->noindex, [], $c['min_words']['category']);

        if (($cat->services_count ?? $cat->services()->count()) === 0) {
            $issues[] = self::warn('empty_category', 'No services assigned.');
        }

        return self::result($issues);
    }

    /** Lower-cased effective titles used by more than one record of the same type. */
    public static function duplicateTitles(string $modelClass): array
    {
        return $modelClass::query()
            ->get(['name', 'meta_title'])
            ->map(fn ($r) => Str::lower(trim($r->meta_title ?: $r->name)))
            ->countBy()
            ->filter(fn ($n) => $n > 1)
            ->all();
    }

    public static function wordCount(?string $html): int
    {
        return str_word_count(strip_tags(html_entity_decode((string) $html)));
    }

    public static function overview(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            $items = [];

            $dup = self::duplicateTitles(ServiceDetail::class);
            foreach (ServiceDetail::withCount(['prices', 'faqs'])->get() as $s) {
                $items[] = self::row('service', $s->id, $s->name, '/admin/services?edit=' . $s->id, self::service($s, $dup));
            }

            foreach (ServiceCategory::withCount('services')->get() as $cat) {
                $items[] = self::row('category', $cat->id, $cat->name, '/admin/service-categories?edit=' . $cat->id, self::category($cat));
            }

            foreach (Location::withCount('faqs')->get() as $l) {
                $items[] = self::row('location', $l->id, $l->name, '/admin/locations?edit=' . $l->id, self::location($l));
            }

            $dup = self::duplicateTitles(BlogDetail::class);
            BlogDetail::query()
                ->select(['id', 'name', 'meta_title', 'meta_desc', 'desc', 'image', 'noindex', 'primary_service_id'])
                ->chunkById(200, function ($blogs) use (&$items, $dup) {
                    foreach ($blogs as $b) {
                        $items[] = self::row('article', $b->id, $b->name, '/admin/blogs?edit=' . $b->id, self::article($b, $dup));
                    }
                });

            usort($items, fn ($a, $b) => $a['score'] <=> $b['score']);

            $byType = [];
            $byCode = [];
            foreach ($items as $it) {
                $t = &$byType[$it['type']];
                $t['total'] = ($t['total'] ?? 0) + 1;
                $t['score_sum'] = ($t['score_sum'] ?? 0) + $it['score'];
                $t['with_errors'] = ($t['with_errors'] ?? 0) + ($it['errors'] > 0 ? 1 : 0);
                $t['clean'] = ($t['clean'] ?? 0) + (empty($it['issues']) ? 1 : 0);
                unset($t);
                foreach ($it['issues'] as $issue) {
                    $key = $it['type'] . ':' . $issue['code'];
                    $byCode[$key] ??= ['type' => $it['type'], 'code' => $issue['code'], 'level' => $issue['level'], 'message' => $issue['message'], 'count' => 0];
                    $byCode[$key]['count']++;
                }
            }
            foreach ($byType as $type => $t) {
                $byType[$type]['avg_score'] = (int) round($t['score_sum'] / max(1, $t['total']));
                unset($byType[$type]['score_sum']);
            }
            $byCode = array_values($byCode);
            usort($byCode, fn ($a, $b) => [$a['level'] === 'error' ? 0 : 1, -$a['count']] <=> [$b['level'] === 'error' ? 0 : 1, -$b['count']]);

            return [
                'generated_at' => now()->toIso8601String(),
                'by_type' => $byType,
                'by_code' => $byCode,
                'items' => $items,
            ];
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('site.navigation');
    }

    private static function common(string $name, ?string $metaTitle, ?string $metaDesc, ?string $body, ?string $image, ?bool $noindex, array $dupTitles, int $minWords): array
    {
        $c = config('seo.audit');
        $issues = [];

        if (blank($metaTitle)) {
            $issues[] = self::warn('meta_title_missing', 'No meta title (the site template is used).');
        } elseif (mb_strlen($metaTitle) > $c['title_max']) {
            $issues[] = self::warn('meta_title_long', 'Meta title over ' . $c['title_max'] . ' characters (' . mb_strlen($metaTitle) . ').');
        }

        if (blank($metaDesc)) {
            $issues[] = self::warn('meta_desc_missing', 'No meta description.');
        } elseif (mb_strlen($metaDesc) > $c['desc_max']) {
            $issues[] = self::warn('meta_desc_long', 'Meta description over ' . $c['desc_max'] . ' characters (' . mb_strlen($metaDesc) . ').');
        } elseif (mb_strlen($metaDesc) < $c['desc_min']) {
            $issues[] = self::warn('meta_desc_short', 'Meta description under ' . $c['desc_min'] . ' characters.');
        }

        if (isset($dupTitles[Str::lower(trim($metaTitle ?: $name))])) {
            $issues[] = self::error('duplicate_title', 'Same title as another page (keyword cannibalisation).');
        }

        $words = self::wordCount($body);
        if ($words < $minWords) {
            $issues[] = self::error('thin_content', "Thin content: {$words} words (minimum {$minWords}).");
        }

        if (blank($image)) {
            $issues[] = self::warn('no_image', 'No image.');
        }

        if ($noindex) {
            $issues[] = self::warn('noindex', 'Set to noindex: hidden from Google.');
        }

        return $issues;
    }

    private static function row(string $type, int $id, string $name, string $editUrl, array $result): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'edit_url' => $editUrl,
            'score' => $result['score'],
            'errors' => $result['errors'],
            'issues' => $result['issues'],
        ];
    }

    private static function result(array $issues): array
    {
        $errors = count(array_filter($issues, fn ($i) => $i['level'] === 'error'));
        $warnings = count($issues) - $errors;

        return [
            'score' => max(0, 100 - $errors * 20 - $warnings * 7),
            'errors' => $errors,
            'issues' => $issues,
        ];
    }

    private static function error(string $code, string $message): array
    {
        return ['level' => 'error', 'code' => $code, 'message' => $message];
    }

    private static function warn(string $code, string $message): array
    {
        return ['level' => 'warning', 'code' => $code, 'message' => $message];
    }
}
