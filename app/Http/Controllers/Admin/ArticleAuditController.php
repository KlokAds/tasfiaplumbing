<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleAudit;
use App\Models\BlogDetail;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Support\ArticleAuditor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Articles → Audit: every live article grouped by topic with a suggested action. Approvers record a
 * decision per article; nothing on the website changes here (the changes are made later, in batches).
 */
class ArticleAuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['suggestion', 'state', 'search', 'per_page']);
        $live = fn ($q) => $q->whereHas('article', fn ($a) => $a->where('status', BlogDetail::PUBLISHED));

        $scope = ArticleAudit::query()->tap($live);
        if ($request->filled('suggestion')) {
            $scope->where(fn ($q) => $q->where('suggestion', $request->input('suggestion'))->orWhere('decision', $request->input('suggestion')));
        }
        if ($request->input('state') === 'open') {
            $scope->whereNull('decision');
        } elseif ($request->input('state') === 'decided') {
            $scope->whereNotNull('decision');
        }
        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $scope->where(fn ($q) => $q->where('group_key', 'like', $term)
                ->orWhereHas('article', fn ($a) => $a->where('name', 'like', $term)));
        }

        // A page of topic groups, the biggest problems first (many articles, then clicks).
        $groups = (clone $scope)->selectRaw('group_key, MAX(group_size) as size, SUM(clicks) as clicks, SUM(impressions) as impressions')
            ->groupBy('group_key')->orderByDesc('size')->orderByDesc('clicks')
            ->paginate(min(50, max(10, (int) $request->input('per_page', 20))))->withQueryString();

        $rows = (clone $scope)->whereIn('group_key', $groups->pluck('group_key'))
            ->with('article:id,name,slug')->get();
        $names = BlogDetail::whereIn('id', $rows->pluck('target_article_id')->merge($rows->pluck('dup_article_id'))->merge($rows->pluck('decision_target_id'))->filter()->unique())
            ->get(['id', 'name', 'slug'])->keyBy('id');
        $services = ServiceDetail::whereIn('id', $rows->pluck('service_id')->filter()->unique())->get(['id', 'name', 'slug'])->keyBy('id');
        $link = fn ($id) => ($b = $names[$id] ?? null) ? ['id' => $b->id, 'name' => $b->name, 'path' => $b->publicPath()] : null;

        $byGroup = $rows->groupBy('group_key');
        $groups->getCollection()->transform(fn ($g) => [
            'key' => $g->group_key,
            'size' => (int) $g->size,
            'clicks' => (int) $g->clicks,
            'impressions' => (int) $g->impressions,
            'rows' => ($byGroup[$g->group_key] ?? collect())->sortBy([['clicks', 'desc'], ['impressions', 'desc'], ['score', 'desc']])->values()
                ->map(fn (ArticleAudit $a) => [
                    'id' => $a->id,
                    'article' => ['id' => $a->article->id, 'name' => $a->article->name, 'path' => $a->article->publicPath()],
                    'words' => $a->words,
                    'score' => $a->score,
                    'dup_percent' => $a->dup_percent,
                    'dup' => $link($a->dup_article_id),
                    'clicks' => $a->clicks,
                    'impressions' => $a->impressions,
                    'position' => $a->position,
                    'top_query' => $a->top_query,
                    'service' => ($s = $services[$a->service_id] ?? null) ? ['name' => $s->name, 'path' => $s->publicPath()] : null,
                    'suggestion' => $a->suggestion,
                    'target' => $link($a->target_article_id),
                    'reason' => $a->reason,
                    'decision' => $a->decision,
                    'decision_target' => $link($a->decision_target_id),
                    'decision_note' => $a->decision_note,
                ]),
        ]);

        $all = ArticleAudit::query()->tap($live);

        return Inertia::render('Admin/Blogs/Audit', [
            'groups' => $groups,
            'filters' => $filters,
            'actions' => ArticleAudit::ACTIONS,
            'summary' => [
                'total' => (clone $all)->count(),
                'decided' => (clone $all)->whereNotNull('decision')->count(),
                'suggested' => (clone $all)->selectRaw('suggestion, count(*) as n')->groupBy('suggestion')->pluck('n', 'suggestion'),
                'clicks' => (int) (clone $all)->sum('clicks'),
                'computed_at' => SiteSetting::stored('audit.computed_at'),
                'search_console' => SiteSetting::stored('audit.search_console') === '1',
                'error' => SiteSetting::stored('audit.error'),
                'live_articles' => BlogDetail::where('status', BlogDetail::PUBLISHED)->count(),
            ],
            'thresholds' => ArticleAuditor::thresholds(),
        ]);
    }

    public function run()
    {
        @ini_set('memory_limit', '512M');
        $result = ArticleAuditor::run();

        return back()->with($result['search_console'] ? 'success' : 'error', $result['search_console']
            ? "Audit done: {$result['articles']} live articles with Search Console data (16 months)."
            : "Audit done for {$result['articles']} articles, without Search Console: " . $result['error']);
    }

    /** The approver's decision for one article. Nothing on the website changes yet. */
    public function decide(Request $request, ArticleAudit $audit)
    {
        $data = $request->validate([
            'decision' => ['nullable', Rule::in(array_keys(ArticleAudit::ACTIONS))],
            'target_id' => ['nullable', 'integer', Rule::exists('blog_details', 'id')->where('status', BlogDetail::PUBLISHED)],
            'note' => 'nullable|string|max:500',
        ]);
        $target = $data['decision'] === 'merge' ? ($data['target_id'] ?? $audit->target_article_id) : null;
        if ($target && (int) $target === $audit->article_id) {
            return back()->with('error', 'An article cannot be merged into itself.');
        }
        if ($data['decision'] === 'merge' && !$target && !$audit->service_id) {
            return back()->with('error', 'Choose the article to merge it into.');
        }

        $audit->update([
            'decision' => $data['decision'],
            'decision_target_id' => $target,
            'decision_note' => $data['note'] ?? null,
            'decided_by' => $data['decision'] ? $request->user()->id : null,
            'decided_at' => $data['decision'] ? now() : null,
        ]);

        return back()->with('success', $data['decision'] ? 'Decision saved. Nothing on the website changes until the batch is run.' : 'Decision cleared.');
    }

    /** Accept the suggestion of every undecided article in one topic group. */
    public function acceptGroup(Request $request)
    {
        $key = $request->validate(['group_key' => 'required|string|max:190'])['group_key'];
        $n = 0;
        foreach (ArticleAudit::where('group_key', $key)->whereNull('decision')->get() as $a) {
            $a->update(['decision' => $a->suggestion, 'decision_target_id' => $a->suggestion === 'merge' ? $a->target_article_id : null,
                'decided_by' => $request->user()->id, 'decided_at' => now()]);
            $n++;
        }

        return back()->with('success', "Suggestion accepted for {$n} article(s) in “{$key}”. Nothing on the website changes until the batch is run.");
    }
}
