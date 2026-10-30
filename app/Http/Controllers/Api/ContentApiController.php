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
use Illuminate\Validation\ValidationException;

/**
 * The content hub (the company's billing app): reads services, approved prices and the list of articles,
 * and manages the articles it made itself (source "hub"): add as draft, edit, publish or schedule,
 * unpublish, delete. Any other article can only be revised in place (same address) with a backup taken
 * first, and put back from that backup.
 *
 * Photos: with "inline" the hub places them in the text itself (<img src="[[photo-N]]"> for the Nth file
 * sent, or the address of a photo already on this website); without it they go below the first section.
 */
class ContentApiController extends Controller
{
    private const ALLOWED_TAGS = '<h2><h3><h4><h5><h6><p><ul><ol><li><strong><em><u><s><blockquote><hr><br><table><thead><tbody><tr><th><td><img>';
    private const PHOTO_PATH = '#^/[A-Za-z0-9/_\-. ()]+\.(jpe?g|png|webp|gif|avif)$#i';

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
            'blog_path' => (new BlogDetail())->publicPath(''),
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
        $inline = $request->boolean('inline');
        $body = $inline
            ? $this->clean($data['body_html'], [], $paths)
            : $this->withPhotos($this->clean($data['body_html']), $request, $data, true, $paths);

        $title = trim($data['title']);
        if (filled($data['slug'] ?? null)) {
            $slug = $this->freeSlug($data['slug']);
        } else {
            $slug = Str::slug($title);
            if (BlogDetail::where('slug', $slug)->exists()) {
                $slug .= '-' . now()->format('ymd-Hi');
            }
        }
        $author = $this->author();

        $blog = new BlogDetail([
            'name' => $title,
            'slug' => $slug,
            'desc' => $body,
            'excerpt' => $data['excerpt'] ?? null,
            'meta_title' => filled($data['meta_title'] ?? null) ? trim($data['meta_title']) : $title,
            'meta_desc' => $data['meta_desc'] ?? null,
            'focus_keyword' => $data['focus_keyword'] ?? null,
            'canonical' => $data['canonical'] ?? null,
            'noindex' => (bool) ($data['noindex'] ?? false),
            'primary_service_id' => $data['service_id'] ?? null,
            'image' => $inline ? $this->cover($data, $paths) : ($paths[0] ?? null),
            'status' => BlogDetail::DRAFT,
            'auth_name' => $author?->name ?? 'Team',
            'btn_name' => 'Read More',
        ]);
        $blog->author_id = $author?->id;
        $blog->source = 'hub';
        $blog->review_note = 'Sent from the content hub (written by AI from job photos). Check every fact before publishing.';
        $blog->save();
        $blog->syncFaqs($data['faqs'] ?? []);

        return response()->json($this->state($blog) + ['photos' => $this->urls($paths)], 201);
    }

    public function show(int $id)
    {
        return response()->json($this->state($this->hubArticle($id)));
    }

    /**
     * Changes an article the hub made. With "inline" the text carries its photos (new ones are sent as
     * files); without it the photos already in the article are kept at its end.
     */
    public function update(Request $request, int $id)
    {
        $blog = $this->hubArticle($id);
        $data = $this->validated($request, true);
        $paths = [];

        $blog->fill([
            'name' => trim($data['title']),
            'meta_title' => filled($data['meta_title'] ?? null) ? trim($data['meta_title']) : trim($data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'meta_desc' => $data['meta_desc'] ?? null,
            'focus_keyword' => $data['focus_keyword'] ?? null,
            'primary_service_id' => $data['service_id'] ?? null,
        ]);
        $this->seoFields($blog, $request, $data);
        if ($request->boolean('inline')) {
            $paths = $this->savePhotos($request);
            $blog->desc = $this->clean($data['body_html'], $this->links($blog), $paths);
            $blog->image = $this->cover($data, $paths) ?? $blog->image;
        } else {
            // Keeps the photos already in the article; the hub sends the text without them.
            $figures = preg_match_all('#<figure>.*?</figure>#is', (string) $blog->desc, $m) ? implode("\n", $m[0]) : '';
            $blog->desc = $this->clean($data['body_html']) . ($figures !== '' ? "\n" . $figures : '');
        }
        if ($blog->status === BlogDetail::PUBLISHED) {
            $blog->content_updated_at = now();
        }
        $blog->save();
        $blog->syncFaqs($data['faqs'] ?? []);
        SeoAudit::flush();

        return response()->json($this->state($blog) + ['photos' => $this->urls($paths)]);
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
            'meta_title' => $blog->meta_title,
            'slug' => $blog->slug,
            'canonical' => $blog->canonical,
            'noindex' => (bool) $blog->noindex,
            'service_id' => $blog->primary_service_id,
            'cover' => $blog->image ? url('/' . ltrim($blog->image, '/')) : null,
            'body_html' => $body,
            'kept' => count($kept),
            'kept_blocks' => array_map(fn ($block) => $this->preview($block), $kept),
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
        $inline = $request->boolean('inline');
        $paths = $inline ? $this->savePhotos($request) : [];
        $body = $this->clean($data['body_html'], $this->links($blog), $paths);
        foreach ($kept as $i => $block) {
            $token = '[[keep-' . ($i + 1) . ']]';
            $body = str_contains($body, $token)
                ? preg_replace('#<p>\s*' . preg_quote($token, '#') . '\s*</p>|' . preg_quote($token, '#') . '#', str_replace(['\\', '$'], ['\\\\', '\\$'], $block), $body, 1)
                : $body . "\n" . $block; // dropped by the writer: kept at the end
        }
        // A [[keep-N]] line with no block behind it (the article changed meanwhile) is left out.
        $body = preg_replace('#<p>\s*\[\[keep-\d+\]\]\s*</p>|\[\[keep-\d+\]\]#', '', $body);
        if (!$inline) {
            $body = $this->withPhotos($body, $request, $data, false);
        }

        if (filled($data['meta_title'] ?? null)) {
            $blog->meta_title = trim($data['meta_title']);
        } elseif ($blog->meta_title === null || $blog->meta_title === $blog->name) {
            $blog->meta_title = trim($data['title']);
        }
        $this->seoFields($blog, $request, $data, false);
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

        return response()->json($this->state($blog) + ['backup' => $backup, 'photos' => $this->urls($paths)]);
    }

    /** Puts an article back exactly as it was in one of its backups. */
    public function restore(Request $request, int $id)
    {
        $blog = BlogDetail::findOrFail($id);
        $data = $request->validate(['backup' => ['required', 'string', 'regex:/^blog-\d+-\d{8}-\d{6}(-\d+)?\.json$/']]);
        abort_unless(str_starts_with($data['backup'], "blog-{$blog->id}-") && Storage::disk('local')->exists('content-backups/' . $data['backup']), 404);

        $saved = json_decode((string) Storage::disk('local')->get('content-backups/' . $data['backup']), true);
        $this->backup($blog); // the current text too, so nothing is lost
        foreach (['name', 'excerpt', 'meta_title', 'meta_desc', 'focus_keyword', 'canonical', 'noindex', 'desc', 'content_updated_at'] as $field) {
            if ($field === 'noindex' || $field === 'canonical') {
                if (array_key_exists($field, $saved['article'] ?? [])) {
                    $blog->{$field} = $saved['article'][$field];
                }
                continue;
            }
            $blog->{$field} = $saved['article'][$field] ?? null;
        }
        $blog->save();
        $blog->syncFaqs($saved['faqs'] ?? []);
        SeoAudit::flush();

        return response()->json($this->state($blog));
    }

    /** How much a draft shares with this site's articles (and its AI-style phrases), before it is sent. */
    public function check(Request $request)
    {
        $data = $request->validate(['body_html' => 'required|string|max:60000', 'article_id' => 'nullable|integer']);
        $result = WritingCheck::run($data['body_html'], $data['article_id'] ?? null);
        if ($result['duplicate']) {
            $result['duplicate']['url'] = url($result['duplicate']['path']);
        }

        return response()->json($result);
    }

    /** The website's photo library, newest first, 40 at a time, for the hub's editor. */
    public function media(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $files = collect(\App\Support\MediaLibrary::scan())
            ->filter(fn ($info, $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), \App\Support\MediaLibrary::IMAGE_EXTENSIONS, true))
            ->when($search !== '', fn ($c) => $c->filter(fn ($i, $path) => stripos(basename($path), $search) !== false))
            ->sortByDesc(fn ($info) => $info[1]);
        $page = max(1, $request->integer('page', 1));
        $slice = $files->slice(($page - 1) * 40, 40);
        $alts = \App\Models\Media::whereIn('path', $slice->keys())->pluck('alt', 'path');

        return response()->json([
            'data' => $slice->map(fn ($info, $path) => [
                'url' => url('/' . implode('/', array_map('rawurlencode', explode('/', $path)))),
                'name' => basename($path),
                'alt' => $alts[$path] ?? null,
            ])->values(),
            'has_more' => $files->count() > $page * 40,
        ]);
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
            'meta_title' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'canonical' => 'nullable|url|max:255',
            'noindex' => 'nullable|boolean',
            'inline' => 'nullable|boolean',
            'cover' => 'nullable|integer|min:0|max:11',
            'cover_path' => 'nullable|string|max:300',
        ] + ($withPhotos ? [
            'photos' => 'nullable|array|max:12',
            'photos.*' => 'image|mimes:jpeg,png,webp|max:6144',
            'alts' => 'nullable|array|max:12',
            'alts.*' => 'nullable|string|max:200',
        ] : []));
    }

    /**
     * Only plain article markup. Headings and paragraphs may keep their alignment; photos keep their
     * address (a photo on this website, or [[photo-N]] for the Nth file sent), text, side and width.
     * Links stay when they point to a live page of this website, to another website (https, opening in
     * a new tab), or were already in the article ($keep).
     */
    private function clean(string $html, array $keep = [], array $paths = []): string
    {
        $html = strip_tags($html, self::ALLOWED_TAGS . '<a>');
        $html = preg_replace_callback('/<(h2|h3|h4|h5|h6|p)\b([^>]*)>/i', fn ($m) => '<' . strtolower($m[1])
            . (preg_match('/text-align:\s*(center|right|justify)/i', $m[2], $a) ? ' style="text-align: ' . strtolower($a[1]) . '"' : '') . '>', $html);
        $html = preg_replace('/<(ul|ol|li|strong|em|u|s|blockquote|hr|table|thead|tbody|tr|th|td)\b[^>]*>/i', '<$1>', $html);
        $html = preg_replace_callback('/<img\b([^>]*)>/i', fn ($m) => $this->image($m[1], $paths), $html);

        $internal = null;
        return preg_replace_callback('#<a\b([^>]*)>(.*?)</a>#is', function ($m) use ($keep, &$internal) {
            $href = preg_match('#href\s*=\s*"([^"]*)"#i', $m[1], $h) ? html_entity_decode($h[1]) : '';
            $internal ??= ServiceDetail::where('is_active', true)->get(['slug'])->map(fn ($s) => $s->publicPath())
                ->merge(BlogDetail::where('status', BlogDetail::PUBLISHED)->get(['slug'])->map(fn ($b) => $b->publicPath()))->flip();
            $path = rtrim((string) parse_url($href, PHP_URL_PATH), '/');
            $ours = str_starts_with($href, '/') && !str_starts_with($href, '//') && isset($internal[$path]);
            if (!$ours && preg_match('#^https?://[^\s"<>]+$#i', $href)) {
                return '<a href="' . e($href) . '" rel="noopener" target="_blank">' . $m[2] . '</a>';
            }

            return $ours || in_array($href, $keep, true) ? '<a href="' . e($href) . '">' . $m[2] . '</a>' : $m[2];
        }, $html);
    }

    /** One photo tag, rebuilt from its allowed parts; empty when its address is not a photo on this website. */
    private function image(string $attrs, array $paths): string
    {
        $get = fn (string $name) => preg_match('/(?<![\w-])' . preg_quote($name, '/') . '\s*=\s*"([^"]*)"/i', $attrs, $m) ? html_entity_decode($m[1], ENT_QUOTES) : '';
        $src = $this->ownPhoto($get('src'), $paths);
        if ($src === null) {
            return '';
        }
        $align = in_array($get('data-align'), ['left', 'right'], true) ? ' data-align="' . $get('data-align') . '"' : '';
        $width = (int) $get('width');

        return '<img src="' . e($src) . '" alt="' . e(Str::limit(trim(strip_tags($get('alt'))), 200, '')) . '"' . $align
            . ($width >= 40 && $width <= 2000 ? ' width="' . $width . '"' : '') . ' loading="lazy">';
    }

    /** The address of a photo file of this website (/folder/name.jpg), or null. [[photo-N]] is the Nth file sent. */
    private function ownPhoto(string $src, array $paths): ?string
    {
        if (preg_match('/^\[\[photo-(\d+)\]\]$/', $src, $m)) {
            return isset($paths[(int) $m[1]]) ? '/' . $paths[(int) $m[1]] : null;
        }
        if (preg_match('#^https?://#i', $src)) {
            $host = strtolower((string) parse_url($src, PHP_URL_HOST));
            if (!in_array($host, [strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)), strtolower(request()->getHost())], true)) {
                return null;
            }
            $src = (string) parse_url($src, PHP_URL_PATH);
        }
        $path = rawurldecode($src);
        if (!preg_match(self::PHOTO_PATH, $path) || str_contains($path, '..') || !is_file(public_path(ltrim($path, '/')))) {
            return null;
        }

        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /** The cover photo: the Nth file sent, or a photo already on this website; null keeps the current one. */
    private function cover(array $data, array $paths): ?string
    {
        if (isset($data['cover']) && isset($paths[(int) $data['cover']])) {
            return $paths[(int) $data['cover']];
        }
        $own = filled($data['cover_path'] ?? null) ? $this->ownPhoto($data['cover_path'], []) : null;

        return $own ? ltrim(rawurldecode($own), '/') : null;
    }

    /** Address, canonical and noindex, when the hub sends them. The address of a live article only changes for hub articles. */
    private function seoFields(BlogDetail $blog, Request $request, array $data, bool $slug = true): void
    {
        if ($slug && filled($data['slug'] ?? null) && Str::slug($data['slug']) !== $blog->slug) {
            $blog->slug = $this->freeSlug($data['slug'], $blog->id);
        }
        if ($request->has('canonical')) {
            $blog->canonical = $data['canonical'] ?? null;
        }
        if ($request->has('noindex')) {
            $blog->noindex = (bool) $data['noindex'];
        }
    }

    private function freeSlug(string $slug, ?int $except = null): string
    {
        $slug = Str::slug($slug);
        if ($slug === '' || BlogDetail::where('slug', $slug)->when($except, fn ($q) => $q->whereKeyNot($except))->exists()) {
            throw ValidationException::withMessages(['slug' => $slug === '' ? 'The web address needs letters or numbers.' : 'Another article already uses this web address.']);
        }

        return $slug;
    }

    /** @return list<string> the links already in an article */
    private function links(BlogDetail $blog): array
    {
        return preg_match_all('#<a\b[^>]*href="([^"]+)"#i', (string) $blog->desc, $m) ? array_map('html_entity_decode', $m[1]) : [];
    }

    /** @return list<string> the addresses of the saved photo files, in the order they were sent */
    private function urls(array $paths): array
    {
        return array_map(fn ($p) => '/' . $p, $paths);
    }

    /** What a kept block is, for the hub to show it: a photo (with its address), a table or a video. */
    private function preview(string $block): array
    {
        $kind = match (true) {
            (bool) preg_match('#^<table#i', $block) => 'table',
            (bool) preg_match('#^<(iframe|video)#i', $block) => 'video',
            default => 'photo',
        };
        $src = $kind === 'photo' && preg_match('#<img\b[^>]*?(?<![\w-])src\s*=\s*"([^"]+)"#i', $block, $m) ? html_entity_decode($m[1]) : null;
        if ($src !== null && !preg_match('#^https?://#i', $src)) {
            $src = url('/' . ltrim($src, '/'));
        }

        return ['kind' => $kind, 'src' => $src];
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
