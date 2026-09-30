<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Support\SeoAudit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class SeoHealthController extends Controller
{
    public function index(Request $request)
    {
        $overview = SeoAudit::overview($request->boolean('refresh'));

        $items = collect($overview['items'])
            ->when($request->filled('type'), fn ($c) => $c->where('type', $request->input('type')))
            ->when($request->filled('code'), fn ($c) => $c->filter(
                fn ($i) => collect($i['issues'])->contains('code', $request->input('code'))
            ))
            ->when($request->input('level') === 'error', fn ($c) => $c->where('errors', '>', 0))
            ->values();

        $perPage = PerPage::get($request, 25);
        $page = max(1, $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Admin/Seo/Health', [
            'byType' => $overview['by_type'],
            'byCode' => $overview['by_code'],
            'items' => $paginator,
            'generatedAt' => $overview['generated_at'],
            'filters' => $request->only(['type', 'code', 'level', 'per_page']),
        ]);
    }
}
