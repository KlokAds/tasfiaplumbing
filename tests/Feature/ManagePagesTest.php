<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The billing app runs the website's own System, Enquiries and settings pages as the owner. */
class ManagePagesTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::putMany(['content_api.token_hash' => hash('sha256', self::TOKEN)]);
        $owner = User::factory()->create(['email' => 'owner@example.com', 'is_active' => true]);
        $owner->forceFill(['is_hidden' => true])->save();
        $owner->assignRole(config('admin.super_role'));
    }

    private function api(): static
    {
        return $this->withToken(self::TOKEN);
    }

    public function test_system_settings_are_read_and_saved_with_the_admins_own_rules(): void
    {
        $this->api()->getJson('/api/content/manage/pages/system')->assertOk()->assertJsonPath('props.status.maintenance', false)->assertJsonStructure(['props' => ['mail', 'geo', 'server']]);
        $this->api()->postJson('/api/content/manage/pages/system/update', ['section' => 'status', 'maintenance' => true, 'maintenance_message' => 'Back soon'])
            ->assertOk()->assertJson(['ok' => true])->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Maintenance mode is ON'));
        $this->assertSame('Back soon', SiteSetting::get('system.maintenance_message'));
        $this->api()->postJson('/api/content/manage/pages/system/update', ['section' => 'mail', 'enabled' => true, 'encryption' => 'tls'])->assertStatus(422)->assertJsonStructure(['errors' => ['host']]);
        $this->api()->postJson('/api/content/manage/pages/system/update', ['section' => 'status', 'maintenance' => false])->assertOk();
        $this->api()->getJson('/api/content/manage/pages/cache')->assertOk()->assertJsonStructure(['props' => ['status']]);
        $this->api()->getJson('/api/content/manage/pages/nope')->assertNotFound();
        $this->withToken('wrong')->getJson('/api/content/manage/pages/system')->assertNotFound();
    }

    public function test_enquiries_are_listed_and_followed_up_nothing_deleted(): void
    {
        $m = Message::create(['name' => 'Mr Tan', 'email' => 'tan@example.com', 'phone' => '81234567', 'subject' => 'Leak', 'message' => 'Kitchen sink', 'status' => 'new']);
        $this->api()->getJson('/api/content/manage/pages/enquiries')->assertOk()->assertJsonPath('props.messages.data.0.name', 'Mr Tan');
        $this->api()->postJson("/api/content/manage/pages/enquiries/status/{$m->id}", ['status' => 'contacted'])->assertOk()->assertJsonPath('message', 'Marked “Contacted”.');
        $this->assertSame('contacted', $m->fresh()->status);
        $this->api()->postJson("/api/content/manage/pages/enquiries/spam/{$m->id}", ['spam' => true])->assertOk();
        $this->assertTrue($m->fresh()->is_spam);
        $this->assertSame(1, Message::count());
        $this->api()->postJson('/api/content/manage/pages/enquiries/status/999', ['status' => 'won'])->assertNotFound();
    }

    public function test_contact_and_business_pages_are_read(): void
    {
        $this->api()->getJson('/api/content/manage/pages/contact')->assertOk()->assertJsonStructure(['props']);
        $this->api()->getJson('/api/content/manage/pages/business')->assertOk()->assertJsonStructure(['props']);
    }
}
