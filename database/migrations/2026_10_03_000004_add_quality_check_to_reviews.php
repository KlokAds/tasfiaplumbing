<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The free writing check (AI-style phrases, duplicate text, pasted text) of an article or a change sent for approval. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_details', function (Blueprint $table) {
            $table->json('quality_check')->nullable();
        });
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->json('quality_check')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_details', fn (Blueprint $table) => $table->dropColumn('quality_check'));
        Schema::table('article_revisions', fn (Blueprint $table) => $table->dropColumn('quality_check'));
    }
};
