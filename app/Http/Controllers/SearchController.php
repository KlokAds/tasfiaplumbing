<?php

namespace App\Http\Controllers;

use App\Models\BlogDetail;
use App\Models\Location;
use App\Models\ProjectDetail;
use App\Models\ServiceDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * One search across services, articles, projects and areas. Only public content is
 * searched (live services, published articles, visible projects, active locations).
 * Results pages are noindex (see PageMeta) so they never compete with real pages.
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = $this->query($request);

        return Inertia::render('Frontend/Search', [
            'q' => $q,
            'results' => $q === '' ? null : $this->search($q, 12),
        ]);
    }

    /** Live suggestions for the search box in the header. */
    public function suggest(Request $request)
    {
        $q = $this->query($request);

        return response()->json($q === '' ? [] : $this->search($q, 4));
    }

    private function query(Request $request): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $request->input('q'))), 80, '');
    }

    private function search(string $q, int $limit): array
    {
        $words = collect(explode(' ', Str::lower($q)))->filter(fn ($w) => mb_strlen($w) >= 2)->take(6)->values();
        if ($words->isEmpty()) {
            return ['services' => [], 'articles' => [], 'projects' => [], 'locations' => [], 'total' => 0];
        }

        // Every word must appear in one of the fields; titles that match rank first.
        $match = function (Builder $query, array $fields, string $title) use ($words, $q) {
            foreach ($words as $w) {
                $query->where(function ($inner) use ($fields, $w) {
                    foreach ($fields as $f) {
                        $inner->orWhere($f, 'like', '%' . $w . '%');
                    }
                });
            }

            return $query->orderByRaw("CASE WHEN LOWER({$title}) LIKE ? THEN 0 WHEN LOWER({$title}) LIKE ? THEN 1 ELSE 2 END", [Str::lower($q) . '%', '%' . Str::lower($q) . '%']);
        };

        $services = $match(ServiceDetail::where('is_active', true), ['name', 'short_summary', 'focus_keyword'], 'name')
            ->take($limit)->get(['id', 'name', 'slug', 'image', 'short_summary'])
            ->map(fn ($s) => ['title' => $s->name, 'url' => $s->publicPath(), 'image' => $s->image, 'text' => $s->short_summary]);

        $articles = $match(BlogDetail::published(), ['name', 'excerpt', 'focus_keyword'], 'name')
            ->take($limit)->get(['id', 'name', 'slug', 'image', 'excerpt'])
            ->map(fn ($b) => ['title' => $b->name, 'url' => $b->publicPath(), 'image' => $b->image, 'text' => $b->excerpt]);

        $projects = $match(ProjectDetail::visible(), ['name', 'area', 'summary'], 'name')
            ->take($limit)->get(['id', 'name', 'image', 'area', 'summary'])
            ->map(fn ($p) => ['title' => $p->name, 'url' => '/projects?q=' . rawurlencode($p->name), 'image' => $p->image, 'text' => $p->summary ?: $p->area]);

        $locations = $match(Location::where('is_active', true), ['name', 'intro'], 'name')
            ->take($limit)->get(['id', 'name', 'slug', 'intro'])
            ->map(fn ($l) => ['title' => $l->name, 'url' => $l->publicPath(), 'image' => null, 'text' => $l->intro]);

        return [
            'services' => $services->values(),
            'articles' => $articles->values(),
            'projects' => $projects->values(),
            'locations' => $locations->values(),
            'total' => $services->count() + $articles->count() + $projects->count() + $locations->count(),
        ];
    }
}
