<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\Location;
use App\Models\Price;
use App\Models\ServiceCategory;
use App\Models\ServiceDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function seedContent(): array
    {
        $cat = ServiceCategory::create(['name' => 'Plumbing', 'is_active' => true]);
        $service = ServiceDetail::create(['name' => 'Water Heater Repair', 'desc' => '<p>Body</p>', 'category_id' => $cat->id, 'is_active' => true]);
        Price::create(['service_id' => $service->id, 'item' => 'Storage heater replacement', 'price_from' => 250, 'price_to' => 450, 'unit' => 'unit', 'is_active' => true]);
        $service->syncFaqs([['question' => 'How much does it cost?', 'answer' => 'S$250 to S$450.']]);
        $location = Location::create(['name' => 'Tampines', 'is_active' => true, 'intro' => 'East side']);

        return [$cat, $service, $location];
    }

    public function test_every_public_page_opens(): void
    {
        [$cat, $service, $location] = $this->seedContent();
        BlogDetail::create(['name' => 'Heater Guide', 'desc' => '<h2>Why</h2><p>x</p>', 'status' => BlogDetail::PUBLISHED, 'primary_service_id' => $service->id]);

        foreach (['/', '/about', '/services', '/services/' . $cat->slug, '/service/' . $service->slug, '/pricing', '/locations', '/locations/' . $location->slug,
            '/projects', '/blogs', '/blogs/heater-guide', '/reviews', '/contact', '/privacy-policy', '/terms-of-service', '/sitemap.xml'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/articles')->assertRedirect('/blogs');
    }

    public function test_service_page_has_service_faq_and_breadcrumb_schema(): void
    {
        [, $service] = $this->seedContent();
        $html = $this->get('/service/' . $service->slug)->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"Service"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"minPrice":250', $html);
    }

    public function test_blog_search_never_shows_drafts(): void
    {
        BlogDetail::create(['name' => 'Secret Draft Heater', 'desc' => 'heater', 'status' => BlogDetail::DRAFT]);
        BlogDetail::create(['name' => 'Live Heater Guide', 'desc' => 'heater', 'status' => BlogDetail::PUBLISHED]);

        $this->get('/blogs?search=heater')->assertOk()->assertSee('Live Heater Guide')->assertDontSee('Secret Draft Heater');
    }

    public function test_sitemap_lists_categories_and_locations(): void
    {
        [$cat, , $location] = $this->seedContent();
        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('/services/' . $cat->slug, $xml);
        $this->assertStringContainsString('/locations/' . $location->slug, $xml);
        $this->assertStringContainsString('/pricing', $xml);
    }

    public function test_enquiry_is_saved_emailed_and_shown_in_the_bell(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \App\Models\ContactContent::create(['email' => 'office@example.test', 'phone' => '93730360', 'address' => 'Singapore']);
        $owner = \App\Models\User::factory()->create()->assignRole('super-admin');

        $this->post('/messages', ['name' => 'Kelvin', 'email' => 'k@example.test', 'phone' => '81234567', 'subject' => 'Water heater', 'message' => 'Leaking'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('messages', ['name' => 'Kelvin', 'is_read' => 0]);
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\NewEnquiry::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'office@example.test');
        \Illuminate\Support\Facades\Notification::assertSentTo($owner, \App\Notifications\NewEnquiry::class);
    }

    public function test_phone_is_formatted_for_display_and_dialling(): void
    {
        $this->assertSame(['+65 9373 0360', '+6593730360'], \App\Http\Middleware\HandleInertiaRequests::formatPhone('6593730360'));
        $this->assertSame(['+65 9373 0360', '+6593730360'], \App\Http\Middleware\HandleInertiaRequests::formatPhone('9373 0360'));
    }

    public function test_pasted_styles_are_removed_and_image_runs_become_a_gallery(): void
    {
        $html = '<p style="color: rgb(13,13,13)"><span style="font-family:Sohne">Hello</span></p><p><img src="/a.jpg"></p><p><img src="/b.jpg"></p><script>alert(1)</script>';
        $out = \App\Support\ContentHtml::render($html, 'Tiling');

        $this->assertStringNotContainsString('style=', $out);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('content-gallery', $out);
        $this->assertStringContainsString('alt="Tiling"', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function test_global_search_finds_public_content_only(): void
    {
        [, $service] = $this->seedContent();
        BlogDetail::create(['name' => 'Water Heater Cost Guide', 'desc' => 'x', 'status' => BlogDetail::PUBLISHED]);
        BlogDetail::create(['name' => 'Water Heater Draft', 'desc' => 'x', 'status' => BlogDetail::DRAFT]);

        $json = $this->getJson('/search/suggest?q=water heater')->assertOk()->json();
        $this->assertSame($service->name, $json['services'][0]['title']);
        $this->assertSame(['Water Heater Cost Guide'], array_column($json['articles'], 'title'));

        $html = $this->get('/search?q=water')->assertOk()->getContent();
        $this->assertStringContainsString('noindex', $html);
    }

    public function test_copyright_uses_founding_year_and_current_year(): void
    {
        \App\Models\SiteSetting::putMany(['business.founded_year' => '2016', 'business.legal_name' => 'Tasfia Plumbing Service Singapore']);
        $this->assertSame('© 2016–' . date('Y') . ' Tasfia Plumbing Service Singapore. All rights reserved.',
            \App\Http\Middleware\HandleInertiaRequests::copyright('Copyright Ⓒ2016 - 2026 | All Right Reserved', 'Tasfia'));
    }

    public function test_hidden_service_returns_404(): void
    {
        ServiceDetail::create(['name' => 'Old Service', 'desc' => 'x', 'is_active' => false]);
        $this->get('/service/old-service')->assertNotFound();
    }
}
