<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The ad or campaign a visitor arrived from (utm_campaign, or Google / Facebook / TikTok ad click ids). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('site_events', 'campaign')) {
            Schema::table('site_events', function (Blueprint $table) {
                $table->string('campaign', 100)->nullable()->after('source');
            });
        }
    }

    public function down(): void
    {
        // Keeps the column: dropping it would lose the campaign of every recorded visit.
    }
};
