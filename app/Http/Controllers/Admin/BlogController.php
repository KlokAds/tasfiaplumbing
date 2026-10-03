<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\ArticleRevision;
use App\Models\SiteSetting;
use App\Models\ArticleVersion;
use App\Support\ArticleHistory;
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
            'review' => $canPublish
                ? $query->where('status', BlogDetail::PENDING)
                : $query->where(fn ($q) => $q->where('status', BlogDetail::PENDING)
                    ->orWhereExists(fn ($r) => $r->selectRaw('1')->from('article_revisions')->whereColumn('article_revisions.article_id', 'blog_details.id')
                        ->where('article_revisions.status', 'pending')->where('article_revisions.user_id', $user->id))),
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
                'review' => $scope(BlogDetail::where('status', BlogDetail::PENDING))->count()
                    + ($canPublish ? ArticleRevision::where('status', 'pending')->count() : ArticleRevision::where('status', 'pending')->where('user_id', $user->id)->count()),
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
            'myRevisions' => $this->openRevisions($user),
            // Approvers choose an article's author and the default author (byline, schema, E-E-A-T).
            'authors' => $canPublish ? User::visible()->orderBy('name')->get(['id', 'name', 'job_title', 'bio'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'job_title' => $u->job_title, 'has_bio' => filled($u->bio)]) : [],
            'defaultAuthor' => (int) SiteSetting::stored('articles.default_author') ?: null,
            'editBlog' => $request->filled('edit') && ($b = BlogDetail::with('faqs')->find($request->integer('edit'))) && $this->canEdit($user, $b)
                ? $this->withRevision($this->row($b, $dup, $services, $user), $canPublish ? $request->integer('revision') : 0)
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
        $blog->author_id = $user->can('articles.publish') && !empty($data['author_id']) ? (int) $data['author_id'] : $user->id;
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
            } elseif ($earlier = ArticleRevision::where('article_id', $blog->id)->where('user_id', $user->id)->where('status', 'pending')->first()?->payload) {
                if (!empty($earlier['image'])) {
                    $payload['image'] = $earlier['image'];
                }
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

        // A publisher who opened a writer's pending changes (Edit before approving) saves them here.
        $revision = $user->can('articles.publish') && $request->filled('revision_id')
            ? ArticleRevision::where('id', $request->integer('revision_id'))->where('article_id', $blog->id)->where('status', 'pending')->first()
            : null;

        if ($user->can('articles.publish') && $request->has('author_id')) {
            $blog->author_id = $data['author_id'] ?: null; // the approver changed the author
        }

        $oldPath = $blog->publicPath();
        $oldStatus = $blog->status;
        $before = $oldStatus === BlogDetail::PUBLISHED ? ArticleHistory::snapshot($blog) : null;
        $blog->fill($this->only($data));
        if ($revision && !$image && !empty($revision->payload['image'])) {
            $image = $revision->payload['image']; // the writer's new picture, unless the publisher chose another
        }
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
        ArticleHistory::record($blog, $before, $user, $revision ? 'change_approved' : 'edit');
        if ($revision && in_array($blog->status, [BlogDetail::PUBLISHED, BlogDetail::SCHEDULED], true)) {
            $revision->update(['status' => 'approved', 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'note' => 'Edited by the reviewer before approval.']);
            ArticleNotifier::revisionReviewed($revision->load('article', 'user'), $user, true);
        }

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
        $before = $blog->status === BlogDetail::PUBLISHED ? ArticleHistory::snapshot($blog) : null;

        $blog->fill(collect($payload)->only(self::CONTENT_FIELDS)->all());
        $blog->reviewed_by = $request->user()->id;
        $blog->reviewed_at = now();
        $blog->save();
        $blog->syncFaqs($payload['faqs'] ?? []);
        ArticleHistory::record($blog, $before, $request->user(), 'change_approved');

        $revision->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        SeoAudit::flush();
        ArticleNotifier::revisionReviewed($revision, $request->user(), true);

        return redirect()->back()->with('success', "Changes to \"{$blog->name}\" are now live.");
    }

    /** The last three live versions of an article, for the History panel (older ones stay stored). */
    public function versions(BlogDetail $blog)
    {
        $current = ArticleHistory::snapshot($blog);

        return response()->json([
            'current' => $current + ['text' => self::plain($current['desc'] ?? '')],
            'versions' => ArticleVersion::with('user:id,name')->where('article_id', $blog->id)->latest('id')->limit(3)->get()
                ->map(fn (ArticleVersion $v) => [
                    'id' => $v->id,
                    'event' => $v->event,
                    'by' => $v->user?->name,
                    'at' => $v->created_at->toIso8601String(),
                    'payload' => $v->payload,
                    'text' => self::plain($v->payload['desc'] ?? ''),
                    // Shown as HTML in admin: cleaned like the public page (no scripts or pasted styles).
                    'html' => \App\Support\ContentHtml::render((string) ($v->payload['desc'] ?? ''), (string) ($v->payload['name'] ?? '')),
                ]),
        ]);
    }

    /** Put an earlier version live again; the text it replaces is kept as a version too. */
    public function restoreVersion(Request $request, ArticleVersion $version)
    {
        $blog = $version->article;
        $before = ArticleHistory::snapshot($blog);
        $payload = $version->payload;
        $oldPath = $blog->publicPath();

        $blog->fill(collect($payload)->only(self::CONTENT_FIELDS)->all());
        $blog->reviewed_by = $request->user()->id;
        $blog->reviewed_at = now();
        $blog->save();
        $blog->syncFaqs($payload['faqs'] ?? []);
        ArticleHistory::record($blog, $before, $request->user(), 'restore');
        SeoAudit::flush();

        $message = "Restored the version from {$version->created_at->timezone(config('admin.timezone'))->format('j M Y, g:i a')}. The text it replaced is in History.";
        if ($oldPath !== $blog->publicPath()) {
            $message .= " URL changed: {$oldPath} now 301-redirects to {$blog->publicPath()}.";
        }

        return redirect()->back()->with('success', $message);
    }

    private static function plain(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** The author shown on articles that have none of their own (Articles page, approvers). */
    public function defaultAuthor(Request $request)
    {
        $data = $request->validate(['author_id' => 'nullable|exists:users,id']);
        SiteSetting::putMany(['articles.default_author' => $data['author_id'] ?? '']);
        SeoAudit::flush();

        return redirect()->back()->with('success', !empty($data['author_id'])
            ? 'Default author saved. Articles without an author now show this person, with their job title and bio.'
            : 'Default author removed.');
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
            // "Save" by a publisher keeps a submitted article in the review queue (not back to draft).
            default => $blog->status === BlogDetail::PENDING && $canPublish
                ? BlogDetail::PENDING
                : ($isLive && $canPublish
                    ? ($blog->status === BlogDetail::PUBLISHED ? BlogDetail::PUBLISHED : $goLive)
                    : BlogDetail::DRAFT),
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

    /**
     * The editor row with a writer's pending changes laid over it, so a publisher can correct them
     * before they go live. Saving with "revision_id" applies them and marks the change approved.
     */
    private function withRevision(array $row, int $revisionId, bool $forReview = true): array
    {
        $revision = $revisionId ? ArticleRevision::with('user:id,name')->where('id', $revisionId)
            ->where('article_id', $row['id'])->where('status', 'pending')->first() : null;
        if (!$revision) {
            return $row;
        }
        $payload = (array) $revision->payload;
        foreach (self::CONTENT_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $row[$field] = $payload[$field];
            }
        }
        $row['faqs'] = $payload['faqs'] ?? $row['faqs'] ?? [];
        if ($forReview) {
            $row['revision_id'] = $revision->id;
            $row['revision_by'] = $revision->user?->name;
        }

        return $row;
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

        // Someone who cannot publish and has sent changes to this live article: show and edit those changes.
        if (!$user->can('articles.publish') && $row['pending_changes']) {
            $own = ArticleRevision::where('article_id', $b->getKey())->where('user_id', $user->id)->where('status', 'pending')->value('id');
            if ($own) {
                $row = $this->withRevision($row, $own, false);
                $row['my_pending'] = true;
            }
        }

        return $row;
    }

    /**
     * The writer's edits waiting for approval, and rejected edits that still need action. A rejection
     * drops off once the article was changed after it, or the writer has sent a newer edit since.
     */
    private function openRevisions(User $user)
    {
        $revisions = ArticleRevision::where('user_id', $user->id)->whereIn('status', ['pending', 'rejected'])
            ->with('article:id,name,content_updated_at')->latest()->take(30)
            ->get(['id', 'article_id', 'status', 'note', 'created_at', 'reviewed_at']);
        $latestPerArticle = ArticleRevision::where('user_id', $user->id)
            ->whereIn('article_id', $revisions->pluck('article_id')->unique())
            ->selectRaw('article_id, MAX(id) as latest_id')->groupBy('article_id')->pluck('latest_id', 'article_id');

        return $revisions->reject(function (ArticleRevision $r) use ($latestPerArticle) {
            if ($r->status !== 'rejected') {
                return false;
            }
            $changed = $r->article?->content_updated_at;

            return !$r->article
                || ($latestPerArticle[$r->article_id] ?? $r->id) !== $r->id
                || ($changed && $r->reviewed_at && $changed->gt($r->reviewed_at));
        })->take(10)->values();
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
            'author_id' => 'nullable|exists:users,id',
        ], ['scheduled_at.after' => 'The publish time must be in the future.']);

        // The "From our jobs" prompts must be replaced with real details before an article goes for approval or live.
        if (in_array($request->input('intent'), ['submit', 'publish'], true) && str_contains((string) ($data['desc'] ?? ''), '[Replace:')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['desc' => 'Replace the [Replace: …] notes in “What we see on real jobs” with real details (or delete them) first.']);
        }

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
