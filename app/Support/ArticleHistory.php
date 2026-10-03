<?php

namespace App\Support;

use App\Models\ArticleVersion;
use App\Models\BlogDetail;
use App\Models\User;

/**
 * Keeps the live versions of articles. Before the live text changes, take a snapshot(); after
 * saving, record() stores it as a version if anything really changed. Restoring a version
 * keeps the text it replaces too, so nothing is ever lost.
 */
class ArticleHistory
{
    /** What a version holds: everything shown on the page and to search engines, and the FAQs. */
    public const FIELDS = [
        'name', 'slug', 'primary_service_id', 'excerpt', 'desc', 'focus_keyword',
        'meta_title', 'meta_desc', 'canonical', 'noindex', 'image',
    ];

    public static function snapshot(BlogDetail $blog): array
    {
        return collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => $blog->getAttribute($f)])->all()
            + ['faqs' => $blog->faqs()->orderBy('sort_order')->get(['question', 'answer'])->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer])->all()];
    }

    /** Store $before as a version when the article now differs from it. */
    public static function record(BlogDetail $blog, ?array $before, ?User $by, string $event): ?ArticleVersion
    {
        if ($before === null || self::same($before, self::snapshot($blog->fresh()))) {
            return null;
        }

        return ArticleVersion::create(['article_id' => $blog->id, 'user_id' => $by?->id, 'event' => $event, 'payload' => $before]);
    }

    private static function same(array $a, array $b): bool
    {
        $norm = fn ($v) => json_encode(collect($v)->map(fn ($x) => is_bool($x) ? (int) $x : $x)->all());

        return $norm($a) === $norm($b);
    }
}
