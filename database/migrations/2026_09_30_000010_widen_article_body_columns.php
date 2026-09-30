<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Article and service bodies can be longer than MySQL's TEXT limit (64 KB): the old site
 * stored them as LONGTEXT and two imported articles are larger than 64 KB.
 * SQLite has no such limit, so only MySQL / MariaDB need the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        foreach (['blog_details', 'service_details'] as $table) {
            if (Schema::hasColumn($table, 'desc')) {
                Schema::table($table, fn (Blueprint $t) => $t->longText('desc')->change());
            }
        }
    }

    public function down(): void
    {
        // Shrinking back to TEXT could cut long articles, so this is left as is.
    }
};
