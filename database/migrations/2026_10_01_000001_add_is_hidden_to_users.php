<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Hidden maintenance account flag (additive; every existing user stays visible). */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_hidden')) {
            Schema::table('users', function (Blueprint $t) {
                $t->boolean('is_hidden')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_hidden'));
    }
};
