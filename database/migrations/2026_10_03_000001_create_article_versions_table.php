<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Earlier live versions of an article: each time the live text changes (an edit, an approved
 * change, a restore) the version it replaced is kept here, so it can be checked and restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('blog_details')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // who replaced it
            $table->string('event', 20); // edit | change_approved | restore
            $table->json('payload');     // the content as it was live
            $table->timestamps();
            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_versions');
    }
};
