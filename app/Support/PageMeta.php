<?php

namespace App\Support;

use App\Models\BlogDetail;
use App\Models\PageSeo;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Title, description, canonical, robots and Open Graph for every public page.
 * Rendered in the initial HTML (crawlers that do not run JavaScript see it) and
 * shared with Inertia so the client-side <Head> shows exactly the same values.
 */
class PageMeta
{
    public static function resolve(Request $request): array
    {
        if ($request->attributes->has('page_meta')) {
            return $request->attributes->get('page_meta');
        }

        $s = SiteSetting::values();
        $brand = $s['business.brand_name'] ?: config('app.name');
        $suffix = $s['seo.title_suffix'] ?? '';
        $route = $request->route()?->getName();
        $slug = $request->route('slug');

        $title = null;
        $desc = null;
        $image = null;
        $canonical = null;
        $noindex = false;
        $type = 'website';

        if ($route === 'service.detail' && $slug && ($m = ServiceDetail::where('slug', $slug)->first())) {
            $title = $m->meta_title ?: $m->name;
            $desc = $m->meta_desc ?: ($m->short_summary ?: $m->desc);
            $image = $m->og_image ?: $m->image;
            $canonical = $m->canonical;
            $noindex = $m->noindex || !$m->is_active;
        } elseif ($route === 'blog.detail' && $slug && ($m = BlogDetail::where('slug', $slug)->first())) {
            $title = $m->meta_title ?: $m->name;
            $desc = $m->meta_desc ?: ($m->excerpt ?: $m->desc);
            $image = $m->og_image ?: $m->image;
            $canonical = $m->canonical;
            $noindex = $m->noindex || $m->status !== BlogDetail::PUBLISHED;
            $type = 'article';
        } elseif ($route === 'service.category' && ($m = \App\Models\ServiceCategory::where('slug', $request->route('category'))->first())) {
            $title = $m->meta_title ?: $m->name . ' in Singapore';
            $desc = $m->meta_desc ?: ($m->intro ?: $m->description);
            $image = $m->image;
            $canonical = $m->canonical;
            $noindex = $m->noindex || !$m->is_active;
        } elseif ($route === 'location.detail' && $slug && ($m = \App\Models\Location::where('slug', $slug)->first())) {
            $title = $m->meta_title ?: "Plumbing Services in {$m->name}";
            $desc = $m->meta_desc ?: ($m->intro ?: $m->description);
            $image = $m->image;
            $canonical = $m->canonical;
            $noindex = $m->noindex || !$m->is_active;
        } elseif ($route === 'search') {
            $title = $request->filled('q') ? 'Search: ' . Str::limit((string) $request->input('q'), 50) : 'Search';
            $noindex = true;
        } else {
            $key = collect(config('seo.static_pages'))->search(fn ($p) => $p['route'] === $route);
            if ($key !== false) {
                $page = PageSeo::forKey($key);
                $title = $page?->meta_title ?: config("seo.static_pages.{$key}.title");
                $desc = $page?->meta_desc;
                $image = $page?->og_image;
                $canonical = $page?->canonical;
                $noindex = (bool) $page?->noindex;
            }
        }

        $title = trim((string) $title) ?: $brand;
        // A title that already names the brand ("… | Tasfia Engineering Services") gets no second brand suffix.
        $brandCore = trim(preg_replace('/\s+(singapore|sg|pte\.?\s*ltd\.?)$/i', '', $brand));
        // The suffix is only added while the full title still fits Google's ~60 characters.
        if ($suffix && !Str::contains(Str::lower($title), Str::lower($brandCore ?: $brand))
            && mb_strlen(rtrim($title) . ' ' . ltrim($suffix)) <= (int) config('seo.audit.title_max', 60)) {
            $title = rtrim($title) . ' ' . ltrim($suffix);
        }

        $desc = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) ($desc ?: $s['seo.default_meta_desc']))))), 160, '…');
        $base = rtrim(config('app.url'), '/');
        $image = $image ?: ($s['seo.default_og_image'] ?: '/logo.png');
        $image = \App\Support\ResponsiveImage::publicUrl($image, $base);

        $meta = [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical ?: $base . '/' . ltrim($request->path(), '/'),
            'robots' => ($noindex || !app()->environment('production')) ? 'noindex, follow' : 'index, follow, max-image-preview:large',
            'image' => $image,
            'type' => $type,
            'site_name' => $brand,
        ];
        $request->attributes->set('page_meta', $meta);

        return $meta;
    }
}
