<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Support\RewriteImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Rewrites uploaded as JSON lines become changes waiting for approval, with a checklist; the live
 * article stays as it is until an approver approves, and nothing is imported twice.
 */
class RewriteImportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(RewriteImport::folder());
        parent::tearDown();
    }

    private function rewrite(string $slug): array
    {
        $body = '<p>A door closer controls how a door shuts. Most problems come from leaking oil or a loose arm.</p>'
            . '<h2>Why does a door closer leak?</h2><p>' . str_repeat('The seal wears out with daily use in humid weather. ', 110) . '</p>'
            . '<h2>Can I adjust it myself?</h2><p>' . str_repeat('Turn the valve a quarter turn and test the door. ', 100) . '</p>'
            . '<h2>Key takeaways</h2><ul><li>Check the oil.</li></ul>'
            . '<p data-related-service="1">See our <a href="/service/door-closer">door closer repair</a>.</p>'
            . '<img src="/uploads/closer.jpg" alt="closer">';

        return [
            'slug' => $slug, 'name' => 'Door closer repair', 'note' => 'Clearer answers and FAQs.',
            'payload' => [
                'desc' => $body,
                'excerpt' => 'How to tell why a door closer leaks or slams.',
                'meta_desc' => str_repeat('Door closer repair in Singapore. ', 4) . 'Call us.',
                'faqs' => array_map(fn ($i) => ['question' => "Question $i?", 'answer' => "Answer $i."], range(1, 4)),
            ],
        ];
    }

    public function test_rewrites_become_changes_waiting_for_approval_with_a_checklist(): void
    {
        $live = BlogDetail::create([
            'name' => 'Door closer repair', 'slug' => 'door-closer-repair', 'status' => BlogDetail::PUBLISHED,
            'desc' => '<p>Old text.</p><p data-related-service="1">x</p><img src="/uploads/closer.jpg" alt="closer">',
        ]);
        File::ensureDirectoryExists(RewriteImport::folder());
        File::put(RewriteImport::folder() . '/door.jsonl', implode("\n", [
            json_encode($this->rewrite('door-closer-repair')),
            json_encode($this->rewrite('no-such-article')),
        ]) . "\n");

        $this->artisan('articles:import-rewrites')->assertSuccessful();

        $this->assertSame('<p>Old text.</p><p data-related-service="1">x</p><img src="/uploads/closer.jpg" alt="closer">', $live->fresh()->desc, 'the live page is unchanged');
        $change = ArticleRevision::sole();
        $this->assertSame('pending', $change->status);
        $this->assertNull($change->auto_approve_at);
        $this->assertCount(4, $change->payload['faqs']);
        $this->assertStringStartsWith('AI rewrite: Clearer answers and FAQs.', $change->note);
        $this->assertStringContainsString('✓ Key takeaways section', $change->note);
        $this->assertStringContainsString('✓ All photos kept', $change->note);
        $this->assertStringContainsString('✓ No prices', $change->note);
        $this->assertFileDoesNotExist(RewriteImport::folder() . '/door.jsonl');
        $this->assertFileExists(RewriteImport::folder() . '/.htaccess');

        // The same file again (or a rewrite of an article that was rejected) is not imported twice.
        File::put(RewriteImport::folder() . '/door.jsonl', json_encode($this->rewrite('door-closer-repair')));
        $this->artisan('articles:import-rewrites');
        $this->assertSame(1, ArticleRevision::count());

        // Approving puts the rewrite live.
        $owner = User::factory()->create(['is_active' => true])->assignRole('super-admin');
        $this->actingAs($owner)->post("/admin/revisions/{$change->id}/approve")->assertRedirect();
        $this->assertStringContainsString('Key takeaways', $live->fresh()->desc);
        $this->assertSame(4, $live->fresh()->faqs()->count());
    }

    public function test_the_checklist_flags_what_to_look_at(): void
    {
        $live = BlogDetail::create(['name' => 'X', 'slug' => 'x', 'status' => BlogDetail::PUBLISHED, 'desc' => '<img src="/uploads/a.jpg">']);
        $list = RewriteImport::checklist($live, ['desc' => '<p>Short. It costs $80 and we delve into it.</p>', 'meta_desc' => 'Too short', 'faqs' => []]);

        $this->assertFalse($list['Length 1200–1500 words (9)']);
        $this->assertFalse($list['All photos kept (1 missing)']);
        $this->assertFalse($list['No prices']);
        $this->assertFalse($list['No AI-style phrases']);
        $this->assertFalse($list['Key takeaways section (GEO)']);
    }
}
