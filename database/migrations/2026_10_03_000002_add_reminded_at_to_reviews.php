<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When the last "still waiting for approval" reminder went out for an article or a change. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_details', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();
        });
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_details', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
        Schema::table('article_revisions', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
    }
};
