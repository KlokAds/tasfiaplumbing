<?php

namespace App\Support;

use App\Models\BlogDetail;
use App\Models\Location;
use App\Models\ServiceCategory;
use App\Models\ServiceDetail;
use Illuminate\Support\Collection;

/** Every public, indexable URL (used by sitemap.xml and the index-status checker). */
class Sitemap
{
    public const STATIC = ['/', '/about', '/services', '/pricing', '/locations', '/projects', '/blogs', '/reviews', '/contact', '/privacy-policy', '/terms-of-service'];

    /** @return Collection<int, array{loc: string, lastmod: ?\DateTimeInterface, type: string, title: ?string}> */
    public static function urls(): Collection
    {
        $indexable = fn ($q) => $q->where('is_active', true)->where('noindex', false)->whereNull('canonical');
        $static = collect(self::STATIC)
            ->reject(fn ($p) => $p === '/pricing' && !\App\Models\Price::where('is_active', true)->exists()); // no prices yet
        $urls = $static->values()->map(fn ($p) => ['loc' => $p, 'lastmod' => null, 'type' => 'Page', 'title' => null]);

        foreach (ServiceCategory::where($indexable)->get(['name', 'slug', 'updated_at', 'content_updated_at']) as $c) {
            $urls->push(['loc' => $c->publicPath(), 'lastmod' => $c->content_updated_at ?? $c->updated_at, 'type' => 'Category', 'title' => $c->name]);
        }
        foreach (ServiceDetail::where($indexable)->get(['name', 'slug', 'updated_at', 'content_updated_at']) as $s) {
            $urls->push(['loc' => $s->publicPath(), 'lastmod' => $s->content_updated_at ?? $s->updated_at, 'type' => 'Service', 'title' => $s->name]);
        }
        foreach (Location::where($indexable)->get(['name', 'slug', 'updated_at', 'content_updated_at']) as $l) {
            $urls->push(['loc' => $l->publicPath(), 'lastmod' => $l->content_updated_at ?? $l->updated_at, 'type' => 'Location', 'title' => $l->name]);
        }
        foreach (BlogDetail::where($indexable)->where('status', BlogDetail::PUBLISHED)->get(['name', 'slug', 'updated_at', 'content_updated_at']) as $b) {
            $urls->push(['loc' => $b->publicPath(), 'lastmod' => $b->content_updated_at ?? $b->updated_at, 'type' => 'Article', 'title' => $b->name]);
        }

        return $urls;
    }
}
