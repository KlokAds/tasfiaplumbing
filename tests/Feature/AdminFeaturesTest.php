<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\FeedBackContent;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ContentQuality;
use App\Support\GoogleReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_testimonials_url_moves_to_reviews(): void
    {
        $this->withoutVite();
        $this->get('/testimonials')->assertRedirect('/reviews')->assertStatus(301);
        FeedBackContent::create(['name' => 'Kelvin T.', 'desc' => 'Fast and tidy work.', 'rating' => 5]);
        $this->get('/reviews')->assertOk()->assertSee('Kelvin T.', false);
    }

    public function test_hidden_own_reviews_are_not_shown(): void
    {
        $this->withoutVite();
        FeedBackContent::create(['name' => 'Hidden Person', 'desc' => 'x', 'rating' => 5, 'is_active' => false]);
        $this->get('/reviews')->assertOk()->assertDontSee('Hidden Person', false);
    }

    public function test_google_reviews_are_fetched_filtered_and_key_is_encrypted(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response([
            'rating' => 4.8, 'userRatingCount' => 120, 'googleMapsUri' => 'https://maps.google.com/?cid=1',
            'reviews' => [
                ['rating' => 5, 'text' => ['text' => 'Great job'], 'authorAttribution' => ['displayName' => 'Ann']],
                ['rating' => 2, 'text' => ['text' => 'Late'], 'authorAttribution' => ['displayName' => 'Bob']],
            ],
        ])]);
        $this->actingAs($this->user('super-admin'));

        $this->post('/admin/reviews/google', ['enabled' => true, 'place_id' => 'ChIJabc123', 'api_key' => 'AIzaTESTKEY123', 'min_rating' => '4', 'show_own' => true])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNotSame('AIzaTESTKEY123', SiteSetting::where('key', 'reviews.google_api_key')->value('value'));
        $this->assertSame('AIzaTESTKEY123', GoogleReviews::apiKey());
        $result = GoogleReviews::get();
        $this->assertSame(4.8, $result['rating']);
        $this->assertCount(1, $result['reviews']);
        $this->assertSame('Ann', $result['reviews'][0]['name']);
    }

    public function test_writer_cannot_open_reviews_or_services(): void
    {
        $this->actingAs($this->user('writer'));
        $this->get('/admin/reviews')->assertForbidden();
        $this->get('/admin/services')->assertForbidden();
        $this->post('/admin/system/cache/clear', ['scope' => 'data'])->assertForbidden();
    }

    public function test_editor_can_view_and_edit_but_not_delete_services(): void
    {
        $this->withoutVite();
        $service = ServiceDetail::create(['name' => 'Tiling', 'desc' => 'x']);
        $this->actingAs($this->user('editor'));

        $this->get('/admin/services')->assertOk();
        $this->delete("/admin/services/{$service->id}")->assertForbidden();
    }

    public function test_custom_role_with_granular_permissions(): void
    {
        $this->actingAs($this->user('super-admin'));
        $this->post('/admin/roles', ['name' => 'SEO assistant', 'permissions' => ['redirects.view', 'redirects.create', 'page_seo.view']])->assertRedirect();

        $assistant = User::factory()->create()->assignRole('seo-assistant');
        $this->actingAs($assistant);
        $this->get('/admin/redirects')->assertOk();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_cache_clear_works_for_admin(): void
    {
        $this->actingAs($this->user('admin'));
        $this->post('/admin/system/cache/clear', ['scope' => 'data'])->assertRedirect()->assertSessionHas('success');
    }

    public function test_duplicate_article_titles_are_refused(): void
    {
        BlogDetail::create(['name' => 'Aircon Chemical Wash Cost Singapore', 'desc' => 'x']);
        ServiceDetail::create(['name' => 'Water Heater Repair', 'desc' => 'x', 'meta_title' => 'Water Heater Repair Singapore | Same Day']);
        $this->actingAs($this->user('super-admin'));

        $this->post('/admin/blogs', ['name' => 'aircon chemical wash cost singapore ', 'desc' => '<p>x</p>', 'intent' => 'draft'])
            ->assertSessionHasErrors('name');
        $this->post('/admin/blogs', ['name' => 'Water heater guide', 'meta_title' => 'Water Heater Repair Singapore | Same Day', 'desc' => '<p>x</p>', 'intent' => 'draft'])
            ->assertSessionHasErrors('meta_title');

        $this->getJson('/admin/content-check/title?type=article&text=Aircon%20Chemical%20Wash%20Cost%20Singapore')
            ->assertOk()->assertJsonPath('conflict.type', 'article');
    }

    public function test_an_article_may_keep_its_own_title(): void
    {
        $blog = BlogDetail::create(['name' => 'My Unique Guide Title', 'desc' => 'x']);
        $this->actingAs($this->user('super-admin'));

        $this->post("/admin/blogs/{$blog->id}", ['name' => 'My Unique Guide Title', 'meta_title' => 'My Unique Guide Title', 'desc' => '<p>y</p>', 'intent' => 'draft'])
            ->assertSessionHasNoErrors();
    }

    public function test_content_quality_scores_a_strong_article_higher(): void
    {
        $weak = ContentQuality::analyze(['type' => 'article', 'name' => 'Guide', 'body' => '<p>Short text.</p>']);
        $body = '<p>Tasfia fixes aircon in Singapore HDB flats. A chemical wash costs S$80 to S$150 per unit.</p>'
            . '<h2>How much does it cost?</h2><ul><li>Wall unit S$80</li><li>Ceiling cassette S$150</li></ul>'
            . '<h2>Why do HDB aircons clog?</h2><p>' . str_repeat('In our experience our team sees 3 causes on site in 90% of 500 jobs. ', 60) . '</p>'
            . '<h3>Rules</h3><p>See <a href="https://www.nea.gov.sg/x">NEA</a> and <a href="/service/aircon">our aircon service</a> and <a href="/blogs/other">guide</a>.</p>';
        $strong = ContentQuality::analyze([
            'type' => 'article', 'name' => 'Aircon Chemical Wash Cost in Singapore (2026)', 'meta_desc' => str_repeat('Clear prices for aircon chemical wash. ', 3),
            'excerpt' => 'An aircon chemical wash in Singapore costs S$80–S$150 per unit, done in about an hour.', 'body' => $body,
            'focus_keyword' => 'aircon chemical wash', 'image' => 'x.jpg', 'has_service' => true, 'updated_at' => now()->toIso8601String(),
            'faqs' => [['question' => 'a?', 'answer' => 'b'], ['question' => 'c?', 'answer' => 'd'], ['question' => 'e?', 'answer' => 'f']],
            'author' => ['name' => 'Rahim', 'job_title' => 'Aircon technician', 'bio' => '12 years'],
        ]);

        $this->assertLessThan(40, $weak['score']);
        $this->assertGreaterThan(80, $strong['score']);
        $this->assertArrayHasKey('eeat', $strong['pillars']);
    }
}
