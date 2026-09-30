<?php

use App\Models\SiteSetting;
use App\Support\Socials;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The old contact table had four numbered link columns ("social_four" was labelled WhatsApp
 * but held an X link). Sort every saved link into the right platform by its address.
 * The old columns are left untouched as a backup.
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
            if ($link === '' || $link === '#') {
                continue;
            }
            if (preg_match('#wa\.me|whatsapp\.com#i', $link) || preg_match('/^\+?[\d\s-]{8,}$/', $link)) {
                $values['social.whatsapp'] = $link;
                continue;
            }
            foreach (array_keys(Socials::PLATFORMS) as $key) {
                try {
                    $url = Socials::normalize($key, $link);
                } catch (\InvalidArgumentException) {
                    continue;
                }
                $setting = Socials::PLATFORMS[$key][1];
                if ($url && blank(SiteSetting::get($setting)) && !isset($values[$setting])) {
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
