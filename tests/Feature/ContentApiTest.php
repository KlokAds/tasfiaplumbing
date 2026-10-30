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

    public function test_markup_from_the_hub_s_editor_is_kept_clean(): void
    {
        $html = '<h2>Repair or replace?</h2><ul><li><p>First point</p></li></ul>'
            . '<table style="min-width: 50px;"><colgroup><col style="min-width: 25px;"><col style="min-width: 25px;"></colgroup><tbody>'
            . '<tr><th colspan="1" rowspan="1"><p>Repair</p></th><th colspan="1" rowspan="1"><p>Replace</p></th></tr>'
            . '<tr><td colspan="1" rowspan="1"><p>Small leak</p></td><td colspan="1" rowspan="1"><p>Rusty tank</p></td></tr></tbody></table>';
        $id = $this->api()->postJson('/api/content/articles', ['title' => 'Water Heater Pipe Leak', 'body_html' => $html])->assertCreated()->json('id');

        $desc = BlogDetail::find($id)->desc;
        $this->assertStringContainsString('<ul><li><p>First point</p></li></ul>', $desc);
        $this->assertStringContainsString('<table><tbody><tr><th><p>Repair</p></th><th><p>Replace</p></th></tr><tr><td><p>Small leak</p></td>', $desc);
        $this->assertStringNotContainsString('style=', $desc);
        $this->assertStringNotContainsString('colgroup', $desc);
        $this->assertStringNotContainsString('colspan', $desc);
    }

    public function test_a_draft_is_checked_against_the_site_s_articles(): void
    {
        $text = implode(' ', array_map(fn ($i) => "Step {$i}: a sliding door that drags usually has worn rollers under panel {$i} and a dirty track.", range(1, 8)));
        $blog = BlogDetail::create(['name' => 'Sliding Door Rollers', 'slug' => 'sliding-door-rollers', 'desc' => '<p>' . $text . '</p>', 'status' => BlogDetail::PUBLISHED]);

        $r = $this->api()->postJson('/api/content/check', ['body_html' => '<p>' . $text . '</p>'])->assertOk();
        $this->assertSame($blog->id, $r->json('duplicate.id'));
        $this->assertGreaterThanOrEqual(15, $r->json('duplicate.percent'));
        $this->assertStringEndsWith('/blogs/sliding-door-rollers', $r->json('duplicate.url'));

        // The article being improved is not compared with itself.
        $this->api()->postJson('/api/content/check', ['body_html' => '<p>' . $text . '</p>', 'article_id' => $blog->id])->assertOk()->assertJsonPath('duplicate', null);
    }

    public function test_any_article_is_revised_in_place_with_a_backup_and_can_be_put_back(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $s = ServiceDetail::create(['name' => 'Door Lock Replacement', 'slug' => 'door-lock-replacement', 'desc' => 'x', 'is_active' => true]);
        $old = '<p>Old intro with a <a href="https://supplier.example/locks">supplier link</a>.</p><figure><img src="/Admin/Blog/Details/old.jpg" alt="old"></figure><h2>Why locks fail</h2><p>Dust.</p><table><tr><td>Size</td></tr></table>';
        $blog = BlogDetail::create(['name' => 'Why Door Locks Get Stiff', 'slug' => 'why-door-locks-get-stiff', 'desc' => $old, 'status' => BlogDetail::PUBLISHED, 'focus_keyword' => 'stiff door lock']);
        $blog->syncFaqs([['question' => 'Old question?', 'answer' => 'Old answer.']]);

        // The article list and service pages come with the info, for linking and for choosing what to update.
        $info = $this->api()->getJson('/api/content/info')->assertOk();
        $this->assertSame('/blogs/why-door-locks-get-stiff', $info->json('articles.0.path'));
        $this->assertSame('/service/door-lock-replacement', $info->json('services.0.path'));

        $text = $this->api()->getJson("/api/content/articles/{$blog->id}/text")->assertOk();
        $this->assertSame(2, $text->json('kept'));
        $this->assertStringContainsString('[[keep-1]]', $text->json('body_html'));
        $this->assertStringNotContainsString('old.jpg', $text->json('body_html'));

        $r = $this->api()->post("/api/content/articles/{$blog->id}/revise", [
            'title' => 'Why Door Locks Get Stiff and How to Fix Them',
            'body_html' => '<p>New intro. See our <a href="/service/door-lock-replacement">lock replacement</a> page, the <a href="https://supplier.example/locks">supplier</a>, <a href="https://spam.example">this</a> and <a href="javascript:alert(1)">that</a>.</p><p>[[keep-1]]</p><h2>Why locks fail</h2><h4>Dust</h4><blockquote><p>Clean it monthly.</p></blockquote><hr><p><u>Wear</u> and <s>rust</s>.</p>',
            'faqs' => [['question' => 'Is the site visit free?', 'answer' => 'Yes.']],
            'photos' => [UploadedFile::fake()->image('job.jpg')],
            'alts' => ['New lock fitted'],
        ], ['Accept' => 'application/json'])->assertOk();

        $blog->refresh();
        $this->assertSame('why-door-locks-get-stiff', $blog->slug, 'the address never changes');
        $this->assertSame(BlogDetail::PUBLISHED, $blog->status);
        $this->assertNotNull($blog->content_updated_at);
        $this->assertStringContainsString('<a href="/service/door-lock-replacement">', $blog->desc);
        $this->assertStringContainsString('<a href="https://supplier.example/locks" rel="noopener" target="_blank">', $blog->desc, 'links already in the article stay');
        $this->assertStringContainsString('<a href="https://spam.example" rel="noopener" target="_blank">this</a>', $blog->desc, 'links to other websites open in a new tab');
        $this->assertStringNotContainsString('javascript:', $blog->desc);
        $this->assertStringContainsString('old.jpg', $blog->desc);
        $this->assertStringContainsString('<table>', $blog->desc, 'a block the writer dropped is kept at the end');
        foreach (['<h4>Dust</h4>', '<blockquote><p>Clean it monthly.</p></blockquote>', '<hr>', '<u>Wear</u>', '<s>rust</s>'] as $kept) {
            $this->assertStringContainsString($kept, $blog->desc);
        }
        $this->assertStringContainsString('alt="New lock fitted"', $blog->desc);
        $this->assertSame(['Is the site visit free?'], $blog->faqs()->pluck('question')->all());

        $backup = $r->json('backup');
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('content-backups/' . $backup);

        $this->api()->postJson("/api/content/articles/{$blog->id}/restore", ['backup' => $backup])->assertOk();
        $blog->refresh();
        $this->assertSame('Why Door Locks Get Stiff', $blog->name);
        $this->assertSame($old, $blog->desc);
        $this->assertSame(['Old question?'], $blog->faqs()->pluck('question')->all());

        $this->api()->postJson("/api/content/articles/{$blog->id}/restore", ['backup' => '../../.env'])->assertUnprocessable();
    }
}
