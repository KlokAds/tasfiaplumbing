<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Where this website is run in the company's billing app. The billing app sends its page for this
 * website with every content API call (X-Billing-Page); links here to the parts it runs point there.
 */
class Billing
{
    private const KEY = 'billing.page';

    /** Keeps the billing app's page for this website (only a plain web address, and only when it changed). */
    public static function remember(?string $url): void
    {
        $url = trim((string) $url);
        $ok = strlen($url) <= 300 && filter_var($url, FILTER_VALIDATE_URL)
            && (str_starts_with($url, 'https://') || (app()->environment('local', 'testing') && str_starts_with($url, 'http://')))
            && !preg_match('/[?#]/', $url);
        if ($ok && $url !== SiteSetting::stored(self::KEY)) {
            SiteSetting::putMany([self::KEY => $url]);
        }
    }

    /** The billing app's page for this website, or one of its sections (keys, enquiries, system); null until it has called. */
    public static function url(string $section = ''): ?string
    {
        $base = SiteSetting::stored(self::KEY);

        return $base ? rtrim($base, '/') . ($section !== '' ? '/' . $section : '') : null;
    }
}
