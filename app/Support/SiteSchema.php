<?php

namespace App\Support;

use App\Models\ContactContent;
use App\Models\SiteSetting;

/**
 * Site-wide JSON-LD (LocalBusiness + WebSite) and robots.txt, generated from
 * Admin > SEO & Business Settings and Admin > Contact & Map. Nothing is hand-written.
 */
class SiteSchema
{
    public static function graph(): array
    {
        $s = SiteSetting::values();
        $contact = null;
        try {
            $contact = ContactContent::first();
        } catch (\Throwable $e) {
        }

        $base = rtrim(config('app.url'), '/');
        $brand = $s['business.brand_name'] ?: config('app.name');

        $business = array_filter([
            '@type' => $s['business.schema_type'] ?: 'LocalBusiness',
            '@id' => $base . '/#business',
            'name' => $brand,
            'legalName' => $s['business.legal_name'] ?: null,
            'alternateName' => $s['business.alternate_name'] ?: null,
            'url' => $base . '/',
            'logo' => $base . '/logo.png',
            'image' => self::absolute($s['seo.default_og_image'] ?: '/logo.png', $base),
            // E.164 (+6593730360) so Google and phones read it correctly.
            'telephone' => \App\Http\Middleware\HandleInertiaRequests::formatPhone($contact?->phone)[1],
            'email' => $contact?->email,
            'priceRange' => $s['business.price_range'] ?: null,
            'address' => self::address($contact?->address, $s['business.postal_code']),
            'geo' => ($s['business.latitude'] && $s['business.longitude']) ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $s['business.latitude'],
                'longitude' => (float) $s['business.longitude'],
            ] : null,
            'areaServed' => $s['business.area_served'] ? ['@type' => 'Country', 'name' => $s['business.area_served']] : null,
            'openingHoursSpecification' => self::hours($s) ?: null,
            'sameAs' => array_values(array_unique(array_filter([...array_values(Socials::links()), $s['google.gbp_maps_uri'] ?? null]))) ?: null,
            'hasMap' => ($s['business.gbp_url'] ?: ($s['google.gbp_maps_uri'] ?? null)) ?: null,
            'foundingDate' => preg_match('/^(19|20)\d{2}$/', (string) ($s['business.founded_year'] ?? '')) ? $s['business.founded_year'] : null,
            'foundingDate' => preg_match('/^(19|20)\d{2}$/', (string) ($s['business.founded_year'] ?? '')) ? $s['business.founded_year'] : null,
            'foundingDate' => preg_match('/^(19|20)\d{2}$/', (string) ($s['business.founded_year'] ?? '')) ? $s['business.founded_year'] : null,
            'foundingDate' => preg_match('/^(19|20)\d{2}$/', (string) ($s['business.founded_year'] ?? '')) ? $s['business.founded_year'] : null,
            'identifier' => $s['business.uen'] ? ['@type' => 'PropertyValue', 'propertyID' => 'UEN', 'value' => $s['business.uen']] : null,
        ]);

        $website = array_filter([
            '@type' => 'WebSite',
            '@id' => $base . '/#website',
            'url' => $base . '/',
            'name' => $brand,
            'alternateName' => $s['business.alternate_name'] ?: null,
            'publisher' => ['@id' => $base . '/#business'],
        ]);

        return ['@context' => 'https://schema.org', '@graph' => [$business, $website]];
    }

    public static function robots(): string
    {
        $s = SiteSetting::values();
        $lines = [];

        if (!app()->environment('production')) {
            $lines[] = '# Non-production environment (' . app()->environment() . '): everything blocked.';
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow: /';

            return implode("\n", $lines) . "\n";
        }

        $lines[] = 'User-agent: *';
        $lines[] = 'Disallow: /admin';
        $lines[] = 'Disallow: /login';
        foreach (preg_split('/\r\n|\r|\n/', (string) $s['robots.extra']) as $rule) {
            if (trim($rule) !== '') {
                $lines[] = trim($rule);
            }
        }

        $block = function (array $agents) use (&$lines) {
            foreach ($agents as $agent) {
                $lines[] = '';
                $lines[] = 'User-agent: ' . $agent;
                $lines[] = 'Disallow: /';
            }
        };
        if ($s['robots.allow_ai_search'] !== '1') {
            $block(['OAI-SearchBot', 'PerplexityBot', 'ChatGPT-User']);
        }
        if ($s['robots.allow_ai_training'] !== '1') {
            $block(['GPTBot', 'Google-Extended', 'CCBot']);
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . rtrim(config('app.url'), '/') . '/sitemap.xml';

        return implode("\n", $lines) . "\n";
    }

    private static function address(?string $raw, ?string $postal): ?array
    {
        if (!$raw) {
            return null;
        }
        $street = trim(preg_replace('/\s*,?\s*Singapore\s*\d{6}\s*$/i', '', $raw), " ,");
        $street = preg_replace('/\s*,\s*/', ', ', $street);
        if (!$postal && preg_match('/(\d{6})\s*$/', $raw, $m)) {
            $postal = $m[1];
        }

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'addressLocality' => 'Singapore',
            'postalCode' => $postal,
            'addressCountry' => 'SG',
        ]);
    }

    private static function hours(array $s): array
    {
        $groups = [
            'hours.mon_fri' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'hours.saturday' => ['Saturday'],
            'hours.sunday' => ['Sunday'],
        ];
        $specs = [];
        foreach ($groups as $key => $days) {
            if (preg_match('/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', trim((string) ($s[$key] ?? '')), $m)) {
                $specs[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days, 'opens' => $m[1], 'closes' => $m[2]];
            }
        }

        return $specs;
    }

    private static function absolute(string $path, string $base): string
    {
        return ResponsiveImage::publicUrl($path, $base);
    }
}
