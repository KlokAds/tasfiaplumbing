<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Article audit (App\Support\ArticleAudit): one row per live article with its topic group, length,
 * quality, overlap with other articles, Search Console numbers and a suggested action. The decision
 * columns hold what an approver chose; nothing on the website changes from this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->unique()->constrained('blog_details')->cascadeOnDelete();
            $table->string('group_key', 190)->index();
            $table->unsignedInteger('group_size')->default(1);
            $table->unsignedInteger('words')->default(0);
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedTinyInteger('dup_percent')->default(0);
            $table->unsignedBigInteger('dup_article_id')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 6, 1)->nullable();
            $table->string('top_query', 190)->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('suggestion', 20)->index();
            $table->unsignedBigInteger('target_article_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('decision', 20)->nullable()->index();
            $table->unsignedBigInteger('decision_target_id')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_audits');
    }
};
