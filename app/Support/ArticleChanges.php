<?php

namespace App\Support;

use App\Models\BlogDetail;
use Illuminate\Support\Str;

/**
 * What a writer's change does to a live article, for the approval email: the fields that change
 * (before and now) and the sentences added and removed in the text.
 */
class ArticleChanges
{
    private const FIELDS = [
        'name' => 'Title',
        'slug' => 'URL',
        'excerpt' => 'Direct answer',
        'meta_title' => 'Meta title',
        'meta_desc' => 'Meta description',
        'focus_keyword' => 'Focus keyword',
        'primary_service_id' => 'Service',
        'canonical' => 'Canonical',
        'noindex' => 'Hidden from Google',
        'image' => 'Featured image',
    ];

    /** Sentences listed per side in the email; the rest are counted. */
    private const SHOW = 3;

    /**
     * @return array{fields: list<array{label: string, old: string, new: string}>, added: list<string>, removed: list<string>,
     *               more_added: int, more_removed: int, words: array{0: int, 1: int}}|null
     */
    public static function between(BlogDetail $live, array $payload): ?array
    {
        $fields = [];
        foreach (self::FIELDS as $key => $label) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            [$old, $new] = [self::show($key, $live->getAttribute($key)), self::show($key, $payload[$key])];
            if ($old !== $new) {
                $fields[] = ['label' => $label, 'old' => $old, 'new' => $new];
            }
        }

        $faqOld = $live->faqs()->get(['question', 'answer'])->map(fn ($f) => trim($f->question) . '|' . trim($f->answer))->all();
        $faqNew = collect($payload['faqs'] ?? [])->filter(fn ($f) => trim($f['question'] ?? '') !== '' && trim($f['answer'] ?? '') !== '')
            ->map(fn ($f) => trim($f['question']) . '|' . trim($f['answer']))->values()->all();
        if (array_key_exists('faqs', $payload) && $faqOld !== $faqNew) {
            $fields[] = ['label' => 'FAQs', 'old' => count($faqOld) . ' questions', 'new' => count($faqNew) . ' questions'
                . (count($faqOld) === count($faqNew) ? ' (edited)' : '')];
        }

        $oldText = self::sentences((string) $live->desc);
        $newText = self::sentences((string) ($payload['desc'] ?? $live->desc));
        $added = array_values(array_diff($newText, $oldText));
        $removed = array_values(array_diff($oldText, $newText));

        if (!$fields && !$added && !$removed) {
            return null;
        }

        return [
            'fields' => $fields,
            'added' => array_slice($added, 0, self::SHOW),
            'removed' => array_slice($removed, 0, self::SHOW),
            'more_added' => max(0, count($added) - self::SHOW),
            'more_removed' => max(0, count($removed) - self::SHOW),
            'words' => [SeoAudit::wordCount((string) $live->desc), SeoAudit::wordCount((string) ($payload['desc'] ?? $live->desc))],
        ];
    }

    private static function show(string $key, mixed $value): string
    {
        if ($key === 'primary_service_id') {
            return $value ? (string) (\App\Models\ServiceDetail::whereKey($value)->value('name') ?? '—') : '—';
        }
        if ($key === 'noindex') {
            return $value ? 'Yes' : 'No';
        }
        if ($key === 'slug') {
            return $value ? '/blogs/' . $value : '—';
        }
        if ($key === 'image') {
            return $value ? basename((string) $value) : '—';
        }
        $value = trim((string) $value);

        return $value === '' ? '—' : Str::limit($value, 120);
    }

    /** The text as sentences (headings and list items count as one), for comparing. */
    private static function sentences(string $html): array
    {
        $text = preg_replace('#</(p|h[1-6]|li|tr|blockquote)>|<br\s*/?>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $out = [];
        foreach (preg_split('/\n+/', $text) as $block) {
            foreach (preg_split('/(?<=[.!?])\s+(?=[A-Z0-9“"(])/u', trim(preg_replace('/\s+/u', ' ', $block))) as $s) {
                $s = trim($s);
                if (mb_strlen($s) >= 3) {
                    $out[] = Str::limit($s, 140);
                }
            }
        }

        return array_values(array_unique($out));
    }
}
