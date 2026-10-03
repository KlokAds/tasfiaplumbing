<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $articleCounts = BlogDetail::selectRaw('author_id, count(*) n')->whereNotNull('author_id')->groupBy('author_id')->pluck('n', 'author_id');

        $users = User::visible()->with('roles:id,name')->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'job_title' => $u->job_title,
            'bio' => $u->bio,
            'social_url' => $u->social_url,
            'image' => $u->photo(),
            'is_active' => (bool) $u->is_active,
            'role' => $u->roles->first()?->name,
            'articles' => $articleCounts[$u->id] ?? 0,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($denied = $this->superAdminGuard($request, $data['role'])) {
            return $denied;
        }
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'job_title' => $data['job_title'] ?? null,
            'bio' => $data['bio'] ?? null,
            'social_url' => $data['social_url'] ?? null,
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
        ]);
        $user->syncRoles([$data['role']]);

        return redirect()->back()->with('success', "{$user->name} added as " . $this->label($data['role']) . '.');
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->is_hidden, 404);
        $data = $this->validated($request, $user);
        if ($denied = $this->superAdminGuard($request, $data['role'], $user)) {
            return $denied;
        }

        if ($this->wouldRemoveLastSuperAdmin($user, $data['role'], $data['is_active'] ?? true)) {
            return redirect()->back()->with('error', 'At least one active Super Admin is required.');
        }
        if ($user->is($request->user()) && !($data['is_active'] ?? true)) {
            return redirect()->back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'job_title' => $data['job_title'] ?? null,
            'bio' => $data['bio'] ?? null,
            'social_url' => $data['social_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        if (!empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->back()->with('success', "{$user->name} updated.");
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is_hidden, 404);
        if ($user->is($request->user())) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }
        if ($denied = $this->superAdminGuard($request, null, $user)) {
            return $denied;
        }
        if ($this->wouldRemoveLastSuperAdmin($user, null, false)) {
            return redirect()->back()->with('error', 'At least one active Super Admin is required.');
        }

        $name = $user->name;
        // Articles keep their byline text; only the link to the account is removed.
        $user->delete();

        return redirect()->back()->with('success', "{$name} removed. Their articles keep the author name.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'job_title' => 'nullable|string|max:120',
            'bio' => 'nullable|string|max:1000',
            'social_url' => 'nullable|url|max:255',
            'role' => ['required', Rule::exists('roles', 'name')],
            'is_active' => 'boolean',
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)->letters()->numbers()],
        ]);
    }

    /**
     * Only a Super Admin may create a Super Admin, or change or remove a Super Admin's account.
     * Even if another role is later given the "users" permissions, it cannot raise itself or
     * anyone else to Super Admin, or take over an owner account by changing its password.
     */
    private function superAdminGuard(Request $request, ?string $role, ?User $target = null): ?\Illuminate\Http\RedirectResponse
    {
        $super = config('admin.super_role');
        if ($request->user()->hasRole($super)) {
            return null;
        }
        if ($role === $super || ($target && $target->hasRole($super))) {
            return redirect()->back()->with('error', 'Only a Super Admin can add, change or remove a Super Admin account.');
        }

        return null;
    }

    private function wouldRemoveLastSuperAdmin(User $user, ?string $newRole, bool $active): bool
    {
        $super = config('admin.super_role');
        if (!$user->hasRole($super) || ($newRole === $super && $active)) {
            return false;
        }

        return User::visible()->role($super)->where('is_active', true)->whereKeyNot($user->id)->doesntExist();
    }

    private function roleOptions(): array
    {
        return Role::orderBy('id')->get(['name'])->map(fn ($r) => [
            'name' => $r->name,
            'label' => $this->label($r->name),
            'description' => config("admin.roles.{$r->name}.description"),
        ])->all();
    }

    private function label(string $role): string
    {
        return config("admin.roles.{$role}.label") ?? ucwords(str_replace(['-', '_'], ' ', $role));
    }
}
