<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogDetail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Admin/Account/Show', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'bio' => $user->bio,
                'social_url' => $user->social_url,
                'image' => $user->photo(),
                'role' => config('admin.roles.' . $user->getRoleNames()->first() . '.label') ?? $user->getRoleNames()->first(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'stats' => [
                'articles' => BlogDetail::where('author_id', $user->id)->count(),
                'published' => BlogDetail::where('author_id', $user->id)->published()->count(),
                'pending' => BlogDetail::where('author_id', $user->id)->where('status', BlogDetail::PENDING)->count(),
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'job_title' => 'nullable|string|max:120',
            'bio' => 'nullable|string|max:1000',
            'social_url' => 'nullable|url|max:255',
            'image' => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $user->image = $request->file('image')->store('Admin/Users', 'uploads');
        }
        unset($data['image']);
        $user->fill($data)->save();

        // Keep the byline on this author's articles in sync with their name.
        BlogDetail::where('author_id', $user->id)->update(['auth_name' => $user->name]);

        return redirect()->back()->with('success', 'Profile saved.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return redirect()->back()->with('success', 'Password changed.');
    }
}
