<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\NotFoundLog;
use App\Models\Price;
use App\Models\Redirect;
use App\Models\ServiceDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAdminTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['is_active' => true])->assignRole('super-admin');
    }

    private function service(array $attrs = []): ServiceDetail
    {
        return ServiceDetail::create($attrs + ['name' => 'Water Heater Repair', 'desc' => 'Body text']);
    }

    public function test_renaming_a_service_keeps_its_slug(): void
    {
        $service = $this->service();
        $service->update(['name' => 'Water Heater Repair & Installation']);

        $this->assertSame('water-heater-repair', $service->fresh()->slug);
        $this->assertSame(0, Redirect::count());
    }

    public function test_changing_a_slug_creates_a_301_that_the_old_url_follows(): void
    {
        $service = $this->service();
        $service->update(['slug' => 'water-heater-repair-singapore']);

        $this->assertDatabaseHas('redirects', [
            'from_path' => '/service/water-heater-repair',
            'to_path' => '/service/water-heater-repair-singapore',
            'code' => 301,
        ]);
        $this->get('/service/water-heater-repair')->assertRedirect('/service/water-heater-repair-singapore')->assertStatus(301);
    }

    public function test_redirect_chains_are_flattened_and_reverting_a_slug_removes_the_loop(): void
    {
        $service = $this->service();
        $service->update(['slug' => 'step-two']);
        $service->update(['slug' => 'step-three']);

        $this->assertSame('/service/step-three', Redirect::where('from_path', '/service/water-heater-repair')->value('to_path'));

        $service->update(['slug' => 'water-heater-repair']);
        $this->assertNull(Redirect::where('from_path', '/service/water-heater-repair')->first());
    }

    public function test_deleted_article_returns_410(): void
    {
        $blog = BlogDetail::create(['name' => 'Old Guide', 'desc' => 'x']);
        $blog->delete();

        $this->get('/blogs/old-guide')->assertStatus(410);
    }

    public function test_legacy_query_string_url_redirects_and_unknown_urls_are_logged(): void
    {
        Redirect::create(['from_path' => '/service-detail.php?id=5', 'to_path' => '/service/aircon', 'code' => 301]);

        $this->get('/service-detail.php?id=5')->assertRedirect('/service/aircon');

        $this->get('/no-such-page-xyz')->assertNotFound();
        $this->assertSame(1, NotFoundLog::where('path', '/no-such-page-xyz')->value('hits'));
    }

    public function test_csv_import_rejects_homepage_targets_and_flattens_chains(): void
    {
        $this->actingAs($this->owner());
        $csv = "from,to,code\n/a.html,/b.html,301\n/b.html,/service/final,301\n/old-promo.html,/,301\n/gone.html,,410\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('map.csv', $csv);

        $this->post('/admin/redirects/import', ['file' => $file])->assertRedirect();

        $this->assertSame('/service/final', Redirect::where('from_path', '/a.html')->value('to_path'));
        $this->assertNull(Redirect::where('from_path', '/old-promo.html')->first());
        $this->assertSame(410, Redirect::where('from_path', '/gone.html')->value('code'));
    }

    public function test_service_update_saves_seo_fields_and_faqs(): void
    {
        $this->actingAs($this->owner());
        $service = $this->service();

        $this->post("/admin/services/{$service->id}", [
            'name' => 'Water Heater Repair',
            'desc' => 'Updated body',
            'short_summary' => 'Same-day water heater repair across Singapore.',
            'response_time' => 'Same day',
            'warranty' => '90 days',
            'noindex' => false,
            'faqs' => [
                ['question' => 'How much does it cost?', 'answer' => 'Usually S$80 to S$250.'],
                ['question' => '', 'answer' => 'ignored blank row'],
            ],
        ])->assertRedirect();

        $service->refresh();
        $this->assertSame('Same day', $service->response_time);
        $this->assertSame(1, $service->faqs()->count());
    }

    public function test_price_label_is_the_quoted_format(): void
    {
        $price = new Price(['price_from' => 80, 'price_to' => 250, 'unit' => 'unit']);
        $this->assertSame('S$80 – S$250 / unit', $price->label);
    }

    public function test_admin_seo_pages_render(): void
    {
        $this->withoutVite();
        $this->actingAs($this->owner());
        $this->service();

        foreach (['/admin/dashboard', '/admin/services', '/admin/service-categories', '/admin/locations', '/admin/pricing',
            '/admin/blogs', '/admin/faqs', '/admin/redirects', '/admin/seo/health', '/admin/seo/settings', '/admin/system/update', '/admin/page-seo', '/admin/users',
            '/admin/roles', '/admin/account', '/admin/media'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_system_update_needs_the_permission(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true])->assignRole('admin'));

        $this->get('/admin/system/update')->assertForbidden();
        $this->postJson('/admin/system/update/deploy', ['password' => 'x'])->assertForbidden();
    }

    public function test_cache_clear_requires_login(): void
    {
        $this->get('/clear')->assertRedirect('/admin/login');
    }
}
