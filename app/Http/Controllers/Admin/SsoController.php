<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Sign-in from the company's billing app: its "Open site admin" button sends the owner here with a link
 * signed with the content API token (valid two minutes, once). The owner's Super Admin account is signed in.
 */
class SsoController extends Controller
{
    public function login(Request $request)
    {
        $hash = SiteSetting::stored('content_api.token_hash');
        abort_unless($hash, 404);
        $exp = (int) $request->query('exp');
        $next = (string) $request->query('next', '/admin');
        $sig = (string) $request->query('sig');
        abort_unless($exp > time() && $exp <= time() + 120, 403, 'This link has expired. Open it again from the billing app.');
        abort_unless(hash_equals(hash_hmac('sha256', "{$exp}|{$next}", $hash), $sig), 403);
        abort_unless(preg_match('#^/admin(/[A-Za-z0-9/_\-.?=&%]*)?$#', $next) && !str_contains($next, '//'), 403);
        abort_unless(Cache::add('sso:' . $sig, 1, 300), 403, 'This link was already used. Open it again from the billing app.');

        $owner = User::where('is_active', true)->role(config('admin.super_role'))->orderByDesc('is_hidden')->orderBy('id')->first();
        abort_unless($owner, 403);
        Auth::login($owner);
        $request->session()->regenerate();
        $owner->forceFill(['last_login_at' => now()])->save();

        return redirect($next);
    }
}
