<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // scheduled_at: when the article should go live. Set by the author when submitting,
        // kept (or changed) by the publisher when approving. Status "scheduled" = approved, waiting for its time.
        Schema::table('blog_details', function (Blueprint $table) {
            if (!Schema::hasColumn('blog_details', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->index();
            }
        });

        // In-app notifications (the bell). Email is sent alongside.
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('blog_details', function (Blueprint $table) {
            if (Schema::hasColumn('blog_details', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
        });
    }
};
