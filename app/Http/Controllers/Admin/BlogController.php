<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\Draft;
use App\Models\ServiceDetail;
use App\Models\User;
use App\Support\ArticleNotifier;
use App\Support\SeoAudit;
use Carbon\Carbon;
use App\Support\TitleGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BlogController extends Controller
{
    public const FILTERS = [
        'no_service' => 'Not linked to a service',
        'no_meta' => 'Missing meta title or description',
        'noindex' => 'Noindex',
    ];

    /** The badges in the list, as filters. */
    public const CHANGES = [
        'new' => 'New (added in the last 30 days)',
        'edited' => 'Edited after it was added',
        'waiting' => 'Changes waiting for approval',
    ];

    private const CONTENT_FIELDS = [
        'name', 'slug', 'primary_service_id', 'excerpt', 'desc', 'focus_keyword',
        'meta_title', 'meta_desc', 'canonical', 'noindex', 'image',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $canAll = $user->can('articles.edit_all');
        $canPublish = $user->can('articles.publish');
        $tab = $request->input('tab', 'all');

        $query = BlogDetail::with(['primaryService:id,name,slug', 'faqs', 'author:id,name'])->latest('updated_at');
        if (!$canAll || $tab === 'mine') {
            $query->where('author_id', $user->id);
        }
        match ($tab) {
            'drafts' => $query->where('status', BlogDetail::DRAFT),
            'review' => $query->where('status', BlogDetail::PENDING),
            'scheduled' => $query->where('status', BlogDetail::SCHEDULED)->reorder('scheduled_at'),
            'published' => $query->where('status', BlogDetail::PUBLISHED),
            default => null,
        };
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('service')) {
            $query->where('primary_service_id', $request->integer('service'));
        }
        match ($request->input('filter')) {
            'no_service' => $query->whereNull('primary_service_id'),
            'no_meta' => $query->where(fn ($q) => $q->whereNull('meta_title')->orWhere('meta_title', '')->orWhereNull('meta_desc')->orWhere('meta_desc', '')),
            'noindex' => $query->where('noindex', true),
            default => null,
        };

        $revisions = fn (string $status) => fn ($q) => $q->selectRaw('1')->from('article_revisions')
            ->whereColumn('article_revisions.article_id', 'blog_details.id')->where('article_revisions.status', $status);
        match ($request->input('changed')) {
            'new' => $query->where('created_at', '>=', now()->subDays(30)),
            // Same rule as the badge: the text changed after it was added, or an edit was approved.
            'edited' => $query->where(fn ($q) => $q->whereColumn('content_updated_at', '>', 'created_at')->orWhereExists($revisions('approved')))
                ->reorder()->orderByDesc('content_updated_at'),
            'waiting' => $query->whereExists($revisions('pending')),
            default => null,
        };

        $services = ServiceDetail::orderBy('order')->get(['id', 'name', 'slug']);
        $dup = SeoAudit::duplicateTitles(BlogDetail::class);

        $blogs = $query->paginate(PerPage::get($request, 25))->withQueryString();
        $this->loadEdits($blogs->getCollection()->modelKeys());
        $blogs->getCollection()->transform(fn (BlogDetail $b) => $this->row($b, $dup, $services, $user));

        $scope = fn ($q) => $canAll ? $q : $q->where('author_id', $user->id);

        return Inertia::render('Admin/Blogs/Index', [
            'blogs' => $blogs,
            'services' => $services,
            'filters' => $request->only(['search', 'filter', 'service', 'tab', 'per_page', 'changed']),
            'filterOptions' => self::FILTERS,
            'changeOptions' => self::CHANGES,
            'counts' => [
                'all' => $scope(BlogDetail::query())->count(),
                'mine' => BlogDetail::where('author_id', $user->id)->count(),
                'drafts' => $scope(BlogDetail::where('status', BlogDetail::DRAFT))->count(),
                'review' => $scope(BlogDetail::where('status', BlogDetail::PENDING))->count() + ($canPublish ? ArticleRevision::where('status', 'pending')->count() : 0),
                'scheduled' => $scope(BlogDetail::where('status', BlogDetail::SCHEDULED))->count(),
                'published' => $scope(BlogDetail::published())->count(),
            ],
            'timezone' => config('admin.timezone'),
            'unlinkedCount' => $scope(BlogDetail::whereNull('primary_service_id'))->count(),
            'revisions' => $canPublish && $tab === 'review'
                ? ArticleRevision::with(['article:id,name,slug', 'user:id,name'])->where('status', 'pending')->latest()->get()
                    // The preview is rendered as HTML in admin: clean it exactly like the public page does
                    // (no scripts, event handlers or pasted styles), so a submitted draft cannot run code.
                    ->map(fn ($r) => ['id' => $r->id, 'article' => $r->article, 'user' => $r->user?->name, 'payload' => ['desc' => \App\Support\ContentHtml::render((string) ($r->payload['desc'] ?? ''), (string) ($r->payload['name'] ?? ''))] + (array) $r->payload, 'created_at' => $r->created_at->toIso8601String()])
                : [],
            'myRevisions' => ArticleRevision::where('user_id', $user->id)->whereIn('status', ['pending', 'rejected'])->with('article:id,name')->latest()->take(10)->get(['id', 'article_id', 'status', 'note', 'created_at']),
            'editBlog' => $request->filled('edit') && ($b = BlogDetail::with('faqs')->find($request->integer('edit'))) && $this->canEdit($user, $b)
                ? $this->row($b, $dup, $services, $user)
                : null,
            'permissions' => [
                'publish' => $canPublish,
                'edit_all' => $canAll,
                'delete' => $user->can('articles.delete'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validated($request);
        $intent = $request->input('intent', 'draft');

        $blog = new BlogDetail($this->only($data));
        $blog->scheduled_at = $data['scheduled_at'];
        $blog->author_id = $user->id;
        $blog->auth_name = $user->name;
        $blog->btn_name = 'Read More';
        $this->applyStatus($blog, $intent, $user);
        if ($request->hasFile('image')) {
            $blog->image = $request->file('image')->store('Admin/Blog/Details', 'uploads');
        }
        $blog->save();
        $blog->syncFaqs($request->input('faqs'));
        Draft::discard($user->id, 'article', 0);
        SeoAudit::flush();
        $this->notifyTransition($blog, null, $user);

        return redirect()->route('admin.blogs.index', ['tab' => match ($blog->status) {
            BlogDetail::PUBLISHED => 'published',
            BlogDetail::SCHEDULED => 'scheduled',
            default => 'mine',
        }])
            ->with('success', $this->statusMessage($blog));
    }

    public function update(Request $request, BlogDetail $blog)
    {
        $user = $request->user();
        abort_unless($this->canEdit($user, $blog), 403);

        $data = $this->validated($request, $blog->id);
        $intent = $request->input('intent', 'draft');
        $image = $request->hasFile('image') ? $request->file('image')->store('Admin/Blog/Details', 'uploads') : null;

        // A live article edited by someone who cannot publish: queue the change, keep the live page as it is.
        if ($blog->status === BlogDetail::PUBLISHED && !$user->can('articles.publish')) {
            $payload = $this->only($data) + ['faqs' => $this->faqRows($request->input('faqs'))];
            if ($image) {
                $payload['image'] = $image;
            }
            $revision = ArticleRevision::updateOrCreate(
                ['article_id' => $blog->id, 'user_id' => $user->id, 'status' => 'pending'],
                ['payload' => $payload],
            );
            Draft::discard($user->id, 'article', $blog->id);
            if ($revision->wasRecentlyCreated) {
                ArticleNotifier::revisionSubmitted($revision->load('article'), $user);
            }

            return redirect()->back()->with('success', 'Changes sent for approval. The live article stays unchanged until a Super Admin approves them.');
        }

        $oldPath = $blog->publicPath();
        $oldStatus = $blog->status;
        $blog->fill($this->only($data));
        $blog->scheduled_at = $data['scheduled_at'];
        if ($image) {
            $blog->image = $image;
        }
        $this->applyStatus($blog, $intent, $user);
        $blog->save();
        $blog->syncFaqs($request->input('faqs'));
        Draft::discard($user->id, 'article', $blog->id);
        SeoAudit::flush();
        $this->notifyTransition($blog, $oldStatus, $user);

        $message = $this->statusMessage($blog);
        if ($oldPath !== $blog->publicPath()) {
            $message .= " URL changed: {$oldPath} now 301-redirects to {$blog->publicPath()}.";
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Approve a submitted article, or change the time of a scheduled one.
     * The publisher can keep the author's requested time, pick another, or publish now.
     */
    public function approve(Request $request, BlogDetail $blog)
    {
        abort_unless(in_array($blog->status, [BlogDetail::PENDING, BlogDetail::SCHEDULED, BlogDetail::DRAFT], true), 422);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['now', 'schedule'])],
            'scheduled_at' => ['nullable', 'required_if:mode,schedule', 'date', 'after:now'],
        ], [
            'scheduled_at.after' => 'The publish time must be in the future.',
            'scheduled_at.required_if' => 'Choose a date and time.',
        ]);

        $wasPending = $blog->status === BlogDetail::PENDING;
        $blog->scheduled_at = $data['mode'] === 'schedule' ? Carbon::parse($data['scheduled_at']) : null;
        $blog->status = $blog->scheduled_at ? BlogDetail::SCHEDULED : BlogDetail::PUBLISHED;
        $blog->reviewed_by = $request->user()->id;
        $blog->reviewed_at = now();
        $blog->review_note = null;
        $blog->save();
        SeoAudit::flush();
        if ($wasPending || $blog->status === BlogDetail::PUBLISHED) {
            ArticleNotifier::approved($blog, $request->user());
        }

        return redirect()->back()->with('success', $blog->status === BlogDetail::SCHEDULED
            ? "Approved. \"{$blog->name}\" goes live on " . ArticleNotifier::when($blog->scheduled_at) . '.'
            : "Published: {$blog->name}");
    }

    /** Send a submitted (or scheduled) article back to its author with a note. */
    public function reject(Request $request, BlogDetail $blog)
    {
        abort_unless(in_array($blog->status, [BlogDetail::PENDING, BlogDetail::SCHEDULED], true), 422);
        $note = $request->validate(['note' => 'required|string|max:1000'])['note'];
        $blog->fill([
            'status' => BlogDetail::DRAFT,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();
        ArticleNotifier::rejected($blog, $request->user(), $note);

        return redirect()->back()->with('success', 'Sent back to the author. They get your note by email and in their notifications.');
    }

    public function approveRevision(Request $request, ArticleRevision $revision)
    {
        abort_unless($revision->status === 'pending', 422);
        $blog = $revision->article;
        $payload = $revision->payload;

        $blog->fill(collect($payload)->only(self::CONTENT_FIELDS)->all());
        $blog->reviewed_by = $request->user()->id;
        $blog->reviewed_at = now();
        $blog->save();
        $blog->syncFaqs($payload['faqs'] ?? []);

        $revision->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        SeoAudit::flush();
        ArticleNotifier::revisionReviewed($revision, $request->user(), true);

        return redirect()->back()->with('success', "Changes to \"{$blog->name}\" are now live.");
    }

    public function rejectRevision(Request $request, ArticleRevision $revision)
    {
        $note = $request->validate(['note' => 'required|string|max:1000'])['note'];
        $revision->update(['status' => 'rejected', 'note' => $note, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        ArticleNotifier::revisionReviewed($revision->load('article', 'user'), $request->user(), false);

        return redirect()->back()->with('success', 'Changes rejected; the author sees your note.');
    }

    public function bulkAssignService(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer|exists:blog_details,id',
            'service_id' => 'required|exists:service_details,id',
        ]);

        $n = BlogDetail::whereIn('id', $data['ids'])->update(['primary_service_id' => $data['service_id']]);
        SeoAudit::flush();

        return redirect()->back()->with('success', "{$n} article(s) linked to the service.");
    }

    public function destroy(Request $request, BlogDetail $blog)
    {
        $user = $request->user();
        $ownUnpublished = $blog->author_id === $user->id && in_array($blog->status, [BlogDetail::DRAFT, BlogDetail::PENDING], true);
        abort_unless($user->can('articles.delete') || $ownUnpublished, 403);

        $path = $blog->publicPath();
        $wasLive = $blog->status === BlogDetail::PUBLISHED;
        $blog->faqs()->delete();
        $blog->delete();
        SeoAudit::flush();

        return redirect()->back()->with('success', $wasLive
            ? "Article deleted. {$path} now returns 410; point it to a related page in Redirects if it had traffic or backlinks."
            : 'Draft deleted.');
    }

    private function canEdit(User $user, BlogDetail $blog): bool
    {
        return $user->can('articles.edit_all') || $blog->author_id === $user->id;
    }

    /**
     * Publishers publish (now, or scheduled when a future time is set). Everyone else can only
     * submit for approval; their requested time is kept and applied when the article is approved.
     */
    private function applyStatus(BlogDetail $blog, string $intent, User $user): void
    {
        $canPublish = $user->can('articles.publish');
        $goLive = $blog->scheduled_at?->isFuture() ? BlogDetail::SCHEDULED : BlogDetail::PUBLISHED;
        $isLive = in_array($blog->status, [BlogDetail::PUBLISHED, BlogDetail::SCHEDULED], true);

        $status = match ($intent) {
            'publish', 'submit' => $canPublish ? $goLive : BlogDetail::PENDING,
            'unpublish' => $canPublish ? BlogDetail::DRAFT : $blog->status,
            default => $isLive && $canPublish
                ? ($blog->status === BlogDetail::PUBLISHED ? BlogDetail::PUBLISHED : $goLive)
                : BlogDetail::DRAFT,
        };

        if ($status === BlogDetail::PENDING && $blog->status !== BlogDetail::PENDING) {
            $blog->submitted_at = now();
            $blog->review_note = null;
        }
        if (in_array($status, [BlogDetail::PUBLISHED, BlogDetail::SCHEDULED], true) && !$isLive) {
            $blog->reviewed_by = $user->id;
            $blog->reviewed_at = now();
            $blog->review_note = null;
        }
        if ($status === BlogDetail::PUBLISHED) {
            $blog->scheduled_at = null;
        }
        $blog->status = $status;
    }

    private function notifyTransition(BlogDetail $blog, ?string $old, User $user): void
    {
        if ($blog->status === BlogDetail::PENDING && $old !== BlogDetail::PENDING) {
            ArticleNotifier::submitted($blog, $user);
        } elseif ($old === BlogDetail::PENDING && in_array($blog->status, [BlogDetail::PUBLISHED, BlogDetail::SCHEDULED], true)) {
            ArticleNotifier::approved($blog, $user);
        }
    }

    private function statusMessage(BlogDetail $blog): string
    {
        return match ($blog->status) {
            BlogDetail::PUBLISHED => 'Article saved and live.',
            BlogDetail::SCHEDULED => 'Scheduled. It goes live automatically on ' . ArticleNotifier::when($blog->scheduled_at) . '.',
            BlogDetail::PENDING => $blog->scheduled_at
                ? 'Submitted for approval, requested for ' . ArticleNotifier::when($blog->scheduled_at) . '. You will get an email when it is reviewed.'
                : 'Submitted for approval. You will get an email when it is reviewed.',
            default => 'Draft saved.',
        };
    }

    private function row(BlogDetail $b, array $dup, $services, User $user): array
    {
        $row = $b->toArray();
        $row['seo'] = SeoAudit::article($b, $dup);
        $row['word_count'] = SeoAudit::wordCount($b->desc);
        $row['public_path'] = $b->publicPath();
        $row['author_name'] = $b->author?->name ?? $b->auth_name;
        $row['suggested_service'] = $b->primary_service_id ? null : $this->suggestService($b->name, $services);
        $row['can_delete'] = $user->can('articles.delete') || ($b->author_id === $user->id && in_array($b->status, [BlogDetail::DRAFT, BlogDetail::PENDING], true));
        // For the "Edited" / "Changes waiting" badge in the list.
        $edits = isset($this->edits['ids'][$b->getKey()]) ? $this->edits : $this->loadEdits([$b->getKey()]);
        $row['pending_changes'] = isset($edits['pending'][$b->getKey()]);
        $row['edited_at'] = $this->editedAt($b, $edits['approved'][$b->getKey()] ?? null)?->toIso8601String();

        return $row;
    }

    /** Pending edits and last approved edit per article, for one page of the list. */
    private ?array $edits = null;

    private function loadEdits(array $ids): array
    {
        $rows = ArticleRevision::whereIn('article_id', $ids)->whereIn('status', ['pending', 'approved'])
            ->selectRaw('article_id, status, MAX(reviewed_at) as last_reviewed')->groupBy('article_id', 'status')->get();

        return $this->edits = [
            'ids' => array_flip($ids),
            'pending' => $rows->where('status', 'pending')->pluck('article_id')->flip()->all(),
            'approved' => $rows->where('status', 'approved')->pluck('last_reviewed', 'article_id')->all(),
        ];
    }

    /**
     * When the article itself last changed: its text (content_updated_at moves only on real
     * changes, not on approve/publish) or an approved edit. Null if never changed after creation.
     */
    private function editedAt(BlogDetail $b, ?string $approvedEdit): ?Carbon
    {
        $created = $b->created_at;
        return collect([$b->content_updated_at, $approvedEdit ? Carbon::parse($approvedEdit) : null])
            ->filter(fn ($t) => $t && (!$created || $t->gt($created->copy()->addMinute())))
            ->sortDesc()->first();
    }

    private function only(array $data): array
    {
        return collect($data)->only(self::CONTENT_FIELDS)->except('image')->all();
    }

    private function faqRows(?array $rows): array
    {
        return collect($rows ?? [])
            ->map(fn ($r) => ['question' => trim($r['question'] ?? ''), 'answer' => trim($r['answer'] ?? '')])
            ->filter(fn ($r) => $r['question'] !== '' && $r['answer'] !== '')
            ->values()->all();
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->input('slug'))]);
        }

        $data = $request->validate([
            'intent' => ['nullable', Rule::in(['draft', 'submit', 'publish', 'unpublish'])],
            'name' => ['required', 'string', 'max:255', TitleGuard::rule('article', $ignoreId, 'title')],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_details', 'slug')->ignore($ignoreId)],
            'primary_service_id' => 'nullable|exists:service_details,id',
            'excerpt' => 'nullable|string|max:500',
            'desc' => 'required|string',
            'focus_keyword' => 'nullable|string|max:120',
            'meta_title' => ['nullable', 'string', 'max:255', TitleGuard::rule('article', $ignoreId, 'meta title')],
            'meta_desc' => 'nullable|string|max:500',
            'canonical' => 'nullable|url|max:255',
            'noindex' => 'boolean',
            'scheduled_at' => array_merge(['nullable', 'date'], in_array($request->input('intent'), ['submit', 'publish'], true) ? ['after:now'] : []),
            'image' => 'nullable|image|max:8192',
            'faqs' => 'nullable|array|max:30',
            'faqs.*.question' => 'nullable|string|max:500',
            'faqs.*.answer' => 'nullable|string|max:3000',
        ], ['scheduled_at.after' => 'The publish time must be in the future.']);

        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }
        $data['scheduled_at'] = filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at']) : null;

        return $data;
    }

    private function suggestService(string $title, $services): ?array
    {
        $stop = ['service', 'services', 'singapore', 'and', 'the', 'repair', 'in', 'sg', 'for', 'of', 'a'];
        $words = fn (string $s) => array_diff(array_unique(str_word_count(Str::lower($s), 1)), $stop);
        $titleWords = $words($title);

        $best = null;
        $bestScore = 0;
        foreach ($services as $s) {
            $score = count(array_intersect($titleWords, $words($s->name)));
            if ($score > $bestScore) {
                $best = $s;
                $bestScore = $score;
            }
        }

        return $best ? ['id' => $best->id, 'name' => $best->name] : null;
    }
}
