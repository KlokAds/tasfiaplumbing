<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\Price;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** The content hub API: a secret token, read services and prices, add drafts only. */
class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-0123456789abcdef';

    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::putMany(['content_api.token_hash' => hash('sha256', self::TOKEN)]);
        @mkdir(public_path('Admin/Blog/Details'), 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (BlogDetail::pluck('image')->filter() as $path) {
            @unlink(public_path($path)); // only the photos this test uploaded
        }
        foreach (BlogDetail::pluck('desc')->flatMap(fn ($d) => preg_match_all('#src="/([^"]+)"#', (string) $d, $m) ? $m[1] : []) as $path) {
            @unlink(public_path($path));
        }
        parent::tearDown();
    }

    private function api(): static
    {
        return $this->withToken(self::TOKEN);
    }

    public function test_without_the_right_token_the_api_does_not_exist(): void
    {
        $this->getJson('/api/content/info')->assertNotFound();
        $this->withToken('wrong')->getJson('/api/content/info')->assertNotFound();
        SiteSetting::putMany(['content_api.token_hash' => null]);
        $this->withToken(self::TOKEN)->getJson('/api/content/info')->assertNotFound();
    }

    public function test_info_lists_live_services_with_approved_prices(): void
    {
        $s = ServiceDetail::create(['name' => 'Door Lock Replacement', 'desc' => 'x', 'is_active' => true]);
        ServiceDetail::create(['name' => 'Hidden service', 'desc' => 'x', 'is_active' => false]);
        Price::create(['service_id' => $s->id, 'item' => 'Door lock', 'price_from' => 60, 'price_to' => 200, 'unit' => 'per lock', 'is_active' => true]);

        $r = $this->api()->getJson('/api/content/info')->assertOk();
        $this->assertSame(['Door Lock Replacement'], collect($r->json('services'))->pluck('name')->all());
        $this->assertSame(60, $r->json('services.0.prices.0.from'));
        $this->assertNotEmpty($r->json('avoid_phrases'));
    }

    public function test_a_draft_is_saved_with_photos_and_is_never_published(): void
    {
        $owner = User::factory()->create(['email' => 'mujxaid@gmail.com', 'is_active' => true]);
        $s = ServiceDetail::create(['name' => 'Door Lock Replacement', 'desc' => 'x', 'is_active' => true]);

        $r = $this->api()->post('/api/content/articles', [
            'title' => 'Door Lock Replacement in a Bedok HDB Flat',
            'excerpt' => 'A stiff lock was replaced.',
            'body_html' => '<p style="color:red">Intro</p><script>alert(1)</script><h2>What was wrong?</h2><p>Stiff.</p><h2>What we did</h2><p>Replaced.</p>',
            'service_id' => $s->id,
            'faqs' => [['question' => 'Is the site visit free?', 'answer' => 'Yes.']],
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'alts' => ['Old lock', 'New lock fitted'],
        ], ['Accept' => 'application/json'])->assertCreated();

        $blog = BlogDetail::findOrFail($r->json('id'));
        $this->assertSame(BlogDetail::DRAFT, $blog->status);
        $this->assertFalse($blog->is_active);
        $this->assertSame($owner->id, $blog->author_id);
        $this->assertSame($s->id, $blog->primary_service_id);
        $this->assertStringNotContainsString('<script', $blog->desc);
        $this->assertStringNotContainsString('style=', $blog->desc);
        $this->assertStringContainsString('alt="New lock fitted"', $blog->desc);
        $this->assertCount(1, $blog->faqs);
        $this->assertStringContainsString('/admin/blogs?tab=mine&edit=' . $blog->id, $r->json('edit_url'));
    }

    public function test_the_hub_can_edit_publish_unpublish_and_delete_only_its_own_articles(): void
    {
        $id = $this->api()->postJson('/api/content/articles', ['title' => 'Glass Door Repair in Jurong', 'body_html' => '<p>Text</p><figure><img src="/x.jpg"></figure>'])->assertCreated()->json('id');
        $site = BlogDetail::create(['name' => 'Written on the site', 'slug' => 'written-on-the-site', 'desc' => '<p>x</p>', 'status' => BlogDetail::PUBLISHED]);

        // Edit: the text changes, the photos already in it stay.
        BlogDetail::whereKey($id)->update(['desc' => '<p>Text</p><figure><img src="/Admin/Blog/Details/keep.jpg" alt="a"></figure>']);
        $this->api()->putJson("/api/content/articles/{$id}", ['title' => 'Glass Door Repair in Jurong West', 'body_html' => '<p>New text</p>'])->assertOk();
        $blog = BlogDetail::find($id);
        $this->assertSame('Glass Door Repair in Jurong West', $blog->name);
        $this->assertStringContainsString('New text', $blog->desc);
        $this->assertStringContainsString('keep.jpg', $blog->desc);

        // Schedule, then publish now, then take it off the site.
        $this->api()->postJson("/api/content/articles/{$id}/publish", ['at' => now()->addDays(2)->toIso8601String()])->assertOk()->assertJsonPath('status', BlogDetail::SCHEDULED);
        $this->api()->postJson("/api/content/articles/{$id}/publish")->assertOk()->assertJsonPath('status', BlogDetail::PUBLISHED);
        $this->assertTrue(BlogDetail::find($id)->is_active);
        $this->api()->postJson("/api/content/articles/{$id}/unpublish")->assertOk()->assertJsonPath('status', BlogDetail::DRAFT);

        // Articles written on the site are out of reach.
        $this->api()->putJson("/api/content/articles/{$site->id}", ['title' => 'Changed', 'body_html' => '<p>x</p>'])->assertNotFound();
        $this->api()->postJson("/api/content/articles/{$site->id}/unpublish")->assertNotFound();
        $this->api()->deleteJson("/api/content/articles/{$site->id}")->assertNotFound();
        $this->assertSame(BlogDetail::PUBLISHED, $site->fresh()->status);

        $this->api()->deleteJson("/api/content/articles/{$id}")->assertOk()->assertJsonPath('deleted', true);
        $this->assertNull(BlogDetail::find($id));
    }
}
