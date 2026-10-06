<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\Faq;
use App\Models\ServiceDetail;
use App\Models\User;
use App\Support\ContentHtml;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * "Preview": an article shown with the real article page (Frontend/Blogs/Show) before it is live.
 * Only for signed-in admin users (admin routes), never indexed, nothing is saved.
 * - fromEditor: what is in the editor right now (posted from the editor into a new tab).
 * - revision: a change sent for approval; its writer and the approvers can open it.
 */
class ArticlePreviewController extends Controller
{
    private const FIELDS = ['name', 'slug', 'excerpt', 'desc', 'meta_title', 'meta_desc', 'focus_keyword', 'primary_service_id', 'image'];

    public function fromEditor(Request $request)
    {
        $data = json_decode((string) $request->input('payload', '{}'), true);
        $data = is_array($data) ? $data : [];
        $base = $request->filled('id') ? BlogDetail::find($request->integer('id')) : null;
        if ($base && !$this->canSee($request->user(), $base)) {
            abort(403);
        }

        return $this->render($request, $base, $data);
    }

    public function revision(Request $request, ArticleRevision $revision)
    {
        $user = $request->user();
        abort_unless($revision->article && ($revision->user_id === $user->id || $user->can('articles.publish')), 403);

        return $this->render($request, $revision->article, (array) $revision->payload);
    }

    private function canSee(User $user, BlogDetail $blog): bool
    {
        return $user->can('articles.edit_all') || $user->can('articles.publish') || $blog->author_id === $user->id || $blog->status === BlogDetail::PUBLISHED;
    }

    private function render(Request $request, ?BlogDetail $base, array $data)
    {
        $blog = $base ? (clone $base)->load(['author:id,name,job_title,bio,image,social_url']) : new BlogDetail(['status' => BlogDetail::DRAFT]);
        $fields = array_intersect_key($data, array_flip(self::FIELDS));
        // A picture just chosen in the editor (not uploaded yet) cannot be shown: keep the saved one.
        if (isset($fields['image']) && (!is_string($fields['image']) || preg_match('#^(blob:|data:|https?:)#', $fields['image']))) {
            unset($fields['image']);
        }
        $blog->forceFill($fields);
        $blog->name = trim((string) $blog->name) ?: 'Untitled article';
        $blog->published_at ??= now();
        if (!$base) {
            $blog->author_id = $request->user()->id;
            $blog->setRelation('author', $request->user());
        }

        $service = $blog->primary_service_id ? ServiceDetail::find($blog->primary_service_id, ['id', 'name', 'slug', 'short_summary', 'image']) : null;
        $blog->setRelation('primaryService', $service);
        $blog->setRelation('faqs', collect($data['faqs'] ?? ($base?->faqs()->where('is_active', true)->get()->toArray() ?? []))
            ->filter(fn ($f) => filled($f['question'] ?? null) && filled($f['answer'] ?? null))
            ->values()->map(fn ($f, $i) => new Faq(['question' => $f['question'], 'answer' => $f['answer'], 'sort_order' => $i, 'is_active' => true])));

        $author = $blog->byline();
        $blog->makeHidden(['review_note', 'reviewed_by', 'submitted_at', 'status', 'source_check', 'quality_check', 'auto_approve_at']);
        $blog->desc = ContentHtml::render((string) $blog->desc, (string) $blog->name);

        return Inertia::render('Frontend/Blogs/Show', [
            'blog' => $blog,
            'author' => $author,
            'relatedBlogs' => [],
            'prices' => $service ? $service->prices()->where('is_active', true)->where('is_featured', true)->take(4)->get() : [],
            'preview' => true,
        ])->toResponse($request)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store');
    }
}
