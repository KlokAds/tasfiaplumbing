<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Social and profile links, all from Admin → Contact & social. The footer icons, the business
 * schema (sameAs) and every WhatsApp button read from here, so nothing is hard-coded.
 */
class Socials
{
    /** key => [label, setting key, allowed hosts, placeholder, handle URL pattern] */
    public const PLATFORMS = [
        'google' => ['Google Business Profile', 'business.gbp_url', ['google.com', 'google.com.sg', 'g.page', 'goo.gl', 'maps.app.goo.gl', 'g.co'], 'https://maps.app.goo.gl/…', null],
        'facebook' => ['Facebook', 'social.facebook', ['facebook.com', 'fb.com', 'fb.me'], 'https://facebook.com/yourpage', 'https://www.facebook.com/%s'],
        'instagram' => ['Instagram', 'social.instagram', ['instagram.com'], '@yourname or https://instagram.com/yourname', 'https://www.instagram.com/%s'],
        'linkedin' => ['LinkedIn', 'social.linkedin', ['linkedin.com'], 'https://linkedin.com/company/…', null],
        'x' => ['X (Twitter)', 'social.x', ['x.com', 'twitter.com'], '@yourname or https://x.com/yourname', 'https://x.com/%s'],
        'youtube' => ['YouTube', 'social.youtube', ['youtube.com', 'youtu.be'], '@yourchannel or https://youtube.com/@…', 'https://www.youtube.com/@%s'],
        'tiktok' => ['TikTok', 'social.tiktok', ['tiktok.com'], '@yourname or https://tiktok.com/@…', 'https://www.tiktok.com/@%s'],
        'pinterest' => ['Pinterest', 'social.pinterest', ['pinterest.com', 'pinterest.co.uk', 'pin.it'], 'https://pinterest.com/…', 'https://www.pinterest.com/%s'],
    ];

    /** @return array<string, string> key => URL, only filled ones, in display order */
    public static function links(): array
    {
        $out = [];
        foreach (self::PLATFORMS as $key => [, $setting]) {
            $url = trim((string) SiteSetting::get($setting));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $out[$key] = $url;
            }
        }

        return $out;
    }

    /**
     * Turn what the owner typed into a clean https URL ("@tasfia" → https://www.instagram.com/tasfia).
     * Returns null for empty input; throws \InvalidArgumentException when it is another site's link.
     */
    public static function normalize(string $key, ?string $input): ?string
    {
        $value = trim((string) $input);
        if ($value === '' || $value === '#') {
            return null;
        }
        [$label, , $hosts, , $pattern] = self::PLATFORMS[$key];

        if (!preg_match('#^https?://#i', $value) && !str_contains($value, '/') && $pattern) {
            return sprintf($pattern, ltrim($value, '@'));
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }
        $host = strtolower(preg_replace('/^www\.|^m\.|^web\./', '', (string) parse_url($value, PHP_URL_HOST)));
        $ok = collect($hosts)->contains(fn ($h) => $host === $h || str_ends_with($host, '.' . $h));
        if (!$ok || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("This is not a {$label} link.");
        }

        return preg_replace('#^http://#i', 'https://', $value);
    }

    /** WhatsApp number in international digits (6593730360): the WhatsApp field, else the phone. */
    public static function whatsappNumber(?string $phone): ?string
    {
        $raw = (string) SiteSetting::get('social.whatsapp');
        if (preg_match('#wa\.me/(\d+)#', $raw, $m)) {
            return $m[1];
        }
        $source = preg_replace('/\D/', '', $raw) !== '' ? $raw : (string) $phone;
        $tel = \App\Http\Middleware\HandleInertiaRequests::formatPhone($source)[1];

        return $tel ? ltrim($tel, '+') : null;
    }

    public static function whatsappUrl(?string $phone): ?string
    {
        $n = self::whatsappNumber($phone);

        return $n ? 'https://wa.me/' . $n : null;
    }
}
