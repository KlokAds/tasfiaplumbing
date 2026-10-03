<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ApiKeyChanged;
use App\Support\ApiKeys;
use App\Support\SourceCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * System → Settings → API keys: every key in one place, encrypted, write-only (last 4 shown),
 * Super Admin only, every change emailed to the hidden admin.
 */
class ApiKeysTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'BSA-test-key-1234567890';

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(['is_active' => true] + $attrs)->assignRole($role);
    }

    public function test_only_a_super_admin_saves_a_key_encrypted_and_the_hidden_admin_is_emailed(): void
    {
        Notification::fake();
        Http::fake(['api.search.brave.com/*' => Http::response(['web' => ['results' => []]])]);
        $owner = $this->user('super-admin');
        $hidden = $this->user('super-admin', ['is_hidden' => true]);
        $editor = $this->user('editor');

        $this->actingAs($editor)->post('/admin/system/settings/keys/brave', ['value' => self::KEY])->assertForbidden();

        $this->actingAs($owner)->post('/admin/system/settings/keys/brave', ['value' => self::KEY])->assertSessionHasNoErrors();
        $stored = SiteSetting::where('key', SourceCheck::KEY_SETTING)->value('value');
        $this->assertStringNotContainsString(self::KEY, (string) $stored);
        $this->assertSame(self::KEY, SourceCheck::key());
        Notification::assertSentTo($hidden, ApiKeyChanged::class, fn ($n) => $n->action === 'saved' && $n->last4 === '7890' && $n->label === 'Brave Search API key');

        // The tab lists the keys with the last 4 characters only, and the page never contains a key.
        $this->actingAs($owner)->get('/admin/system/settings?tab=keys')
            ->assertInertia(fn ($page) => $page->where('apiKeys.0.name', 'brave')->where('apiKeys.0.saved', true)->where('apiKeys.0.last4', '7890'))
            ->assertDontSee(self::KEY);

        $this->actingAs($owner)->post('/admin/system/settings/keys/brave', ['remove' => true]);
        $this->assertFalse(SourceCheck::enabled());
        Notification::assertSentTo($hidden, ApiKeyChanged::class, fn ($n) => $n->action === 'removed');
    }

    public function test_a_key_the_service_does_not_accept_is_not_saved(): void
    {
        Http::fake(['api.search.brave.com/*' => Http::response(['error' => 'invalid'], 401)]);
        $this->actingAs($this->user('super-admin'))->post('/admin/system/settings/keys/brave', ['value' => self::KEY])->assertSessionHasErrors('value');
        $this->assertFalse(SourceCheck::enabled());
    }

    public function test_the_other_keys_keep_working_where_they_are_used(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');

        $this->actingAs($owner)->post('/admin/system/settings/keys/github', ['value' => 'github_pat_abcdefghijklmnop'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/admin/system/settings/keys/smtp', ['value' => 'app-password-1234'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/admin/system/settings/keys/google_secret', ['value' => 'GOCSPX-secret-9876'])->assertSessionHasNoErrors();

        $this->assertSame('github_pat_abcdefghijklmnop', ApiKeys::value('github'));
        $this->assertSame('GOCSPX-secret-9876', \App\Support\Google\GoogleApi::clientSecret());
        $this->assertSame('app-password-1234', \Illuminate\Support\Facades\Crypt::decryptString(SiteSetting::where('key', 'mail.password')->value('value')));

        // Saving the email server or the GitHub details does not touch the saved password / token.
        $this->actingAs($owner)->post('/admin/system/settings', ['section' => 'mail', 'enabled' => false, 'encryption' => 'tls']);
        $this->assertSame('app-password-1234', \Illuminate\Support\Facades\Crypt::decryptString(SiteSetting::where('key', 'mail.password')->value('value')));

        $this->actingAs($owner)->post('/admin/system/settings/keys/unknown', ['value' => 'x'])->assertNotFound();
    }
}
