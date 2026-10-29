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
use Illuminate\Support\Str;

/**
 * The content hub (the company's billing app): reads services and approved prices, and manages the
 * articles it made itself (source "hub"): add as draft, edit, publish or schedule, unpublish, delete.
 * Articles written on this site are never touched.
 */
class ContentApiController extends Controller
{
    private const ALLOWED_TAGS = '<h2><h3><p><ul><ol><li><strong><em><br>';

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
            'services' => ServiceDetail::where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'prices' => ($prices[$s->id] ?? collect())->map(fn ($p) => ['item' => $p->item, 'from' => $p->price_from, 'to' => $p->price_to, 'unit' => $p->unit])->values(),
            ]),
            'searches' => $queries,
            'avoid_phrases' => WritingCheck::AI_PHRASES,
        ]);
    }

    /** Saves an article as a draft in Articles, with its photos. */
    public function storeDraft(Request $request)
    {
        $data = $this->validated($request, true);

        $paths = [];
        foreach ($request->file('photos', []) as $file) {
            $name = 'job-' . now()->format('ymd-His') . '-' . Str::lower(Str::random(6)) . '.' . (in_array($file->extension(), ['png', 'webp'], true) ? $file->extension() : 'jpg');
            $paths[] = $file->storeAs('Admin/Blog/Details', $name, 'uploads');
        }

        // The photos after the first go below the first section, so the article opens with the answer.
        $body = $this->clean($data['body_html']);
        $alts = array_values($data['alts'] ?? []);
        $figures = collect($paths)->slice(1)->values()->map(fn ($p, $i) => '<figure><img src="/' . e($p) . '" alt="' . e($alts[$i + 1] ?? $data['title']) . '"></figure>')->implode("\n");
        if ($figures !== '') {
            $first = stripos($body, '<h2');
            $second = $first === false ? false : stripos($body, '<h2', $first + 3);
            $at = $second === false ? strlen($body) : $second;
            $body = substr($body, 0, $at) . $figures . substr($body, $at);
        }

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

    /** Only plain article markup, without attributes. */
    private function clean(string $html): string
    {
        $html = strip_tags($html, self::ALLOWED_TAGS);

        return preg_replace('/<(h2|h3|p|ul|ol|li|strong|em)\b[^>]*>/i', '<$1>', $html);
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
