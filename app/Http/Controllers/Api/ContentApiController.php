<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogDetail;
use App\Models\Price;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\SeoAudit;
use App\Support\WritingCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The content hub (the company's billing app): reads services, approved prices and the list of articles,
 * and manages the articles it made itself (source "hub"): add as draft, edit, publish or schedule,
 * unpublish, delete. Any other article can only be revised in place (same address) with a backup taken
 * first, and put back from that backup.
 */
class ContentApiController extends Controller
{
    private const ALLOWED_TAGS = '<h2><h3><p><ul><ol><li><strong><em><br><table><thead><tbody><tr><th><td>';

    /** Brand, live services with approved prices, the searches people use, and the phrases articles avoid. */
    public function info()
    {
        $prices = Price::where('is_active', true)->orderBy('sort_order')->get()->groupBy('service_id');
        $queries = [];
        try {
            $queries = collect(\App\Support\Google\SearchConsole::report()['queries'] ?? [])->pluck('key')->take(40)->values()->all();
        } catch (\Throwable) {
            // Search Console is optional.
        }

        return response()->json([
            'brand' => SiteSetting::get('business.brand_name') ?: config('app.name'),
            'url' => rtrim((string) config('app.url'), '/'),
            'services' => ServiceDetail::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'path' => $s->publicPath(),
                'prices' => ($prices[$s->id] ?? collect())->map(fn ($p) => ['item' => $p->item, 'from' => $p->price_from, 'to' => $p->price_to, 'unit' => $p->unit])->values(),
            ]),
            'searches' => $queries,
            'articles' => BlogDetail::whereIn('status', [BlogDetail::PUBLISHED, BlogDetail::SCHEDULED])->orderByDesc('published_at')->limit(300)
                ->get(['id', 'name', 'slug', 'focus_keyword', 'excerpt', 'primary_service_id', 'source', 'status', 'published_at', 'scheduled_at'])->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->name,
                    'path' => $b->publicPath(),
                    'keyword' => $b->focus_keyword,
                    'excerpt' => Str::limit(strip_tags((string) $b->excerpt), 160),
                    'service_id' => $b->primary_service_id,
                    'status' => $b->status,
                    'live_at' => ($b->status === BlogDetail::SCHEDULED ? $b->scheduled_at : $b->published_at)?->toIso8601String(),
                ]),
            'avoid_phrases' => WritingCheck::AI_PHRASES,
        ]);
    }

    /** Saves an article as a draft in Articles, with its photos. */
    public function storeDraft(Request $request)
    {
        $data = $this->validated($request, true);

        $paths = $this->savePhotos($request);
        $body = $this->withPhotos($this->clean($data['body_html']), $request, $data, true, $paths);

        $title = trim($data['title']);
        $slug = Str::slug($title);
        if (BlogDetail::where('slug', $slug)->exists()) {
            $slug .= '-' . now()->format('ymd-Hi');
        }
        $author = $this->author();

        $blog = new BlogDetail([
            'name' => $title,
            'slug' => $slug,
            'desc' => $body,
            'excerpt' => $data['excerpt'] ?? null,
            'meta_title' => $title,
            'meta_desc' => $data['meta_desc'] ?? null,
            'focus_keyword' => $data['focus_keyword'] ?? null,
            'primary_service_id' => $data['service_id'] ?? null,
            'image' => $paths[0] ?? null,
            'status' => BlogDetail::DRAFT,
            'auth_name' => $author?->name ?? 'Team',
            'btn_name' => 'Read More',
        ]);
        $blog->author_id = $author?->id;
        $blog->source = 'hub';
        $blog->review_note = 'Sent from the content hub (written by AI from job photos). Check every fact before publishing.';
        $blog->save();
        $blog->syncFaqs($data['faqs'] ?? []);

        return response()->json($this->state($blog), 201);
    }

    public function show(int $id)
    {
        return response()->json($this->state($this->hubArticle($id)));
    }

    /** Changes the text of an article the hub made. Its photos stay where they are. */
    public function update(Request $request, int $id)
    {
        $blog = $this->hubArticle($id);
        $data = $this->validated($request, false);

        $blog->fill([
            'name' => trim($data['title']),
            'meta_title' => trim($data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'meta_desc' => $data['meta_desc'] ?? null,
            'focus_keyword' => $data['focus_keyword'] ?? null,
            'primary_service_id' => $data['service_id'] ?? null,
        ]);
        // Keeps the photos already in the article; the hub sends the text without them.
        $figures = preg_match_all('#<figure>.*?</figure>#is', (string) $blog->desc, $m) ? implode("\n", $m[0]) : '';
        $blog->desc = $this->clean($data['body_html']) . ($figures !== '' ? "\n" . $figures : '');
        if ($blog->status === BlogDetail::PUBLISHED) {
            $blog->content_updated_at = now();
        }
        $blog->save();
        $blog->syncFaqs($data['faqs'] ?? []);
        SeoAudit::flush();

        return response()->json($this->state($blog));
    }

    /**
     * The text of any article, for the hub to improve. Photos, tables and videos are replaced by
     * [[keep-N]] lines so they come back unchanged when the article is revised.
     */
    public function text(int $id)
    {
        $blog = BlogDetail::findOrFail($id);
        [$body, $kept] = $this->protect((string) $blog->desc);

        return response()->json($this->state($blog) + [
            'title' => $blog->name,
            'excerpt' => $blog->excerpt,
            'meta_desc' => $blog->meta_desc,
            'focus_keyword' => $blog->focus_keyword,
            'body_html' => $body,
            'kept' => count($kept),
            'faqs' => $blog->faqs()->get(['question', 'answer'])->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer])->values(),
            'source' => $blog->source,
        ]);
    }

    /**
     * Revises any article in place: same address, same status. The whole article is saved to a backup
     * file first, the [[keep-N]] photos and tables go back where they were, and new job photos are added.
     */
    public function revise(Request $request, int $id)
    {
        $blog = BlogDetail::findOrFail($id);
        $data = $this->validated($request, true);
        $backup = $this->backup($blog);

        [, $kept] = $this->protect((string) $blog->desc);
        $links = preg_match_all('#<a\b[^>]*href="([^"]+)"#i', (string) $blog->desc, $m) ? $m[1] : [];
        $body = $this->clean($data['body_html'], $links);
        foreach ($kept as $i => $block) {
            $token = '[[keep-' . ($i + 1) . ']]';
            $body = str_contains($body, $token)
                ? preg_replace('#<p>\s*' . preg_quote($token, '#') . '\s*</p>|' . preg_quote($token, '#') . '#', str_replace(['\\', '$'], ['\\\\', '\\$'], $block), $body, 1)
                : $body . "\n" . $block; // dropped by the writer: kept at the end
        }
        $body = $this->withPhotos($body, $request, $data, false);

        if ($blog->meta_title === null || $blog->meta_title === $blog->name) {
            $blog->meta_title = trim($data['title']);
        }
        $blog->fill([
            'name' => trim($data['title']),
            'excerpt' => $data['excerpt'] ?? $blog->excerpt,
            'meta_desc' => $data['meta_desc'] ?? $blog->meta_desc,
            'focus_keyword' => $data['focus_keyword'] ?? $blog->focus_keyword,
        ]);
        $blog->desc = $body;
        if ($blog->status === BlogDetail::PUBLISHED) {
            $blog->content_updated_at = now();
        }
        $blog->save();
        if (!empty($data['faqs'])) {
            $blog->syncFaqs($data['faqs']);
        }
        SeoAudit::flush();

        return response()->json($this->state($blog) + ['backup' => $backup]);
    }

    /** Puts an article back exactly as it was in one of its backups. */
    public function restore(Request $request, int $id)
    {
        $blog = BlogDetail::findOrFail($id);
        $data = $request->validate(['backup' => ['required', 'string', 'regex:/^blog-\d+-\d{8}-\d{6}(-\d+)?\.json$/']]);
        abort_unless(str_starts_with($data['backup'], "blog-{$blog->id}-") && Storage::disk('local')->exists('content-backups/' . $data['backup']), 404);

        $saved = json_decode((string) Storage::disk('local')->get('content-backups/' . $data['backup']), true);
        $this->backup($blog); // the current text too, so nothing is lost
        foreach (['name', 'excerpt', 'meta_title', 'meta_desc', 'focus_keyword', 'desc', 'content_updated_at'] as $field) {
            $blog->{$field} = $saved['article'][$field] ?? null;
        }
        $blog->save();
        $blog->syncFaqs($saved['faqs'] ?? []);
        SeoAudit::flush();

        return response()->json($this->state($blog));
    }

    /** Publishes now, or at a later time (the site's scheduler puts it live then). */
    public function publish(Request $request, int $id)
    {
        $blog = $this->hubArticle($id);
        $data = $request->validate(['at' => 'nullable|date|after:now']);
        $author = $this->author();

        $blog->scheduled_at = !empty($data['at']) ? \Illuminate\Support\Carbon::parse($data['at']) : null;
        $blog->status = $blog->scheduled_at ? BlogDetail::SCHEDULED : BlogDetail::PUBLISHED;
        $blog->reviewed_by = $author?->id;
        $blog->reviewed_at = now();
        $blog->review_note = null;
        $blog->save();
        SeoAudit::flush();

        return response()->json($this->state($blog));
    }

    public function unpublish(int $id)
    {
        $blog = $this->hubArticle($id);
        $blog->status = BlogDetail::DRAFT;
        $blog->scheduled_at = null;
        $blog->save();
        SeoAudit::flush();

        return response()->json($this->state($blog));
    }

    /** Deletes an article the hub made (its text stays in the hub). A live one then answers 410 Gone. */
    public function destroy(int $id)
    {
        $blog = $this->hubArticle($id);
        $wasLive = $blog->status === BlogDetail::PUBLISHED;
        $path = $blog->publicPath();
        $blog->faqs()->delete();
        $blog->delete();
        SeoAudit::flush();

        return response()->json(['deleted' => true, 'was_live' => $wasLive, 'path' => $path]);
    }

    /** @return list<string> paths of the saved job photos */
    private function savePhotos(Request $request): array
    {
        $paths = [];
        foreach ($request->file('photos', []) as $file) {
            $name = 'job-' . now()->format('ymd-His') . '-' . Str::lower(Str::random(6)) . '.' . (in_array($file->extension(), ['png', 'webp'], true) ? $file->extension() : 'jpg');
            $paths[] = $file->storeAs('Admin/Blog/Details', $name, 'uploads');
        }

        return $paths;
    }

    /**
     * Puts job photos below the first section, so the article opens with the answer. A new article
     * uses its first photo as the cover; a revised one keeps its cover and gets all photos in the text.
     */
    private function withPhotos(string $body, Request $request, array $data, bool $coverFirst, ?array $paths = null): string
    {
        $paths ??= $this->savePhotos($request);
        $alts = array_values($data['alts'] ?? []);
        $start = $coverFirst ? 1 : 0;
        $figures = collect($paths)->slice($start)->values()->map(fn ($p, $i) => '<figure><img src="/' . e($p) . '" alt="' . e($alts[$i + $start] ?? $data['title']) . '"></figure>')->implode("\n");
        if ($figures === '') {
            return $body;
        }
        $first = stripos($body, '<h2');
        $second = $first === false ? false : stripos($body, '<h2', $first + 3);
        $at = $second === false ? strlen($body) : $second;

        return substr($body, 0, $at) . $figures . substr($body, $at);
    }

    /** @return array{0: string, 1: list<string>} the body with photos, tables and videos as [[keep-N]] lines, and those blocks */
    private function protect(string $html): array
    {
        $kept = [];
        $body = preg_replace_callback('#<figure\b.*?</figure>|<table\b.*?</table>|<iframe\b.*?</iframe>|<video\b.*?</video>|<picture\b.*?</picture>|<img\b[^>]*>#is', function ($m) use (&$kept) {
            $kept[] = $m[0];

            return '<p>[[keep-' . count($kept) . ']]</p>';
        }, $html);

        return [$body, $kept];
    }

    /** Saves the whole article (text, FAQs, settings) before the hub changes it. */
    private function backup(BlogDetail $blog): string
    {
        $name = 'blog-' . $blog->id . '-' . now()->format('Ymd-His');
        $file = $name . '.json';
        for ($i = 1; Storage::disk('local')->exists('content-backups/' . $file); $i++) {
            $file = "{$name}-{$i}.json";
        }
        Storage::disk('local')->put('content-backups/' . $file, json_encode([
            'saved_at' => now()->toIso8601String(),
            'article' => $blog->getAttributes(),
            'faqs' => $blog->faqs()->get(['question', 'answer'])->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer])->values(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $file;
    }

    private function hubArticle(int $id): BlogDetail
    {
        return BlogDetail::where('source', 'hub')->findOrFail($id);
    }

    private function author(): ?User
    {
        return User::where('email', config('content_api.author_email', 'mujxaid@gmail.com'))->first()
            ?? User::role('super-admin')->orderBy('id')->first();
    }

    private function validated(Request $request, bool $withPhotos): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'meta_desc' => 'nullable|string|max:500',
            'focus_keyword' => 'nullable|string|max:120',
            'body_html' => 'required|string|max:60000',
            'service_id' => 'nullable|exists:service_details,id',
            'faqs' => 'nullable|array|max:10',
            'faqs.*.question' => 'required|string|max:500',
            'faqs.*.answer' => 'required|string|max:3000',
        ] + ($withPhotos ? [
            'photos' => 'nullable|array|max:8',
            'photos.*' => 'image|mimes:jpeg,png,webp|max:6144',
            'alts' => 'nullable|array|max:8',
            'alts.*' => 'nullable|string|max:200',
        ] : []));
    }

    /**
     * Only plain article markup, without attributes. Links stay only when they point to a page of this
     * website (a service or a live article), or were already in the article ($keep).
     */
    private function clean(string $html, array $keep = []): string
    {
        $html = strip_tags($html, self::ALLOWED_TAGS . '<a>');
        $html = preg_replace('/<(h2|h3|p|ul|ol|li|strong|em|table|thead|tbody|tr|th|td)\b[^>]*>/i', '<$1>', $html);

        $internal = null;
        return preg_replace_callback('#<a\b([^>]*)>(.*?)</a>#is', function ($m) use ($keep, &$internal) {
            $href = preg_match('#href\s*=\s*"([^"]*)"#i', $m[1], $h) ? html_entity_decode($h[1]) : '';
            $internal ??= ServiceDetail::where('is_active', true)->get(['slug'])->map(fn ($s) => $s->publicPath())
                ->merge(BlogDetail::where('status', BlogDetail::PUBLISHED)->get(['slug'])->map(fn ($b) => $b->publicPath()))->flip();
            $path = rtrim((string) parse_url($href, PHP_URL_PATH), '/');
            $ours = str_starts_with($href, '/') && !str_starts_with($href, '//') && isset($internal[$path]);

            return $ours || in_array($href, $keep, true) ? '<a href="' . e($href) . '">' . $m[2] . '</a>' : $m[2];
        }, $html);
    }

    private function state(BlogDetail $blog): array
    {
        return [
            'id' => $blog->id,
            'status' => $blog->status,
            'scheduled_at' => $blog->scheduled_at?->toIso8601String(),
            'published_at' => $blog->published_at?->toIso8601String(),
            'url' => url($blog->publicPath()),
            'edit_url' => url('/admin/blogs?tab=mine&edit=' . $blog->id),
        ];
    }
}
