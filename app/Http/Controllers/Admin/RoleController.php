<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index()
    {
        $super = config('admin.super_role');

        $roles = Role::with('permissions:id,name')->withCount(['users' => fn ($q) => $q->where('is_hidden', false)])->orderBy('id')->get()->map(fn (Role $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'label' => config("admin.roles.{$r->name}.label") ?? Str::headline($r->name),
            'description' => config("admin.roles.{$r->name}.description"),
            'is_super' => $r->name === $super,
            'is_builtin' => array_key_exists($r->name, config('admin.roles')),
            'users_count' => $r->users_count,
            'permissions' => $r->name === $super ? $this->allPermissions() : $r->permissions->pluck('name')->values(),
        ]);

        return Inertia::render('Admin/Users/Roles', [
            'roles' => $roles,
            'modules' => config('admin.modules'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::create(['name' => Str::slug($data['name']), 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->back()->with('success', "Role \"{$data['name']}\" created.");
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->name === config('admin.super_role'), 403, 'Super Admin always has every permission.');

        $data = $this->validated($request, $role);
        if (!array_key_exists($role->name, config('admin.roles'))) {
            $role->name = Str::slug($data['name']);
            $role->save();
        }
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->back()->with('success', 'Role permissions saved.');
    }

    public function destroy(Role $role)
    {
        if ($role->name === config('admin.super_role') || array_key_exists($role->name, config('admin.roles'))) {
            return redirect()->back()->with('error', 'Built-in roles cannot be deleted.');
        }
        if ($role->users()->exists()) {
            return redirect()->back()->with('error', 'Move the users in this role to another role first.');
        }
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->back()->with('success', 'Role deleted.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', function ($attr, $value, $fail) use ($role) {
                $slug = Str::slug($value);
                if (Role::where('name', $slug)->when($role, fn ($q) => $q->whereKeyNot($role->id))->exists()) {
                    $fail('A role with this name already exists.');
                }
            }],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($this->allPermissions())],
        ]);
    }

    private function allPermissions(): array
    {
        return \App\Support\Permissions::all();
    }
}
