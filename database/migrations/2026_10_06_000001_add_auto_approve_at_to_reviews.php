<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When an article or a change waiting for approval is approved automatically (Content quality 100/100). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_details', function (Blueprint $table) {
            $table->timestamp('auto_approve_at')->nullable();
        });
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->timestamp('auto_approve_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_details', fn (Blueprint $table) => $table->dropColumn('auto_approve_at'));
        Schema::table('article_revisions', fn (Blueprint $table) => $table->dropColumn('auto_approve_at'));
    }
};
