<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Split the broad permissions (services.manage, website.manage …) into per-module
 * view/create/edit/delete rights. Every role keeps exactly the access it had.
 */
return new class extends Migration
{
    private const MAP = [
        'services.manage' => ['services.view', 'services.create', 'services.edit'],
        'services.delete' => ['services.delete'],
        'catalog.manage' => ['categories.*', 'locations.*', 'pricing.*', 'faqs.*'],
        'website.manage' => ['homepage.*', 'about.*', 'banners.*', 'projects.*', 'reviews.view', 'reviews.create', 'reviews.edit', 'reviews.delete', 'page_seo.*'],
        'media.upload' => ['media.view', 'media.create', 'media.edit'],
        'media.delete' => ['media.delete'],
        'leads.view' => ['enquiries.view', 'enquiries.edit'],
        'leads.delete' => ['enquiries.delete'],
        'seo.manage' => ['seo_health.view', 'redirects.*', 'seo_settings.*', 'page_seo.view'],
        'settings.manage' => ['settings.*', 'system.cache', 'reviews.google'],
        'users.manage' => ['users.*', 'roles.*'],
        'articles.create' => ['seo_health.view'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Role::with('permissions')->get() as $role) {
            $current = $role->permissions->pluck('name');
            $extra = $current->flatMap(fn ($name) => Permissions::expand(self::MAP[$name] ?? []));
            $role->syncPermissions($current->intersect(Permissions::all())->merge($extra)->unique()->values()->all());
        }

        Permission::whereNotIn('name', Permissions::all())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Not reversible automatically; roles can be adjusted in Admin > Roles.
    }
};
