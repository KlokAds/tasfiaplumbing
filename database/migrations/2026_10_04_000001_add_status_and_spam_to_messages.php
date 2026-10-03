<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enquiries: spam flag (App\Support\SpamCheck, nothing is deleted), follow-up status
 * (new → contacted → quoted → won / lost), the "no reply yet" reminder, and the page it was sent from.
 * Existing enquiries keep everything they have; they start as "new" and not spam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_spam')->default(false)->index();
            $table->string('spam_reason', 300)->nullable();
            $table->string('status', 12)->default('new')->index();
            $table->timestamp('status_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->string('page', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_spam', 'spam_reason', 'status', 'status_at', 'reminded_at', 'page']);
        });
    }
};
