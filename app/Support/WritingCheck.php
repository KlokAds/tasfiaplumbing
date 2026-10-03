<?php

namespace App\Support;

use App\Models\BlogDetail;

/**
 * Free checks of an article sent for approval (no outside service):
 * - AI-style phrases ("delve into", "in today's fast-paced world" …) that make text read as generated;
 * - duplicate text: how much of it is the same as another article on this site;
 * - pasted text: how much of the text was pasted into the editor rather than typed.
 * The result is stored on the article or change (quality_check) and shown to approvers.
 * The editor shows the same phrase list while writing (resources/js/Pages/Admin/Blogs/Index.vue, AI_PHRASES).
 */
class WritingCheck
{
    public const AI_PHRASES = [
        "in today's fast-paced world", "in today's world", "in today's digital age", 'in the ever-evolving', 'ever-changing landscape',
        'delve into', 'delves into', 'delve deeper', "let's dive", 'dive into the world', 'dive deep into',
        "it's important to note", 'it is important to note', "it's worth noting", 'it is worth noting', "it's crucial to",
        'plays a crucial role', 'plays a vital role', 'plays a pivotal role', 'a testament to', 'stands as a testament',
        'navigate the complexities', 'navigating the world of', 'unlock the potential', 'unlock the secrets', 'unleash the',
        'elevate your', 'look no further', 'game-changer', 'game changer', 'a myriad of', 'a plethora of', 'rich tapestry',
        'embark on a journey', 'embark on your', 'in the realm of', 'the world of home', 'seamlessly integrate',
        'whether you are a seasoned', "whether you're a seasoned", 'in conclusion,', 'to sum up,', 'all in all,',
        'when it comes to the world of', 'cutting-edge', 'state-of-the-art', 'second to none', 'peace of mind knowing',
        'we hope this guide', 'we hope this article', 'this comprehensive guide', 'ultimate guide',
    ];

    /** A share of the text that matches another article from which it is reported. */
    private const DUPLICATE_MIN = 15;

    /** Words per compared chunk. */
    private const SHINGLE = 8;

    /**
     * @return array{checked_at: string, ai_phrases: list<string>, duplicate: ?array{percent: int, id: int, name: string, path: string}, pasted: array{chars: int, percent: int}}
     */
    public static function run(string $html, ?int $articleId, int $pastedChars): array
    {
        return [
            'checked_at' => now()->toIso8601String(),
            'ai_phrases' => self::aiPhrases($html),
            'duplicate' => self::duplicate($html, $articleId),
            'pasted' => ['chars' => $pastedChars, 'percent' => self::pastedPercent($pastedChars, $html)],
        ];
    }

    /** @return list<string> the AI-style phrases found (at most 10) */
    public static function aiPhrases(string $html): array
    {
        $text = mb_strtolower(str_replace(['’', '‘'], "'", self::plain($html)));
        $found = [];
        foreach (self::AI_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                $found[] = rtrim($phrase, ',');
            }
        }

        return array_slice(array_values(array_unique($found)), 0, 10);
    }

    /** The other article on this site that shares the most text with this one, when it is DUPLICATE_MIN % or more. */
    public static function duplicate(string $html, ?int $articleId): ?array
    {
        $mine = self::shingles($html);
        if (count($mine) < 20) {
            return null;
        }

        $best = null;
        BlogDetail::query()->whereKeyNot((int) $articleId)->whereIn('status', [BlogDetail::PUBLISHED, BlogDetail::SCHEDULED, BlogDetail::PENDING])
            ->select(['id', 'name', 'slug', 'desc'])->chunkById(100, function ($articles) use ($mine, &$best) {
                foreach ($articles as $a) {
                    $shared = count(array_intersect_key($mine, self::shingles((string) $a->desc)));
                    if ($shared && (!$best || $shared > $best[0])) {
                        $best = [$shared, $a];
                    }
                }
            });

        if (!$best) {
            return null;
        }
        $percent = (int) round(100 * $best[0] / count($mine));

        return $percent >= self::DUPLICATE_MIN
            ? ['percent' => $percent, 'id' => $best[1]->id, 'name' => (string) $best[1]->name, 'path' => $best[1]->publicPath()]
            : null;
    }

    public static function pastedPercent(int $pastedChars, string $html): int
    {
        $length = mb_strlen(preg_replace('/\s+/u', '', self::plain($html)));

        return $length ? (int) min(100, round(100 * $pastedChars / max($length, 1))) : 0;
    }

    /**
     * New articles need the "What we see on real jobs" section (first-hand experience, E-E-A-T)
     * with at least 25 words of real detail. Turned off with config admin.require_job_notes.
     */
    public static function jobNotesMissing(string $html): bool
    {
        if (!config('admin.require_job_notes', true)) {
            return false;
        }
        if (!preg_match('#<h2[^>]*>\s*(?:<[^>]+>\s*)*What we see on real jobs.*?</h2>(.*?)(?=<h2|$)#is', $html, $m)) {
            return true;
        }

        return str_word_count(self::plain($m[1])) < 25;
    }

    /** Issue lines for the approval email (same wording as the review queue). */
    public static function issues(?array $check): array
    {
        if (!$check) {
            return [];
        }
        $out = [];
        if (!empty($check['ai_phrases'])) {
            $out[] = 'Writing: AI-style phrases: “' . implode('”, “', array_slice($check['ai_phrases'], 0, 5)) . '”. Ask for plain, first-hand wording.';
        }
        if (!empty($check['duplicate'])) {
            $out[] = "Writing: {$check['duplicate']['percent']}% of the text is the same as “{$check['duplicate']['name']}” on this site. Two pages with the same text compete in Google.";
        }
        if (($check['pasted']['percent'] ?? 0) >= 60) {
            $out[] = "Writing: {$check['pasted']['percent']}% of the text was pasted into the editor. Check that it was written for this site.";
        }

        return $out;
    }

    private static function plain(string $html): string
    {
        $text = preg_replace('#</(p|h[1-6]|li|tr|blockquote|div)>|<br\s*/?>#i', "\n", $html);

        return html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Chunks of SHINGLE words (as keys), to compare two texts. */
    private static function shingles(string $html): array
    {
        $words = preg_split('/[^a-z0-9$]+/', mb_strtolower(self::plain($html)), -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        for ($i = 0, $n = count($words) - self::SHINGLE; $i <= $n; $i++) {
            $out[crc32(implode(' ', array_slice($words, $i, self::SHINGLE)))] = true;
        }

        return $out;
    }
}
