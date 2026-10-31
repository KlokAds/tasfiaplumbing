<?php

namespace Tests\Feature;

use App\Models\Footer;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ApiKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The billing app runs the website from there: people and roles, keys, tracking codes, and signing the owner in. */
class ManageApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-0123456789abcdef';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::putMany(['content_api.token_hash' => hash('sha256', self::TOKEN)]);
        $this->owner = User::factory()->create(['email' => 'owner@example.com', 'is_active' => true]);
        $this->owner->forceFill(['is_hidden' => true])->save();
        $this->owner->assignRole(config('admin.super_role'));
        Notification::fake();
    }

    private function api(): static
    {
        return $this->withToken(self::TOKEN);
    }

    public function test_people_get_a_role_or_lose_access_and_the_owner_is_never_touched(): void
    {
        $this->api()->getJson('/api/content/manage')->assertOk()->assertJsonPath('users', [])->assertJsonStructure(['roles', 'modules', 'keys', 'google', 'tracking', 'chat', 'leads_week']);

        $this->api()->postJson('/api/content/manage/users', ['email' => 'Nadia@Example.com', 'name' => 'Nadia', 'role' => 'editor'])->assertOk()->assertJson(['created' => true]);
        $nadia = User::where('email', 'nadia@example.com')->sole();
        $this->assertTrue($nadia->hasRole('editor'));
        $this->assertTrue($nadia->is_active);
        Notification::assertSentTo($nadia, \App\Notifications\AdminPasswordReset::class);

        $this->api()->postJson('/api/content/manage/users', ['email' => 'nadia@example.com', 'role' => 'writer'])->assertOk()->assertJson(['created' => false]);
        $this->assertTrue($nadia->fresh()->hasRole('writer'));
        $this->api()->getJson('/api/content/manage')->assertJsonPath('users.0.role', 'writer');

        $this->api()->postJson('/api/content/manage/users', ['email' => 'nadia@example.com', 'role' => 'none'])->assertOk();
        $this->assertFalse($nadia->fresh()->is_active, 'access taken away, the account is kept');
        $this->assertSame(1, User::where('email', 'nadia@example.com')->count());

        $this->api()->postJson('/api/content/manage/users', ['email' => 'x@example.com', 'role' => config('admin.super_role')])->assertStatus(422);
        $this->api()->postJson('/api/content/manage/users', ['email' => 'owner@example.com', 'role' => 'writer'])->assertStatus(422);
        $this->assertTrue($this->owner->fresh()->hasRole(config('admin.super_role')));
    }

    public function test_roles_never_get_the_owners_permissions(): void
    {
        $this->api()->putJson('/api/content/manage/roles/editor', ['permissions' => ['services.view', 'services.edit', 'settings.edit', 'system.update', 'users.create', 'made.up']])->assertOk();
        $this->assertEqualsCanonicalizing(['services.view', 'services.edit'], Role::findByName('editor')->permissions->pluck('name')->all());

        $this->api()->postJson('/api/content/manage/roles', ['label' => 'Blog only', 'permissions' => ['articles.create']])->assertOk()->assertJson(['name' => 'blog-only']);
        $this->assertSame(['articles.create'], Role::findByName('blog-only')->permissions->pluck('name')->all());
        $this->api()->putJson('/api/content/manage/roles/' . config('admin.super_role'), ['permissions' => []])->assertStatus(422);
    }

    public function test_tracking_codes_and_keys_are_set_from_the_billing_app(): void
    {
        Footer::query()->delete();
        Footer::create(['g_tag' => null]);
        $this->api()->putJson('/api/content/manage/settings', ['gtm' => 'GTM-ABC123', 'gsc_verification' => 'abc'])->assertOk();
        $this->assertSame('GTM-ABC123', Footer::first()->g_tag);
        $this->assertSame('abc', SiteSetting::get('seo.gsc_verification'));
        $this->api()->putJson('/api/content/manage/settings', ['ga4' => 'nope'])->assertStatus(422);

        $this->api()->putJson('/api/content/manage/keys/github', ['value' => 'github_pat_secret1234'])->assertOk()->assertJson(['saved' => true, 'last4' => '1234']);
        $this->assertSame('github_pat_secret1234', ApiKeys::value('github'));
        $this->api()->getJson('/api/content/manage')->assertJsonMissing(['github_pat_secret1234']);
        $this->api()->putJson('/api/content/manage/keys/nope', ['value' => 'x'])->assertNotFound();
        $this->withToken('wrong')->getJson('/api/content/manage')->assertNotFound();
    }

    public function test_the_owner_is_signed_in_by_a_signed_link_once(): void
    {
        $exp = time() + 60;
        $next = '/admin/insights/google';
        $sig = hash_hmac('sha256', "{$exp}|{$next}", hash('sha256', self::TOKEN));
        $url = '/admin/sso?' . http_build_query(['exp' => $exp, 'next' => $next, 'sig' => $sig]);
        $this->get($url)->assertRedirect($next);
        $this->assertAuthenticatedAs($this->owner);

        auth()->logout();
        $this->get($url)->assertForbidden();
        $bad = '/admin/sso?' . http_build_query(['exp' => $exp, 'next' => '/admin', 'sig' => $sig]);
        $this->get($bad)->assertForbidden();
        $this->assertGuest();
    }
}
