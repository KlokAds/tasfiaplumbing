<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every review from the Google Business Profile (the Places API only gives 5).
        Schema::create('google_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_id')->unique();
            $table->string('author')->nullable();
            $table->string('photo', 500)->nullable();
            $table->unsignedTinyInteger('rating')->default(0);
            $table->text('comment')->nullable();
            $table->text('reply')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();
        });

        // Google's answer to "is this URL indexed?" (URL Inspection API).
        Schema::create('index_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500)->unique();
            $table->string('verdict', 20)->nullable();         // PASS / NEUTRAL / FAIL
            $table->string('coverage', 190)->nullable();       // "Submitted and indexed", "Crawled - currently not indexed" …
            $table->string('robots', 40)->nullable();
            $table->string('fetch', 40)->nullable();
            $table->string('google_canonical', 500)->nullable();
            $table->timestamp('last_crawl')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->string('error', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_statuses');
        Schema::dropIfExists('google_reviews');
    }
};
