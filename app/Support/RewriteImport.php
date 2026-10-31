<?php

namespace App\Support;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Rewritten articles made outside the site (tools/rewrite_blogs.py in the handyman repo) arrive as
 * JSON-lines files in public/rewrite-imports. Each line becomes a pending change to the live article,
 * so nothing changes on the site until an approver presses "Approve changes" (no automatic approval).
 * The change's note holds a checklist, so the approver sees at a glance what to look at.
 * An article that already had an imported rewrite (waiting, approved or rejected) is not imported again.
 */
class RewriteImport
{
    public const MARK = 'AI rewrite';

    public static function folder(): string
    {
        return public_path('rewrite-imports');
    }

    /** @return array{imported: int, skipped: int, files: int} */
    public static function run(): array
    {
        $dir = self::folder();
        $result = ['imported' => 0, 'skipped' => 0, 'files' => 0];
        if (!is_dir($dir)) {
            return $result;
        }
        // The folder is under the web root: keep it closed to visitors.
        if (!is_file("$dir/.htaccess")) {
            File::put("$dir/.htaccess", "Require all denied\n");
        }
        foreach (glob("$dir/*.jsonl") ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $row = json_decode($line, true);
                is_array($row) && self::import($row) ? $result['imported']++ : $result['skipped']++;
            }
            File::delete($file);
            $result['files']++;
        }
        if ($result['files']) {
            Log::info('Rewrites imported', $result);
        }

        return $result;
    }

    public static function import(array $row): bool
    {
        $payload = (array) ($row['payload'] ?? []);
        $desc = trim((string) ($payload['desc'] ?? ''));
        $blog = BlogDetail::where('slug', (string) ($row['slug'] ?? ''))->first()
            ?? BlogDetail::where('name', (string) ($row['name'] ?? ''))->first();
        if ($desc === '' || !$blog || $blog->status !== BlogDetail::PUBLISHED) {
            return false;
        }
        if (ArticleRevision::where('article_id', $blog->id)->where('note', 'like', self::MARK . '%')->exists()) {
            return false;
        }

        $faqs = collect($payload['faqs'] ?? [])
            ->map(fn ($f) => ['question' => trim((string) ($f['question'] ?? '')), 'answer' => trim((string) ($f['answer'] ?? ''))])
            ->filter(fn ($f) => $f['question'] !== '' && $f['answer'] !== '')->values()->all();
        $change = [
            'name' => $blog->name,
            'desc' => $desc,
            'excerpt' => mb_substr(trim((string) ($payload['excerpt'] ?? '')), 0, 160) ?: $blog->excerpt,
            'meta_desc' => mb_substr(trim((string) ($payload['meta_desc'] ?? '')), 0, 160) ?: $blog->meta_desc,
            'faqs' => $faqs,
        ];

        $revision = ArticleRevision::create([
            'article_id' => $blog->id,
            'user_id' => null,
            'payload' => $change,
            'status' => 'pending',
            'note' => self::note($blog, $change, (string) ($row['note'] ?? '')),
        ]);
        $revision->forceFill(['quality_check' => WritingCheck::run($desc, $blog->id), 'auto_approve_at' => null])->saveQuietly();

        return true;
    }

    /** "AI rewrite: what changed" and one line per check, ✓ or ✗. */
    public static function note(BlogDetail $blog, array $change, string $summary): string
    {
        $lines = [self::MARK . ($summary !== '' ? ': ' . $summary : '')];
        foreach (self::checklist($blog, $change) as $label => $ok) {
            $lines[] = ($ok ? '✓ ' : '✗ ') . $label;
        }

        return mb_substr(implode("\n", $lines), 0, 1000);
    }

    /** @return array<string, bool> */
    public static function checklist(BlogDetail $blog, array $change): array
    {
        $html = $change['desc'];
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('#<(/p|/h\d|/li|br\s*/?)>#i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));
        preg_match('#<p[^>]*>(.*?)</p>#is', $html, $first);
        $opening = trim(strip_tags($first[1] ?? ''));
        preg_match_all('#<h2[^>]*>(.*?)</h2>#is', $html, $h2);
        $questions = count(array_filter($h2[1], fn ($h) => str_ends_with(trim(strip_tags($h)), '?')));
        preg_match_all('#<img[^>]+src="([^"]+)"#i', (string) $blog->desc, $photos);
        $missing = array_filter($photos[1], fn ($src) => !str_contains($html, $src));
        $hadService = str_contains((string) $blog->desc, 'data-related-service="1"');
        $meta = mb_strlen($change['meta_desc'] ?? '');

        return [
            "Length 1200–1500 words ($words)" => $words >= 1200 && $words <= 1500,
            'Short direct answer at the start (AEO)' => $opening !== '' && count(preg_split('/\s+/u', $opening)) <= 80,
            "Question headings ($questions)" => $questions >= 2,
            'Key takeaways section (GEO)' => stripos($html, 'Key takeaways') !== false,
            'FAQs (' . count($change['faqs']) . ')' => count($change['faqs']) >= 4,
            "Meta description 130–160 characters ($meta)" => $meta >= 130 && $meta <= 160,
            'All photos kept' . ($missing ? ' (' . count($missing) . ' missing)' : '') => !$missing,
            'Service page link kept' => !$hadService || str_contains($html, 'data-related-service="1"'),
            'No prices' => !preg_match('/(S?\$\s?\d|\d+\s?(SGD|dollars))/i', $text),
            'No AI-style phrases' => !WritingCheck::aiPhrases($html),
        ];
    }
}
