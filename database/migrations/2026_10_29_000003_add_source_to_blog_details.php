<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where an article came from: "hub" when the content hub (billing app) made it. The hub may change only those. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('blog_details', 'source')) {
            Schema::table('blog_details', function (Blueprint $table) {
                $table->string('source', 20)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        // Kept: dropping it would forget which articles the hub made.
    }
};
