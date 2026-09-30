<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Switches the owner controls from Admin → System → Site settings, without touching .env:
 * maintenance mode, temporary debug mode, SMTP mail and country access.
 */
class SystemSettings
{
    /** Search engines and link previews are never blocked, so SEO and WhatsApp previews keep working. */
    public const BOT_PATTERN = '/googlebot|google-inspectiontool|googleother|adsbot-google|mediapartners-google|storebot-google|bingbot|bingpreview|applebot|duckduckbot|yandex|baiduspider|facebookexternalhit|facebot|whatsapp|twitterbot|linkedinbot|slackbot|telegrambot|discordbot|pinterest/i';

    public const DEBUG_MINUTES = 30;

    public static function maintenanceOn(): bool
    {
        return (bool) self::safe(fn () => SiteSetting::get('system.maintenance'));
    }

    public static function debugUntil(): ?int
    {
        $until = (int) self::safe(fn () => SiteSetting::get('system.debug_until'));

        return $until > time() ? $until : null;
    }

    public static function isBot(Request $request): bool
    {
        return (bool) preg_match(self::BOT_PATTERN, (string) $request->userAgent());
    }

    /**
     * Visitor country (ISO code) from the CDN in front of the site. Cloudflare sends CF-IPCountry
     * for free; other proxies can send X-Country-Code. Null when the server cannot tell.
     */
    public static function country(Request $request): ?string
    {
        $code = strtoupper(trim((string) ($request->header('CF-IPCountry') ?: $request->header('X-Country-Code'))));

        return preg_match('/^[A-Z]{2}$/', $code) && !in_array($code, ['XX', 'T1'], true) ? $code : null;
    }

    /** @return array{mode: string, countries: string[], message: ?string} */
    public static function geo(): array
    {
        return self::safe(fn () => [
            'mode' => in_array(SiteSetting::get('geo.mode'), ['block', 'allow'], true) ? SiteSetting::get('geo.mode') : 'off',
            'countries' => self::parseCountries((string) SiteSetting::get('geo.countries', '')),
            'message' => SiteSetting::get('geo.message'),
        ]) ?? ['mode' => 'off', 'countries' => [], 'message' => null];
    }

    public static function parseCountries(string $list): array
    {
        preg_match_all('/\b[A-Za-z]{2}\b/', $list, $m);

        return array_values(array_unique(array_map('strtoupper', $m[0])));
    }

    /** True when this visitor should see the "not available in your country" page. */
    public static function countryBlocked(Request $request): bool
    {
        $geo = self::geo();
        if ($geo['mode'] === 'off' || !$geo['countries'] || self::isBot($request)) {
            return false;
        }
        $country = self::country($request);
        if (!$country) {
            return false; // unknown: never lock people out by mistake
        }

        return $geo['mode'] === 'block'
            ? in_array($country, $geo['countries'], true)
            : !in_array($country, $geo['countries'], true);
    }

    /**
     * Site mode from admin: "production" (live: Google may index, errors hidden) or "local"
     * (testing: robots.txt blocks Google). Empty = whatever APP_ENV in .env says.
     */
    public static function applyEnvironment(): void
    {
        $mode = (string) self::safe(fn () => SiteSetting::get('system.env'));
        if (!in_array($mode, ['production', 'local'], true) || app()->runningUnitTests()) {
            return;
        }
        app()->instance('env', $mode);
        config(['app.env' => $mode]);
        if ($mode === 'production') {
            config(['app.debug' => false]); // the 30-minute debug switch can still turn it on for the team
        }
    }

    /**
     * Website address from admin (overrides APP_URL): sitemap, canonical tags, schema and
     * emails then always use the real domain, and https is forced when it starts with https.
     */
    public static function applyUrl(): void
    {
        $url = rtrim((string) self::safe(fn () => SiteSetting::get('system.app_url')), '/');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }
        config(['app.url' => $url]);
        if (!app()->runningInConsole() || app()->runningUnitTests()) {
            \Illuminate\Support\Facades\URL::forceRootUrl($url);
            if (str_starts_with($url, 'https://')) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }
    }

    /** SMTP details saved in admin (password stays encrypted until it is used). */
    public static function mail(): array
    {
        return self::safe(fn () => [
            'enabled' => (bool) SiteSetting::get('mail.enabled'),
            'host' => SiteSetting::get('mail.host'),
            'port' => (int) SiteSetting::get('mail.port', 587),
            'encryption' => SiteSetting::get('mail.encryption', 'tls'),
            'username' => SiteSetting::get('mail.username'),
            'has_password' => filled(SiteSetting::get('mail.password')),
            'from_address' => SiteSetting::get('mail.from_address'),
            'from_name' => SiteSetting::get('mail.from_name'),
        ]) ?? ['enabled' => false];
    }

    /** Use the admin SMTP settings for every email the site sends. */
    public static function applyMail(): void
    {
        $m = self::mail();
        if (empty($m['enabled']) || blank($m['host'] ?? null)) {
            return;
        }
        $password = self::safe(fn () => ($p = SiteSetting::get('mail.password')) ? Crypt::decryptString($p) : null);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $m['host'],
            'mail.mailers.smtp.port' => $m['port'] ?: 587,
            // Laravel 11+: "smtps" = implicit TLS (port 465); "smtp" upgrades with STARTTLS when offered.
            'mail.mailers.smtp.scheme' => $m['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $m['username'],
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.timeout' => 15,
        ]);
        if (filled($m['from_address'])) {
            config(['mail.from.address' => $m['from_address'], 'mail.from.name' => $m['from_name'] ?: config('app.name')]);
        }
    }

    /** Brand and contact details for the maintenance / blocked page. */
    public static function pageDetails(): array
    {
        $s = fn ($k, $d = null) => rescue(fn () => \App\Models\SiteSetting::get($k, $d), $d, false);
        $contact = rescue(fn () => \App\Models\ContactContent::first(), null, false);
        [$phone, $tel] = \App\Http\Middleware\HandleInertiaRequests::formatPhone($contact?->phone);
        $logo = rescue(fn () => \App\Models\Footer::first()?->main_logo, null, false);

        return [
            'brand' => $s('business.brand_name') ?: config('app.name'),
            'logo' => $logo ? '/' . ltrim($logo, '/') : '/logo.png',
            'phone' => $phone,
            'tel' => $tel,
            'email' => $contact?->email,
            'whatsapp' => $tel ? 'https://wa.me/' . ltrim($tel, '+') : null,
            'message' => $s('system.maintenance_message'),
            'back_at' => $s('system.maintenance_back'),
            'geo_message' => $s('geo.message'),
        ];
    }

    private static function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return null; // settings table missing (fresh install, migrations running)
        }
    }
}
