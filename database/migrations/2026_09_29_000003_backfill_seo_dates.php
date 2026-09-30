<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['service_details', 'blog_details'] as $table) {
            DB::table($table)->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);
            DB::table($table)->whereNull('content_updated_at')->update(['content_updated_at' => DB::raw('updated_at')]);
        }
    }

    public function down(): void
    {
        // Backfilled values are indistinguishable from real ones; nothing to undo.
    }
};
