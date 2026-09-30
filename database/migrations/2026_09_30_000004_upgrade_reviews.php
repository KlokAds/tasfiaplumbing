<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Own reviews get the details customers look for: stars, area and type of job.
        Schema::table('feed_back_contents', function (Blueprint $table) {
            if (!Schema::hasColumn('feed_back_contents', 'rating')) {
                $table->unsignedTinyInteger('rating')->default(5);
            }
            if (!Schema::hasColumn('feed_back_contents', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('feed_back_contents', 'job')) {
                $table->string('job')->nullable();
            }
            if (!Schema::hasColumn('feed_back_contents', 'review_date')) {
                $table->date('review_date')->nullable();
            }
            if (!Schema::hasColumn('feed_back_contents', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        // /testimonials became /reviews: move its Page SEO row too.
        if (Schema::hasTable('page_seo') && !DB::table('page_seo')->where('key', 'reviews')->exists()) {
            DB::table('page_seo')->where('key', 'testimonials')->update(['key' => 'reviews']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('page_seo')) {
            DB::table('page_seo')->where('key', 'reviews')->update(['key' => 'testimonials']);
        }
        Schema::table('feed_back_contents', function (Blueprint $table) {
            foreach (['rating', 'location', 'job', 'review_date', 'is_active'] as $col) {
                if (Schema::hasColumn('feed_back_contents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
