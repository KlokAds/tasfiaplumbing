<?php

namespace App\Support;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AboutContent;
use App\Models\BlogDetail;
use App\Models\ContactContent;
use App\Models\FeedBackContent;
use App\Models\Footer;
use App\Models\HomeCounter;
use App\Models\HomeHero;
use App\Models\Location;
use App\Models\NotFoundLog;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Support\Google\GoogleApi;
use Illuminate\Support\Facades\Cache;

/**
 * "Is the website complete?" One list the owner can work through: every item says what is
 * wrong, why it matters, where to fix it and which public page shows it.
 */
class SiteChecklist
{
    /** @return array<int, array{group: string, items: array}> */
    public static function groups(): array
    {
        $s = fn (string $k) => SiteSetting::get($k);
        $contact = ContactContent::first();
        $footer = Footer::first();
        [$phone, $tel] = HandleInertiaRequests::formatPhone($contact?->phone);
        $waNumber = Socials::whatsappNumber($contact?->phone);
        $words = fn (?string $html) => str_word_count(strip_tags((string) $html));

        $hero = HomeHero::orderBy('id')->first();
        $about = AboutContent::first();
        $services = ServiceDetail::where('is_active', true);
        $serviceCount = (clone $services)->count();
        $noSummary = (clone $services)->where(fn ($q) => $q->whereNull('short_summary')->orWhere('short_summary', ''))->count();
        $noPrice = (clone $services)->whereDoesntHave('prices', fn ($q) => $q->where('is_active', true))->count();
        $noCategory = (clone $services)->whereNull('category_id')->count();
        $noImage = (clone $services)->where(fn ($q) => $q->whereNull('image')->orWhere('image', ''))->count();
        $locations = Location::where('is_active', true)->get(['id', 'description']);
        $goodLocations = $locations->filter(fn ($l) => $words($l->description) >= 200)->count();
        $unlinked = BlogDetail::published()->whereNull('primary_service_id')->count();
        $reviews = ReviewFeed::build(3);
        $seo = collect(SeoAudit::overview()['by_type']);
        $seoAvg = $seo->sum('total') ? (int) round($seo->sum(fn ($t) => $t['avg_score'] * $t['total']) / $seo->sum('total')) : 0;
        $seen = Cache::get('system.scheduler_seen');
        $mail = SystemSettings::mail();
        $env = app()->environment();

        $groups = [
            'Business details' => [
                self::item('phone', 'Phone number', (bool) $tel && str_starts_with((string) $tel, '+65'), 'must',
                    $tel ? "Shown as {$phone}." : 'No phone number. Visitors cannot call and WhatsApp buttons have no number.', '/admin/settings/contact', '/contact'),
                self::item('whatsapp', 'WhatsApp number', (bool) $waNumber, 'must',
                    $waNumber ? "Quote buttons open WhatsApp chat with +{$waNumber}." : 'No WhatsApp number or phone, so the quote buttons cannot open WhatsApp.', '/admin/settings/contact', '/contact'),
                self::item('socials', 'Social profiles', count(Socials::links()) >= 2, 'should',
                    count(Socials::links()) . ' profiles linked (' . (implode(', ', array_keys(Socials::links())) ?: 'none') . '). They show as footer icons and help Google trust the business.', '/admin/settings/contact', '/'),
                self::item('gbp', 'Google Business Profile link', filled($s('business.gbp_url')) || filled($s('google.gbp_maps_uri')), 'should', 'Links your website and your Google Maps listing (reviews, local ranking).', '/admin/settings/contact', null),
                self::item('email', 'Business email', filled($contact?->email), 'must', 'Enquiries are emailed here.', '/admin/settings/contact', '/contact'),
                self::item('address', 'Address', filled($contact?->address), 'should', 'Shown in the footer and used by Google for local search.', '/admin/settings/contact', '/contact'),
                self::item('legal', 'Legal company name', filled($s('business.legal_name')), 'should', 'Used in the copyright line and the Organisation schema.', '/admin/settings/business'),
                self::item('founded', 'Year founded', (bool) preg_match('/^(19|20)\d{2}$/', (string) $s('business.founded_year')), 'should', 'Shows "Serving Singapore since …" and the copyright years.', '/admin/settings/business', '/about'),
                self::item('hours', 'Opening hours', filled($s('hours.mon_fri')), 'should', 'Shown on the contact page and to Google.', '/admin/settings/business', '/contact'),
                self::item('logo', 'Logo uploaded', filled($footer?->main_logo), 'should', 'Header, footer and search results.', '/admin/settings/footer', '/'),
            ],
            'Homepage & About' => [
                self::item('hero', 'Homepage headline', $hero && mb_strlen((string) $hero->title) >= 15 && !str_contains((string) $hero->title, '!!'), 'must',
                    $hero?->title ? '"' . $hero->title . '". Say what you do and where, e.g. "Plumbing services in Singapore".' : 'No headline yet.', '/admin/hero', '/'),
                self::item('hero_image', 'Homepage photo', filled($hero?->img), 'should', 'A real job photo behind the headline.', '/admin/hero', '/'),
                self::item('counters', 'Numbers (counters)', HomeCounter::count() >= 3, 'should', 'Projects done, years, team size. Only use numbers you can prove.', '/admin/home-static', '/'),
                self::item('about', 'About page text', $words($about?->short_desc) >= 120, 'must',
                    $words($about?->short_desc) . ' words. Aim for 150–300: when you started, what you do, where, licences, how you work.', '/admin/about', '/about'),
            ],
            'Services & areas' => [
                self::item('summaries', 'Service summaries', $serviceCount && !$noSummary, 'should',
                    $noSummary ? "{$noSummary} of {$serviceCount} services have no short summary (the text on service cards and in Google)." : 'Every service has a summary.', '/admin/services', '/services'),
                self::item('prices', 'Starting prices', $serviceCount && !$noPrice, 'should',
                    $noPrice ? "{$noPrice} services show no price. \"From S\$…\" gets more enquiries and is used by Google and AI answers." : 'Every service has a price.', '/admin/pricing', '/pricing'),
                self::item('categories', 'Service categories', $serviceCount && !$noCategory, 'should',
                    $noCategory ? "{$noCategory} services are not in a category, so the Services menu shows one long list." : 'Services are grouped.', '/admin/services', '/services'),
                self::item('service_images', 'Service photos', $serviceCount && !$noImage, 'should', $noImage ? "{$noImage} services have no photo." : 'Every service has a photo.', '/admin/services', '/services'),
                self::item('locations', 'Area pages', $goodLocations >= 6, 'should',
                    $locations->count() . ' area pages, ' . $goodLocations . ' with 200+ words. Aim for 6–10 well-written pages (Tampines, Jurong, Woodlands …).', '/admin/locations', '/locations'),
                self::item('articles', 'Articles linked to a service', !$unlinked, 'should',
                    $unlinked ? "{$unlinked} published articles are not linked to a service, so they do not pass authority to your service pages." : 'Every article supports a service.', '/admin/blogs?filter=no_service', '/blogs'),
            ],
            'Trust & reviews' => [
                self::item('reviews', 'Reviews on the website', $reviews['count'] >= 3, 'must',
                    $reviews['google'] ? "Google: {$reviews['google']['rating']}★ from {$reviews['google']['total']} reviews." : $reviews['count'] . ' reviews shown. Connect Google reviews or add reviews customers sent you.', '/admin/reviews', '/reviews'),
                self::item('own_reviews', 'Own reviews with job & area', FeedBackContent::count() === 0 || FeedBackContent::whereNotNull('job')->count() > 0, 'should', 'Add the job type and area to your own reviews ("Bathroom renovation · Tampines").', '/admin/reviews', '/reviews'),
            ],
            'Google & SEO' => [
                self::item('google', 'Google connected', GoogleApi::connected() && filled($s('google.gsc_property')), 'should', 'Shows search clicks, visitors and index status here in admin.', '/admin/insights/google', null),
                self::item('seo_score', 'Average SEO score', $seoAvg >= 80, 'should', "Now {$seoAvg}/100. Fix the pages with errors first.", '/admin/seo/health', null),
                self::item('share_image', 'Default share image', filled($s('seo.default_og_image')), 'should', 'The picture shown when a link is shared on WhatsApp or Facebook.', '/admin/seo/settings', null),
                self::item('404s', 'Broken links (404)', NotFoundLog::where('is_resolved', false)->count() === 0, 'should', NotFoundLog::where('is_resolved', false)->count() . ' old URLs lead nowhere. Redirect them to keep their Google ranking.', '/admin/redirects?tab=404', null),
                self::item('tracking', 'Tag Manager', filled($footer?->g_tag) && blank($footer?->g_a_tag), 'should',
                    blank($footer?->g_tag) ? 'No Tag Manager ID.' : (filled($footer?->g_a_tag) ? 'Both Tag Manager and GA4 are set. Clear GA4 and add it inside Tag Manager.' : 'Tag Manager loads Analytics and ads pixels.'), '/admin/settings/footer', null),
            ],
            'Server & email' => [
                self::item('smtp', 'Email server', ($mail['enabled'] ?? false) && filled($mail['host'] ?? null) || config('mail.default') === 'smtp', 'must', 'Without it enquiry emails never arrive.', '/admin/system/settings?tab=mail', null),
                self::item('https', 'HTTPS', str_starts_with((string) config('app.url'), 'https://'), 'must', 'APP_URL must start with https:// on the live server.', '/admin/system/settings?tab=server', null),
                self::item('production', 'Live mode', $env === 'production' && !config('app.debug'), 'must', "Now \"{$env}\"" . (config('app.debug') ? ' with error details on' : '') . '. Set APP_ENV=production and APP_DEBUG=false. (Google is blocked by robots.txt until then.)', '/admin/system/settings?tab=server', null),
                self::item('cron', 'Scheduled tasks', $seen && time() - (int) $seen < 180, 'must', 'The server cron publishes scheduled articles and syncs Google every night.', '/admin/system/settings?tab=server', null),
                self::item('maintenance', 'Website is online', !SystemSettings::maintenanceOn(), 'must', 'Maintenance mode is on: visitors cannot see the site.', '/admin/system/settings', '/'),
            ],
        ];

        return collect($groups)->map(fn ($items, $group) => ['group' => $group, 'items' => $items])->values()->all();
    }

    /** @return array{done: int, total: int, must_open: int} */
    public static function score(): array
    {
        $items = collect(self::groups())->flatMap(fn ($g) => $g['items']);

        return [
            'done' => $items->where('ok', true)->count(),
            'total' => $items->count(),
            'must_open' => $items->where('ok', false)->where('level', 'must')->count(),
        ];
    }

    private static function item(string $key, string $label, bool $ok, string $level, string $detail, ?string $fix, ?string $view = null): array
    {
        return compact('key', 'label', 'ok', 'level', 'detail', 'fix', 'view');
    }
}
