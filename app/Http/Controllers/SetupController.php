<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** First run only: create the owner (Super Admin) account. Closed once any user exists. */
class SetupController extends Controller
{
    public function show()
    {
        abort_if($this->done(), 404);

        return view('setup');
    }

    public function store(Request $request)
    {
        abort_if($this->done(), 404);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'is_active' => true,
        ]);
        $user->assignRole(config('admin.super_role'));
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect('/admin/checklist')->with('success', 'Welcome! Your owner account is ready. Work through this checklist to finish the website.');
    }

    private function done(): bool
    {
        return DB::table('users')->exists();
    }
}
