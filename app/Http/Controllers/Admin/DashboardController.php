<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\Draft;
use App\Models\Location;
use App\Models\Message;
use App\Models\NotFoundLog;
use App\Models\Price;
use App\Models\ServiceDetail;
use App\Support\SeoAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $can = fn (string $p) => $user->can($p);

        $kpis = [];
        if ($can('enquiries.view')) {
            $kpis[] = [
                'label' => 'Enquiries (30 days)',
                'value' => Message::where('created_at', '>=', now()->subDays(30))->count(),
                'hint' => Message::where('is_read', 0)->count() . ' unread',
                'href' => '/admin/messages',
            ];
        }
        $kpis[] = [
            'label' => 'Published articles',
            'value' => BlogDetail::published()->count(),
            'hint' => '+' . BlogDetail::published()->where('published_at', '>=', now()->startOfMonth())->count() . ' this month',
            'href' => '/admin/blogs?tab=published',
        ];
        $kpis[] = [
            'label' => 'Live services',
            'value' => ServiceDetail::where('is_active', true)->count(),
            'hint' => ServiceDetail::whereNull('category_id')->count() . ' without category',
            'href' => '/admin/services',
        ];
        $seo = SeoAudit::overview();
        $totals = collect($seo['by_type']);
        $kpis[] = [
            'label' => 'Average SEO score',
            'value' => $totals->sum('total') ? (int) round($totals->sum(fn ($t) => $t['avg_score'] * $t['total']) / $totals->sum('total')) : 0,
            'hint' => $totals->sum('with_errors') . ' pages with errors',
            'href' => '/admin/seo/health',
            'suffix' => '/100',
        ];

        $attention = array_values(array_filter([
            $can('articles.edit_all') ? ['label' => 'Articles not linked to a service', 'count' => BlogDetail::whereNull('primary_service_id')->count(), 'href' => '/admin/blogs?filter=no_service', 'level' => 'error'] : null,
            $can('services.edit') ? ['label' => 'Services without a category', 'count' => ServiceDetail::whereNull('category_id')->count(), 'href' => '/admin/services', 'level' => 'warning'] : null,
            $can('pricing.create') ? ['label' => 'Services without a price', 'count' => ServiceDetail::doesntHave('prices')->count(), 'href' => '/admin/pricing', 'level' => 'warning'] : null,
            $can('pricing.edit') ? ['label' => 'Prices not reviewed for ' . Price::STALE_AFTER_DAYS . '+ days', 'count' => Price::where(fn ($q) => $q->whereNull('last_reviewed_at')->orWhere('last_reviewed_at', '<', now()->subDays(Price::STALE_AFTER_DAYS)))->count(), 'href' => '/admin/pricing', 'level' => 'warning'] : null,
            $can('locations.create') ? ['label' => 'Location pages (plan: 8–10)', 'count' => Location::count(), 'target' => 8, 'href' => '/admin/locations', 'level' => Location::count() >= 8 ? 'ok' : 'warning'] : null,
            $can('redirects.create') ? ['label' => 'Broken URLs (404) without a redirect', 'count' => NotFoundLog::where('is_resolved', false)->count(), 'href' => '/admin/redirects?tab=404', 'level' => 'error'] : null,
        ], fn ($a) => $a && ($a['count'] > 0 || isset($a['target']))));

        $pending = [];
        if ($can('articles.publish')) {
            $pending = BlogDetail::with('author:id,name')->where('status', BlogDetail::PENDING)->latest('submitted_at')->take(6)->get()
                ->map(fn ($b) => ['type' => 'new', 'id' => $b->id, 'title' => $b->name, 'by' => $b->author?->name ?? $b->auth_name, 'at' => $b->submitted_at?->toIso8601String(), 'href' => "/admin/blogs?tab=review&edit={$b->id}"])
                ->concat(ArticleRevision::with(['article:id,name', 'user:id,name'])->where('status', 'pending')->latest()->take(6)->get()
                    ->map(fn ($r) => ['type' => 'changes', 'id' => $r->id, 'title' => $r->article?->name, 'by' => $r->user?->name, 'at' => $r->created_at->toIso8601String(), 'href' => '/admin/blogs?tab=review']))
                ->sortByDesc('at')->values()->take(8);
        }

        $mine = [
            'drafts' => BlogDetail::where('author_id', $user->id)->where('status', BlogDetail::DRAFT)->latest('updated_at')->take(5)->get(['id', 'name', 'updated_at', 'review_note']),
            'pending' => BlogDetail::where('author_id', $user->id)->where('status', BlogDetail::PENDING)->count(),
            'scheduled' => BlogDetail::where('author_id', $user->id)->where('status', BlogDetail::SCHEDULED)->count(),
            'autosaved' => Draft::where('user_id', $user->id)->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'checklist' => $can('settings.view') ? \App\Support\SiteChecklist::score() : null,
            'insights' => $can('analytics.view') ? $this->insights() : null,
            'kpis' => $kpis,
            'attention' => $attention,
            'pending' => $pending,
            'mine' => $mine,
            'scheduled' => BlogDetail::where('status', BlogDetail::SCHEDULED)
                ->when(!$can('articles.edit_all'), fn ($q) => $q->where('author_id', $user->id))
                ->with('author:id,name')->orderBy('scheduled_at')->take(5)->get()
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'by' => $b->author?->name, 'at' => $b->scheduled_at?->toIso8601String()]),
            'recentMessages' => $can('enquiries.view') ? Message::latest()->take(6)->get(['id', 'name', 'email', 'phone', 'subject', 'is_read', 'created_at']) : [],
            'seoByType' => $seo['by_type'],
            'quick' => array_values(array_filter([
                $can('articles.create') ? ['label' => 'Write article', 'href' => '/admin/blogs?new=1', 'primary' => true] : null,
                $can('services.view') ? ['label' => 'Services', 'href' => '/admin/services'] : null,
                $can('media.create') ? ['label' => 'Upload media', 'href' => '/admin/media'] : null,
                $can('page_seo.view') ? ['label' => 'Page SEO', 'href' => '/admin/page-seo'] : null,
            ])),
        ]);
    }

    public function checklist()
    {
        return Inertia::render('Admin/Checklist', ['groups' => \App\Support\SiteChecklist::groups()]);
    }

    /** Last 30 days from Google, only when a report is already cached (the dashboard never waits on Google). */
    private function insights(): ?array
    {
        $gsc = cache('google.gsc.report');
        $ga4 = cache('google.ga4.report');
        if (!$gsc && !$ga4) {
            return \App\Support\Google\GoogleApi::connected() ? ['empty' => true] : null;
        }

        return [
            'clicks' => $gsc['now']['clicks'] ?? null,
            'clicks_before' => $gsc['before']['clicks'] ?? null,
            'position' => $gsc['now']['position'] ?? null,
            'users' => isset($ga4['now']) ? (int) $ga4['now']['activeUsers'] : null,
            'users_before' => isset($ga4['before']) ? (int) $ga4['before']['activeUsers'] : null,
            'leads' => isset($ga4['events']) ? (int) collect($ga4['events'])->sum('eventCount') : null,
        ];
    }
}
