<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\SourceCheckFound;
use App\Support\SourceCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Source check: sentences of articles waiting for approval are searched on the web (Brave Search).
 * The key is saved in System → API keys (tests/Feature/ApiKeysTest.php).
 */
class SourceCheckTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'BSA-test-key-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        SourceCheck::$pause = 0;
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(['is_active' => true] + $attrs)->assignRole($role);
    }

    private function text(): string
    {
        return '<h2>How much does it cost</h2>'
            . '<p>Most floor spring jobs in Singapore shops take about two hours when the old box comes out cleanly.</p>'
            . '<p>We always check the top pivot first because a worn pivot makes a new floor spring fail early.</p>'
            . '<p>Short line.</p>';
    }

    public function test_it_picks_long_sentences_and_skips_headings_and_short_lines(): void
    {
        $this->assertSame([
            'Most floor spring jobs in Singapore shops take about two hours when the old box comes out cleanly.',
            'We always check the top pivot first because a worn pivot makes a new floor spring fail early.',
        ], SourceCheck::sentences($this->text()));
    }

    public function test_waiting_articles_are_checked_and_copied_text_is_reported(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');
        SourceCheck::saveKey(self::KEY);
        Http::fake(function ($request) {
            $copied = str_contains(urldecode((string) $request->url()), 'top pivot');

            return Http::response(['web' => ['results' => $copied
                ? [['url' => 'https://other-site.example/floor-spring'], ['url' => config('app.url') . '/blogs/own-page']]
                : []]]);
        });

        $this->actingAs($writer)->post('/admin/blogs', ['name' => 'Floor Spring Repair Singapore', 'desc' => $this->text(), 'faqs' => [], 'intent' => 'submit']);
        $blog = BlogDetail::firstOrFail();

        $this->assertSame(1, SourceCheck::due());
        $check = $blog->fresh()->source_check;
        $this->assertSame(2, $check['total']);
        $this->assertSame(1, $check['found']);
        $this->assertSame(['https://other-site.example/floor-spring'], $check['matches'][0]['urls'], 'our own site is not a match');
        Notification::assertSentTo($owner, SourceCheckFound::class);

        // Unchanged text is not searched again.
        $this->assertSame(0, SourceCheck::due());

        // Approvers see it; the writer does not.
        $this->actingAs($owner)->get('/admin/blogs?tab=review')->assertInertia(fn ($page) => $page->where('blogs.data.0.source_check.found', 1));
        $this->actingAs($writer)->get('/admin/blogs?tab=review')->assertInertia(fn ($page) => $page->missing('blogs.data.0.source_check'));
    }

    public function test_changes_to_live_articles_are_checked_and_nothing_runs_without_a_key(): void
    {
        Http::fake(['api.search.brave.com/*' => Http::response(['web' => ['results' => []]])]);
        $writer = $this->user('writer');
        $blog = BlogDetail::create(['name' => 'Live', 'slug' => 'live', 'desc' => '<p>Old</p>', 'status' => BlogDetail::PUBLISHED]);
        $revision = ArticleRevision::create(['article_id' => $blog->id, 'user_id' => $writer->id, 'status' => 'pending', 'payload' => ['name' => 'Live', 'desc' => $this->text(), 'faqs' => []]]);

        $this->assertSame(0, SourceCheck::due());
        Http::assertNothingSent();

        SourceCheck::saveKey(self::KEY);
        $this->assertSame(1, SourceCheck::due());
        $this->assertSame(0, $revision->fresh()->source_check['found']);
    }
}
