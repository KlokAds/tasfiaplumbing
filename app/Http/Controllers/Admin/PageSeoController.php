<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageSeo;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PageSeoController extends Controller
{
    public function index()
    {
        $stored = PageSeo::all()->keyBy('key');
        $audit = config('seo.audit');
        $brand = Str::lower(SiteSetting::get('business.brand_name', 'Tasfia'));
        $titles = $stored->map(fn ($p) => Str::lower(trim((string) $p->meta_title)))->filter()->countBy();

        $pages = collect(config('seo.static_pages'))->map(function ($page, $key) use ($stored, $audit, $brand, $titles) {
            $row = $stored->get($key);
            $title = (string) $row?->meta_title;
            $desc = (string) $row?->meta_desc;

            $issues = [];
            if ($title === '') {
                $issues[] = ['level' => 'warning', 'message' => 'No meta title; the default "' . $page['title'] . '" is used.'];
            } elseif (mb_strlen($title) > $audit['title_max']) {
                $issues[] = ['level' => 'warning', 'message' => 'Meta title over ' . $audit['title_max'] . ' characters.'];
            }
            if ($title !== '' && ($titles[Str::lower(trim($title))] ?? 0) > 1) {
                $issues[] = ['level' => 'error', 'message' => 'Same title as another page.'];
            }
            if ($title !== '' && !Str::contains(Str::lower($title), $brand) && Str::contains(Str::lower($title), 'tasfia')) {
                $issues[] = ['level' => 'warning', 'message' => 'Brand name differs from the one in settings (keep one brand name everywhere).'];
            }
            if ($desc === '') {
                $issues[] = ['level' => 'warning', 'message' => 'No meta description; the site default is used.'];
            } elseif (mb_strlen($desc) > $audit['desc_max'] || mb_strlen($desc) < $audit['desc_min']) {
                $issues[] = ['level' => 'warning', 'message' => 'Meta description should be ' . $audit['desc_min'] . '–' . $audit['desc_max'] . ' characters.'];
            }
            if ($row?->noindex) {
                $issues[] = ['level' => 'warning', 'message' => 'Hidden from Google (noindex).'];
            }
            $errors = count(array_filter($issues, fn ($i) => $i['level'] === 'error'));

            return [
                'key' => $key,
                'label' => $page['label'],
                'path' => $page['path'],
                'default_title' => $page['title'],
                'meta_title' => $row?->meta_title,
                'meta_desc' => $row?->meta_desc,
                'focus_keyword' => $row?->focus_keyword,
                'og_image' => $row?->og_image,
                'canonical' => $row?->canonical,
                'noindex' => (bool) $row?->noindex,
                'updated_at' => $row?->updated_at?->toIso8601String(),
                'seo' => ['score' => max(0, 100 - $errors * 20 - (count($issues) - $errors) * 7), 'errors' => $errors, 'issues' => $issues],
            ];
        })->values();

        return Inertia::render('Admin/PageSeo/Index', [
            'pages' => $pages,
            'titleSuffix' => SiteSetting::get('seo.title_suffix', ''),
            'brand' => SiteSetting::get('business.brand_name') ?: config('app.name'),
            'defaultDesc' => SiteSetting::get('seo.default_meta_desc', ''),
        ]);
    }

    public function update(Request $request, string $key)
    {
        abort_unless(array_key_exists($key, config('seo.static_pages')), 404);

        $data = $request->validate([
            'meta_title' => 'nullable|string|max:255',
            'meta_desc' => 'nullable|string|max:500',
            'focus_keyword' => 'nullable|string|max:120',
            'og_image' => 'nullable|string|max:512',
            'canonical' => 'nullable|url|max:255',
            'noindex' => 'boolean',
        ]);

        PageSeo::updateOrCreate(['key' => $key], $data + ['updated_by' => $request->user()->id]);

        return redirect()->back()->with('success', config("seo.static_pages.{$key}.label") . ' SEO saved.');
    }
}
