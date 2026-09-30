<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminPasswordChanged;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Forgotten admin passwords.
 *
 * - The answer is the same whether or not the email belongs to an account, and the email is sent
 *   after the response, so neither the message nor the timing tells anyone which addresses exist.
 * - Only active accounts get a link. Links are single-use, hashed in the database and expire (config/auth.php).
 * - A new link for the same email can be asked for once a minute; the routes are rate limited too.
 * - A successful reset signs out every other session and "remember me" device, and emails the owner.
 */
class PasswordResetController extends Controller
{
    public const SENT = 'If that email belongs to an active admin account, a reset link is on its way. Check your inbox and spam folder.';

    public function showRequest()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Admin/Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:190']);
        $email = trim($data['email']);

        defer(function () use ($email) {
            try {
                Password::broker()->sendResetLink(['email' => $email, 'is_active' => 1]);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return back()->with('status', self::SENT);
    }

    public function showReset(Request $request, string $token)
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Admin/Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email:rfc|max:190',
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = Password::broker()->reset(
            ['email' => trim($data['email']), 'is_active' => 1] + $data,
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->endOtherSessions($user);
                event(new PasswordReset($user));

                try {
                    $user->notify(new AdminPasswordChanged($request->ip()));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This reset link is invalid or has expired. Ask for a new one.',
            ]);
        }

        return redirect()->route('admin.login')->with('success', 'Your password was changed. Sign in with the new password.');
    }

    /** Database sessions carry the user id, so every open session of this account can be closed. */
    private function endOtherSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }
        try {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->delete();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
