<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminPasswordChanged;
use App\Notifications\AdminPasswordReset;
use App\Http\Controllers\Admin\PasswordResetController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        Notification::fake();
    }

    public function test_pages_open_for_guests(): void
    {
        $this->get('/admin/forgot-password')->assertOk();
        $this->get('/admin/reset-password/abc?email=a@example.com')->assertOk();
    }

    public function test_the_answer_is_the_same_for_known_unknown_and_inactive_emails(): void
    {
        $active = User::factory()->create(['email' => 'boss@example.com', 'is_active' => true]);
        $inactive = User::factory()->create(['email' => 'old@example.com', 'is_active' => false]);

        foreach (['boss@example.com', 'nobody@example.com', 'old@example.com'] as $email) {
            $this->from('/admin/forgot-password')->post('/admin/forgot-password', ['email' => $email])
                ->assertRedirect('/admin/forgot-password')
                ->assertSessionHas('status', PasswordResetController::SENT);
        }

        Notification::assertSentTo($active, AdminPasswordReset::class);
        Notification::assertNotSentTo($inactive, AdminPasswordReset::class);
    }

    public function test_a_valid_link_changes_the_password_once_and_signs_out_remembered_devices(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com', 'is_active' => true, 'remember_token' => 'old-token']);
        $token = Password::broker()->createToken($user);

        $this->post('/admin/reset-password', [
            'token' => $token, 'email' => 'boss@example.com',
            'password' => 'NewPass2026x', 'password_confirmation' => 'NewPass2026x',
        ])->assertRedirect('/admin/login')->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass2026x', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
        Notification::assertSentTo($user, AdminPasswordChanged::class);

        // The same link cannot be used again.
        $this->post('/admin/reset-password', [
            'token' => $token, 'email' => 'boss@example.com',
            'password' => 'Another2026x', 'password_confirmation' => 'Another2026x',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('NewPass2026x', $user->fresh()->password));
    }

    public function test_bad_tokens_weak_passwords_and_inactive_accounts_are_refused(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com', 'is_active' => true]);
        $token = Password::broker()->createToken($user);

        $this->post('/admin/reset-password', [
            'token' => 'wrong', 'email' => 'boss@example.com',
            'password' => 'NewPass2026x', 'password_confirmation' => 'NewPass2026x',
        ])->assertSessionHasErrors('email');

        $this->post('/admin/reset-password', [
            'token' => $token, 'email' => 'boss@example.com',
            'password' => 'short1', 'password_confirmation' => 'short1',
        ])->assertSessionHasErrors('password');

        $user->forceFill(['is_active' => false])->save();
        $this->post('/admin/reset-password', [
            'token' => $token, 'email' => 'boss@example.com',
            'password' => 'NewPass2026x', 'password_confirmation' => 'NewPass2026x',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_expired_links_do_not_work(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com', 'is_active' => true]);
        $token = Password::broker()->createToken($user);
        $this->travel(61)->minutes();

        $this->post('/admin/reset-password', [
            'token' => $token, 'email' => 'boss@example.com',
            'password' => 'NewPass2026x', 'password_confirmation' => 'NewPass2026x',
        ])->assertSessionHasErrors('email');
    }

    public function test_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/forgot-password', ['email' => "x{$i}@example.com"]);
        }
        $this->post('/admin/forgot-password', ['email' => 'x9@example.com'])->assertStatus(429);
    }
}
