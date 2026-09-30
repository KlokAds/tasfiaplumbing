<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects become proof of real work (the "Experience" in E-E-A-T): which service,
 * where, what was done and when. Service and location pages can then show recent jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_details', function (Blueprint $table) {
            if (!Schema::hasColumn('project_details', 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained('service_details')->nullOnDelete();
            }
            if (!Schema::hasColumn('project_details', 'location_id')) {
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            }
            if (!Schema::hasColumn('project_details', 'area')) {
                $table->string('area')->nullable();
            }
            if (!Schema::hasColumn('project_details', 'property_type')) {
                $table->string('property_type', 60)->nullable();
            }
            if (!Schema::hasColumn('project_details', 'summary')) {
                $table->text('summary')->nullable();
            }
            if (!Schema::hasColumn('project_details', 'completed_on')) {
                $table->date('completed_on')->nullable();
            }
            if (!Schema::hasColumn('project_details', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table) {
            foreach (['service_id', 'location_id'] as $fk) {
                if (Schema::hasColumn('project_details', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['area', 'property_type', 'summary', 'completed_on', 'is_active'] as $col) {
                if (Schema::hasColumn('project_details', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
