<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrade existing tables from v1 (old project) to v2 (new upgrade).
     * Safely adds missing columns and renames old column names.
     */
    public function up(): void
    {
        // ── Users: Add is_active, full_name, image columns ──
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(1)->after('password');
            }
            if (!Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'image')) {
                $table->string('image')->nullable()->after('full_name');
            }
        });

        // ── Messages: Add is_read and subject columns (if missing from old schema) ──
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (!Schema::hasColumn('messages', 'is_read')) {
                    $table->boolean('is_read')->default(0)->after('message');
                }
                if (!Schema::hasColumn('messages', 'subject')) {
                    $table->string('subject')->nullable()->after('phone');
                }
            });
        }

        // ── Home Counters: Rename old column names to new v2 names ──
        if (Schema::hasTable('home_counters')) {
            Schema::table('home_counters', function (Blueprint $table) {
                // Old: icon_name, counter, title → New: c_icon, c_count, c_title + c_subtitle
                if (Schema::hasColumn('home_counters', 'icon_name') && !Schema::hasColumn('home_counters', 'c_icon')) {
                    $table->renameColumn('icon_name', 'c_icon');
                }
                if (Schema::hasColumn('home_counters', 'counter') && !Schema::hasColumn('home_counters', 'c_count')) {
                    $table->renameColumn('counter', 'c_count');
                }
                if (Schema::hasColumn('home_counters', 'title') && !Schema::hasColumn('home_counters', 'c_title')) {
                    $table->renameColumn('title', 'c_title');
                }
            });

            // Add c_subtitle separately (after renames complete)
            Schema::table('home_counters', function (Blueprint $table) {
                if (!Schema::hasColumn('home_counters', 'c_subtitle')) {
                    $table->string('c_subtitle')->nullable()->after('c_title');
                }
            });
        }

        // ── Home Skills: Rename old column names to new v2 names ──
        if (Schema::hasTable('home_skills')) {
            Schema::table('home_skills', function (Blueprint $table) {
                // Old: icon_name, title, short_desc → New: s_icon, s_title, s_subtitle + s_point
                if (Schema::hasColumn('home_skills', 'icon_name') && !Schema::hasColumn('home_skills', 's_icon')) {
                    $table->renameColumn('icon_name', 's_icon');
                }
                if (Schema::hasColumn('home_skills', 'title') && !Schema::hasColumn('home_skills', 's_title')) {
                    $table->renameColumn('title', 's_title');
                }
                if (Schema::hasColumn('home_skills', 'short_desc') && !Schema::hasColumn('home_skills', 's_subtitle')) {
                    $table->renameColumn('short_desc', 's_subtitle');
                }
            });

            // Add s_point separately (after renames complete)
            Schema::table('home_skills', function (Blueprint $table) {
                if (!Schema::hasColumn('home_skills', 's_point')) {
                    $table->string('s_point')->nullable()->after('id');
                }
            });
        }

        // ── Service Details: Make columns nullable for flexibility ──
        if (Schema::hasTable('service_details')) {
            Schema::table('service_details', function (Blueprint $table) {
                // The old schema had non-nullable image/bef_img/aft_img, make them nullable
                if (Schema::hasColumn('service_details', 'image')) {
                    $table->string('image')->nullable()->change();
                }
                if (Schema::hasColumn('service_details', 'bef_img')) {
                    $table->string('bef_img')->nullable()->change();
                }
                if (Schema::hasColumn('service_details', 'aft_img')) {
                    $table->string('aft_img')->nullable()->change();
                }
                // The old schema had unique constraint on 'order', remove it for flexibility
                // (handled by making the column just an integer without unique)
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Users: Drop added columns
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('users', 'is_active')) $columns[] = 'is_active';
            if (Schema::hasColumn('users', 'full_name')) $columns[] = 'full_name';
            if (Schema::hasColumn('users', 'image')) $columns[] = 'image';
            if (!empty($columns)) $table->dropColumn($columns);
        });

        // Messages: Drop is_read
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'is_read')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('is_read');
            });
        }
    }
};
