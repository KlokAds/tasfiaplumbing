<?php

namespace App\Support;

/**
 * Left-over template text that must never go live: [MIN], [MAX], [Insert …], S$XX, XX hours,
 * Lorem ipsum, TBD. Usually the sign of a generated draft that was pasted without real details.
 * The editor shows the same list (resources/js/Pages/Admin/Blogs/Index.vue, TEMPLATE_PATTERNS).
 */
class TemplateText
{
    private const PATTERNS = [
        '/\[[A-Z][A-Z0-9 _\/&-]{0,30}\]/',                                                         // [MIN], [MAX], [AREA]
        '/\[(?:insert|your|add|enter|put|name|area|price|number|phone|date|location|company|brand|city)\b[^\]]{0,60}\]/i',
        '/S\$\s?X{1,4}\b/i',                                                                        // S$X, S$XX
        '/\bX{1,3}(?:\s?[-–]\s?X{1,3})?\s?(?:hours?|hrs?|mins?|minutes?|days?|weeks?|months?|years?|%)/', // XX hours, X–X days
        '/lorem ipsum/i',
        '/\b(?:TBD|TODO|TBC)\b/',
    ];

    /** The template bits found in the texts, e.g. ["[MIN]", "S$XX"] (at most 6). */
    public static function find(?string ...$texts): array
    {
        $plain = html_entity_decode(strip_tags(implode("\n", array_map('strval', $texts))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $found = [];
        foreach (self::PATTERNS as $pattern) {
            if (preg_match_all($pattern, $plain, $m)) {
                array_push($found, ...$m[0]);
            }
        }

        return array_slice(array_values(array_unique(array_map('trim', $found))), 0, 6);
    }

    /** Every text of an article (its fields and FAQs) from saved data or a change waiting for approval. */
    public static function inArticle(array $a): array
    {
        $faqs = collect($a['faqs'] ?? [])->flatMap(fn ($f) => [(string) ($f['question'] ?? ''), (string) ($f['answer'] ?? '')])->all();

        return self::find($a['name'] ?? '', $a['excerpt'] ?? '', $a['desc'] ?? '', $a['meta_title'] ?? '', $a['meta_desc'] ?? '', ...$faqs);
    }

    public static function message(array $found): string
    {
        return 'Template text left in the article: ' . implode(', ', $found) . '. Put in the real details (prices, times, places) before it goes for approval or live.';
    }
}
