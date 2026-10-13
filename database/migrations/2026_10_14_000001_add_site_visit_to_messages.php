<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quote form: a free site visit (preferred day and time, postal code, property) and photos of the job. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('wants_visit')->default(false);
            $table->date('visit_date')->nullable();
            $table->string('visit_time', 20)->nullable();   // any, morning, afternoon, evening
            $table->string('postal_code', 10)->nullable();
            $table->string('property', 30)->nullable();
            $table->json('photos')->nullable();               // paths on the private disk
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn(['wants_visit', 'visit_date', 'visit_time', 'postal_code', 'property', 'photos']));
    }
};
