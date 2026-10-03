<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The last source check (sentences found on other websites) of an article or a change waiting for approval. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_details', function (Blueprint $table) {
            $table->json('source_check')->nullable();
        });
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->json('source_check')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_details', fn (Blueprint $table) => $table->dropColumn('source_check'));
        Schema::table('article_revisions', fn (Blueprint $table) => $table->dropColumn('source_check'));
    }
};
