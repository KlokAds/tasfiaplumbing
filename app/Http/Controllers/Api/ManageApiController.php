<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Footer;
use App\Models\Message;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ApiKeys;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The company's billing app runs this website from there: who may sign in here and with which role, what
 * each role may do, the API keys, the Google connection and the tracking codes. People who sign in here
 * keep doing the content work. The owner's parts (keys, settings, people, roles, system, Google connect)
 * cannot be given to a role from here, and the owner's own account is never changed from here.
 */
class ManageApiController extends Controller
{
    /** Permissions only the owner (Super Admin) has: a role never gets them through the billing app. */
    public const OWNER_ONLY = ['settings.', 'users.', 'roles.', 'system.', 'analytics.connect', 'reviews.google'];

    public function show()
    {
        $super = config('admin.super_role');
        $footer = Footer::first();

        return response()->json([
            'admin_url' => url('/admin'),
            'users' => User::where('is_hidden', false)->with('roles:id,name')->orderBy('name')->get()->map(fn (User $u) => [
                'email' => $u->email,
                'name' => $u->full_name ?: $u->name,
                'role' => $u->roles->first()?->name,
                'active' => (bool) $u->is_active,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ])->values(),
            'roles' => Role::with('permissions:id,name')->withCount(['users' => fn ($q) => $q->where('is_hidden', false)])->orderBy('id')->get()->map(fn (Role $r) => [
                'name' => $r->name,
                'label' => config("admin.roles.{$r->name}.label") ?? Str::headline($r->name),
                'description' => config("admin.roles.{$r->name}.description"),
                'is_super' => $r->name === $super,
                'is_builtin' => array_key_exists($r->name, config('admin.roles')),
                'users' => $r->users_count,
                'permissions' => $r->name === $super ? Permissions::all() : $r->permissions->pluck('name')->values(),
            ])->values(),
            'modules' => config('admin.modules'),
            'owner_only' => self::OWNER_ONLY,
            'keys' => collect(ApiKeys::KEYS)->map(fn ($k, $name) => ['name' => $name, 'label' => $k['label'], 'used_for' => $k['used_for'], 'get' => $k['get']] + array_intersect_key(ApiKeys::status($name), ['saved' => 1, 'last4' => 1]))->values(),
            'google' => [
                'client_id' => SiteSetting::get('google.client_id'),
                'secret_saved' => (bool) ApiKeys::value('google_secret'),
                'connected_email' => SiteSetting::get('google.connected_email'),
                'property' => SiteSetting::get('google.gsc_property'),
                'last_sync' => SiteSetting::get('google.last_sync'),
                'last_error' => SiteSetting::get('google.last_error'),
            ],
            'tracking' => [
                'gtm' => $footer?->g_tag,
                'ga4' => $footer?->g_a_tag,
                'gsc_verification' => SiteSetting::get('seo.gsc_verification'),
                'bing_verification' => SiteSetting::get('seo.bing_verification'),
            ],
            'chat' => [
                'mujaid' => SiteSetting::stored('chat.mujaid_enabled') === '1',
                'tawk' => SiteSetting::get('tracking.tawk_enabled') === '1' && filled(SiteSetting::get('tracking.tawk_id')),
            ],
            'leads_week' => Message::where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    /**
     * Gives a person a role here, or takes their access away (role "none": the account stays, it just
     * cannot sign in). A new person gets an email to set their own password.
     */
    public function user(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'role' => 'required|string|max:60',
        ]);
        $super = config('admin.super_role');
        $email = strtolower(trim($data['email']));
        $role = $data['role'] === 'none' ? null : Role::where('name', $data['role'])->first();
        if ($data['role'] !== 'none' && !$role) {
            return response()->json(['message' => 'There is no such role on this website.'], 422);
        }
        if ($role?->name === $super) {
            return response()->json(['message' => 'Super Admin is the owner only.'], 422);
        }
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($user && ($user->is_hidden || $user->hasRole($super))) {
            return response()->json(['message' => 'The owner\'s account is not changed from the billing app.'], 422);
        }

        $created = false;
        if (!$user) {
            if (!$role) {
                return response()->json(['ok' => true, 'created' => false]);
            }
            $user = User::create([
                'name' => trim((string) ($data['name'] ?? '')) ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32),
                'is_active' => true,
            ]);
            $created = true;
        } elseif (filled($data['name'] ?? null)) {
            $user->update(['name' => trim($data['name'])]);
        }
        $user->syncRoles($role ? [$role->name] : []);
        $user->update(['is_active' => (bool) $role]);
        if ($created) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return response()->json(['ok' => true, 'created' => $created]);
    }

    /** What a role may do (a new role when no name is given). The owner's permissions are never given. */
    public function role(Request $request, ?string $name = null)
    {
        $data = $request->validate([
            'label' => [$name ? 'nullable' : 'required', 'string', 'max:60'],
            'permissions' => 'present|array',
            'permissions.*' => 'string|max:100',
        ]);
        $allowed = array_values(array_filter(array_intersect($data['permissions'], Permissions::all()), fn ($p) => !self::ownerOnly($p)));
        if ($name) {
            $role = Role::where('name', $name)->firstOrFail();
            if ($role->name === config('admin.super_role')) {
                return response()->json(['message' => 'Super Admin always has every permission.'], 422);
            }
        } else {
            $slug = Str::slug($data['label']);
            if ($slug === '' || Role::where('name', $slug)->exists()) {
                return response()->json(['message' => 'A role with this name already exists.'], 422);
            }
            $role = Role::create(['name' => $slug, 'guard_name' => 'web']);
        }
        $role->syncPermissions($allowed);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['ok' => true, 'name' => $role->name]);
    }

    /** The tracking and verification codes and the Google client id. Only the ones sent change. */
    public function settings(Request $request)
    {
        $data = $request->validate([
            'gtm' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]+$/'],
            'ga4' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]+$/'],
            'gsc_verification' => 'nullable|string|max:200',
            'bing_verification' => 'nullable|string|max:200',
            'google_client_id' => 'nullable|string|max:200',
        ]);
        $footer = Footer::first();
        foreach (['gtm' => 'g_tag', 'ga4' => 'g_a_tag'] as $in => $column) {
            if ($request->exists($in) && $footer) {
                $footer->update([$column => $data[$in] ?? null]);
            }
        }
        $settings = [];
        foreach (['gsc_verification' => 'seo.gsc_verification', 'bing_verification' => 'seo.bing_verification', 'google_client_id' => 'google.client_id'] as $in => $key) {
            if ($request->exists($in)) {
                $settings[$key] = trim((string) ($data[$in] ?? ''));
            }
        }
        if ($settings) {
            SiteSetting::putMany($settings);
        }

        return response()->json(['ok' => true]);
    }

    /** Saves or removes an API key (checked with its service first); the owner's mailbox is told. */
    public function key(Request $request, string $name)
    {
        abort_unless(isset(ApiKeys::KEYS[$name]), 404);
        $data = $request->validate(['value' => 'nullable|string|max:2000']);
        $value = trim((string) ($data['value'] ?? ''));
        if ($value !== '' && ($problem = ApiKeys::test($name, $value))) {
            return response()->json(['message' => $problem], 422);
        }
        ApiKeys::save($name, $value);
        if ($owner = User::where('is_active', true)->role(config('admin.super_role'))->orderByDesc('is_hidden')->first()) {
            $by = $owner->replicate()->fill(['name' => 'the billing app']);
            ApiKeys::alert($name, $value !== '' ? 'changed' : 'removed', $by, $request->ip(), $value !== '' ? substr($value, -4) : null);
        }

        return response()->json(['ok' => true] + ApiKeys::status($name));
    }

    public static function ownerOnly(string $permission): bool
    {
        foreach (self::OWNER_ONLY as $p) {
            if ($permission === $p || (str_ends_with($p, '.') && str_starts_with($permission, $p))) {
                return true;
            }
        }

        return false;
    }
}
