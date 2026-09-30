<?php

namespace App\Support;

use App\Models\BlogDetail;
use App\Models\Location;
use App\Models\ServiceCategory;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * JSON-LD for a single page (Service, Article, FAQPage, BreadcrumbList …).
 * Controllers call PageSchema::set(...) and the layout prints it server-side next to the
 * site-wide LocalBusiness graph, so crawlers that skip JavaScript still see it.
 */
class PageSchema
{
    private const ATTR = 'page_schema';

    public static function set(?array ...$nodes): void
    {
        request()->attributes->set(self::ATTR, array_values(array_filter($nodes)));
    }

    public static function current(): ?array
    {
        $nodes = request()->attributes->get(self::ATTR);

        return $nodes ? ['@context' => 'https://schema.org', '@graph' => $nodes] : null;
    }

    /** @param array<int, array{0: string, 1: string}> $trail [name, path] pairs after Home */
    public static function breadcrumbs(array $trail): array
    {
        $items = [['name' => 'Home', 'path' => '/']];
        foreach ($trail as [$name, $path]) {
            $items[] = ['name' => $name, 'path' => $path];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($c, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => self::url($c['path']),
            ])->all(),
        ];
    }

    public static function faq(Collection|array $faqs): ?array
    {
        if (!SiteSetting::get('seo.faq_schema', '1')) {
            return null;
        }
        $faqs = collect($faqs)->filter(fn ($f) => filled($f['question'] ?? $f->question ?? null));
        if ($faqs->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn ($f) => [
                '@type' => 'Question',
                'name' => trim(strip_tags(is_array($f) ? $f['question'] : $f->question)),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags(is_array($f) ? $f['answer'] : $f->answer))],
            ])->values()->all(),
        ];
    }

    public static function service(ServiceDetail $s): array
    {
        $prices = $s->relationLoaded('prices') ? $s->prices : collect();
        $offers = $prices->filter(fn ($p) => $p->is_active ?? true)->map(fn ($p) => array_filter([
            '@type' => 'Offer',
            'name' => $p->item,
            'priceCurrency' => 'SGD',
            'priceSpecification' => array_filter([
                '@type' => 'PriceSpecification',
                'priceCurrency' => 'SGD',
                'minPrice' => (float) $p->price_from,
                'maxPrice' => $p->price_to ? (float) $p->price_to : null,
                'unitText' => $p->unit ?: null,
            ]),
        ]))->values();

        return array_filter([
            '@type' => 'Service',
            '@id' => self::url($s->publicPath()) . '#service',
            'name' => $s->name,
            'serviceType' => $s->name,
            'description' => Str::limit(trim(strip_tags((string) ($s->short_summary ?: $s->desc))), 300, '…'),
            'url' => self::url($s->publicPath()),
            'image' => $s->image ? ResponsiveImage::publicUrl($s->image, config('app.url')) : null,
            'provider' => ['@id' => self::url('/') . '#business'],
            'areaServed' => ['@type' => 'Country', 'name' => 'Singapore'],
            'category' => $s->category?->name,
            'offers' => $offers->isNotEmpty() ? $offers->all() : null,
        ]);
    }

    public static function article(BlogDetail $b): array
    {
        $author = $b->author;

        return array_filter([
            '@type' => 'Article',
            '@id' => self::url($b->publicPath()) . '#article',
            'headline' => Str::limit($b->name, 110, ''),
            'description' => Str::limit(trim(strip_tags((string) ($b->excerpt ?: $b->desc))), 300, '…'),
            'image' => $b->image ? [ResponsiveImage::publicUrl($b->image, config('app.url'))] : null,
            'datePublished' => optional($b->published_at ?? $b->created_at)->toIso8601String(),
            'dateModified' => optional($b->content_updated_at ?? $b->updated_at)->toIso8601String(),
            'author' => array_filter([
                '@type' => 'Person',
                'name' => $author?->name ?? ($b->auth_name ?: SiteSetting::get('business.brand_name')),
                'jobTitle' => $author?->job_title ?: null,
                'description' => $author?->bio ?: null,
                'sameAs' => $author?->social_url ?: null,
            ]),
            'publisher' => ['@id' => self::url('/') . '#business'],
            'mainEntityOfPage' => self::url($b->publicPath()),
            'about' => $b->primaryService ? ['@id' => self::url($b->primaryService->publicPath()) . '#service'] : null,
        ]);
    }

    public static function location(Location $l): array
    {
        return array_filter([
            '@type' => 'WebPage',
            '@id' => self::url($l->publicPath()) . '#page',
            'name' => $l->meta_title ?: $l->name,
            'url' => self::url($l->publicPath()),
            'about' => ['@id' => self::url('/') . '#business'],
            'spatialCoverage' => array_filter([
                '@type' => 'Place',
                'name' => $l->name . ', Singapore',
                'geo' => ($l->latitude && $l->longitude) ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $l->latitude, 'longitude' => (float) $l->longitude] : null,
            ]),
        ]);
    }

    public static function itemList(string $name, Collection $items): array
    {
        return [
            '@type' => 'ItemList',
            'name' => $name,
            'itemListElement' => $items->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => self::url($item->publicPath()),
                'name' => $item->name,
            ])->all(),
        ];
    }

    public static function category(ServiceCategory $c, Collection $services): array
    {
        return self::itemList($c->name, $services);
    }

    public static function url(string $path): string
    {
        return rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}
