<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function owner(): User
    {
        return User::factory()->create()->assignRole('super-admin');
    }

    public function test_maintenance_shows_503_to_visitors_but_not_to_the_team(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/admin/system/settings', ['section' => 'status', 'maintenance' => true, 'maintenance_message' => 'Quick update'])
            ->assertSessionHas('success');
        auth()->logout();

        $this->get('/')->assertStatus(503)->assertHeader('Retry-After')->assertSee('Quick update');
        $this->get('/admin/login')->assertOk();
        $this->actingAs($owner)->get('/')->assertOk();
    }

    public function test_country_rules_block_visitors_but_never_search_engines(): void
    {
        SiteSetting::putMany(['geo.mode' => 'allow', 'geo.countries' => 'SG, MY']);

        $this->get('/', ['CF-IPCountry' => 'SG'])->assertOk();
        $this->get('/', ['CF-IPCountry' => 'RU'])->assertForbidden();
        $this->get('/', ['CF-IPCountry' => 'RU', 'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertOk();
        $this->get('/')->assertOk(); // unknown country is never blocked
    }

    public function test_owner_cannot_block_their_own_country(): void
    {
        $this->actingAs($this->owner())
            ->post('/admin/system/settings', ['section' => 'geo', 'mode' => 'block', 'countries' => 'SG, RU'], ['CF-IPCountry' => 'SG']);

        $this->assertSame(['RU'], SystemSettings::geo()['countries']);
    }

    public function test_smtp_settings_are_encrypted_and_applied(): void
    {
        // The password is saved in System → API keys, the rest in the Email tab.
        $this->actingAs($this->owner())->post('/admin/system/settings/keys/smtp', ['value' => 'secret-pass'])->assertSessionHasNoErrors();
        $this->post('/admin/system/settings', [
            'section' => 'mail', 'enabled' => true, 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls',
            'username' => 'me@example.test', 'from_address' => 'hello@example.test', 'from_name' => 'Tasfia',
        ])->assertSessionHas('success');

        $this->assertNotSame('secret-pass', SiteSetting::get('mail.password'));
        $this->assertSame('secret-pass', Crypt::decryptString(SiteSetting::get('mail.password')));

        SystemSettings::applyMail();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame('secret-pass', config('mail.mailers.smtp.password'));
        $this->assertSame('hello@example.test', config('mail.from.address'));

        // Saving the email settings keeps the saved password
        $this->post('/admin/system/settings', ['section' => 'mail', 'enabled' => true, 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'from_address' => 'hello@example.test', 'password' => '']);
        $this->assertSame('secret-pass', Crypt::decryptString(SiteSetting::get('mail.password')));
    }

    public function test_debug_mode_turns_itself_off(): void
    {
        $this->actingAs($this->owner())->post('/admin/system/settings/debug', ['on' => true]);
        $this->assertNotNull(SystemSettings::debugUntil());

        SiteSetting::putMany(['system.debug_until' => (string) (time() - 1)]);
        $this->assertNull(SystemSettings::debugUntil());
    }

    public function test_only_permitted_users_open_system_settings(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get('/admin/system/settings')->assertForbidden();
        $this->actingAs($this->owner())->get('/admin/system/settings')->assertOk();
        $this->get('/admin/system/settings/preview?mode=blocked')->assertOk()->assertSee('Not available in your region');
    }
}
