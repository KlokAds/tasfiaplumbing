<?php

use App\Models\SiteSetting;
use App\Support\Socials;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Second pass of 2026_09_30_000009: on some sites the social settings ended up empty although
 * the old contact links are still saved. Sort each old link into its platform by address.
 * Only empty settings are filled; the old columns stay as a backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contact_contents') || !Schema::hasTable('site_settings')) {
            return;
        }
        $row = DB::table('contact_contents')->first();
        if (!$row) {
            return;
        }

        $values = [];
        foreach (['social_one_link', 'social_two_link', 'social_three_link', 'social_four_link', 'social_five_link'] as $col) {
            $link = trim((string) ($row->{$col} ?? ''));
            // Skip placeholders such as "#" or a bare "https://facebook.com" with no page.
            if ($link === '' || $link === '#' || trim((string) parse_url($link, PHP_URL_PATH), '/') === '' && !parse_url($link, PHP_URL_QUERY)) {
                continue;
            }
            if (preg_match('#wa\.me|whatsapp\.com#i', $link)) {
                $key = 'social.whatsapp';
                if (blank(SiteSetting::get($key)) && !isset($values[$key])) {
                    $values[$key] = $link;
                }
                continue;
            }
            foreach (Socials::PLATFORMS as $platform => $info) {
                try {
                    $url = Socials::normalize($platform, $link);
                } catch (\Throwable) {
                    $url = null;
                }
                if (!$url) {
                    continue; // not this platform, try the next one
                }
                $setting = $info[1];
                if (blank(SiteSetting::get($setting)) && !isset($values[$setting])) {
                    $values[$setting] = $url;
                }
                break;
            }
        }
        if ($values) {
            SiteSetting::putMany($values);
        }
    }

    public function down(): void
    {
    }
};
