<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $names = \App\Support\Permissions::all();

        // Permissions left over from the previous CMS are not attached to any role or user; remove them.
        $legacy = DB::table('permissions')->whereNotIn('name', $names)->pluck('id');
        $attached = DB::table('role_has_permissions')->whereIn('permission_id', $legacy)->exists()
            || DB::table('model_has_permissions')->whereIn('permission_id', $legacy)->exists();
        if ($legacy->isNotEmpty() && !$attached) {
            DB::table('permissions')->whereIn('id', $legacy)->delete();
        }

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(config('admin.super_role'), 'web');
        foreach (config('admin.default_role_permissions') as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            // Only fill roles that have nothing yet, so re-running never overwrites choices made in the admin.
            if ($role->permissions()->count() === 0) {
                $role->syncPermissions(\App\Support\Permissions::expand($permissions));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Roles and permissions may have been edited in the admin since; leave them.
    }
};
