<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The old footer kept the Tawk.to chat as two ids (property_id, widget_id). The new setting
 * (Admin > SEO > Tracking) was left empty, so the chat did not load. Fill it from the footer and
 * switch the chat on, only when nothing is set yet. The footer columns stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('footers') || !Schema::hasTable('site_settings') || filled(SiteSetting::get('tracking.tawk_id'))) {
            return;
        }
        $row = DB::table('footers')->first();
        $property = trim((string) ($row->property_id ?? ''));
        $widget = trim((string) ($row->widget_id ?? ''));
        if (!preg_match('/^[a-z0-9]+$/i', $property) || !preg_match('/^[a-z0-9]+$/i', $widget)) {
            return;
        }
        SiteSetting::putMany(['tracking.tawk_id' => "{$property}/{$widget}", 'tracking.tawk_enabled' => '1']);
    }

    public function down(): void
    {
        // Settings are kept; they can be changed in the admin.
    }
};
