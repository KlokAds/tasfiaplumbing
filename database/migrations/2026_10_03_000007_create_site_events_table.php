<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The website's own visitor counter (App\Support\VisitorStats): page views and WhatsApp, call and
 * chat clicks from real browsers. No IP address is stored, only a daily one-way visitor hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_events', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('type', 20);            // visit | whatsapp | call | chat | chat_message
            $table->char('visitor', 16);           // the same person on the same day, not reversible
            $table->char('country', 2)->nullable();
            $table->string('path', 300)->nullable();
            $table->string('source', 100)->nullable(); // the site the visitor came from (google.com …)
            $table->string('device', 10)->nullable();  // mobile | desktop
            $table->timestamp('created_at')->nullable();
            $table->index(['day', 'type']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('visitors.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_events');
    }
};
