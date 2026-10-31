<?php

namespace Tests\Feature;

use App\Http\Middleware\FromBilling;
use App\Models\Message;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\NewEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Enquiries, people and roles, Google connect and the system are run from the billing app: here only from its signed-in link. */
class BillingOnlyPagesTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-0123456789abcdef';

    private const PAGES = ['/admin/messages', '/admin/users', '/admin/roles', '/admin/system/settings', '/admin/system/cache', '/admin/system/update', '/admin/insights/google'];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::putMany(['content_api.token_hash' => hash('sha256', self::TOKEN)]);
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole(config('admin.super_role'));
        $this->flushSession();
    }

    public function test_signed_in_here_the_pages_run_from_billing_do_not_exist(): void
    {
        $this->actingAs($this->owner);
        foreach (self::PAGES as $page) {
            $this->get($page)->assertNotFound();
        }
        $this->post('/admin/messages-scan-spam')->assertNotFound();
        $this->get('/admin/blogs')->assertOk();
    }

    public function test_the_billing_apps_sign_in_link_opens_them(): void
    {
        $exp = time() + 60;
        $next = '/admin/messages';
        $sig = hash_hmac('sha256', "{$exp}|{$next}", hash('sha256', self::TOKEN));
        $this->get('/admin/sso?' . http_build_query(['exp' => $exp, 'next' => $next, 'sig' => $sig]))->assertRedirect($next);

        foreach (self::PAGES as $page) {
            $this->get($page)->assertOk();
        }
        $this->travel(FromBilling::HOURS * 60 + 1)->minutes();
        $this->get('/admin/messages')->assertNotFound();
    }

    public function test_links_point_to_the_billing_apps_page_for_this_website(): void
    {
        $page = 'https://billing.example.com/admin/websites/3';
        $this->withToken(self::TOKEN)->withHeader('X-Billing-Page', $page)->getJson('/api/content/info')->assertOk();
        $this->withToken(self::TOKEN)->withHeader('X-Billing-Page', 'javascript:alert(1)')->getJson('/api/content/info')->assertOk();

        $this->assertSame($page . '/enquiries', \App\Support\Billing::url('enquiries'));
        $message = Message::create(['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Door stuck']);
        $this->assertSame($page . '/enquiries', (new NewEnquiry($message))->toArray($this->owner)['url']);
    }
}
