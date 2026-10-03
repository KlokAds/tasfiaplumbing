<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What a batch did to an audited article (App\Support\AuditBatch), and what it was before, so it can be undone. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_audits', function (Blueprint $table) {
            $table->string('applied_action', 20)->nullable()->index();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->json('applied_snapshot')->nullable();
            $table->timestamp('undone_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('article_audits', function (Blueprint $table) {
            $table->dropColumn(['applied_action', 'applied_at', 'applied_by', 'applied_snapshot', 'undone_at']);
        });
    }
};
