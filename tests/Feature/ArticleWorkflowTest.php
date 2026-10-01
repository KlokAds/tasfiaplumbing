<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\ArticleWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ArticleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['is_active' => true])->assignRole($role);
    }

    private function article(array $overrides = []): array
    {
        return $overrides + ['name' => 'Aircon Chemical Wash Cost Singapore', 'desc' => '<p>Body</p>', 'faqs' => []];
    }

    public function test_writer_cannot_publish_and_submission_notifies_the_super_admin(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');

        $this->actingAs($writer)->post('/admin/blogs', $this->article(['intent' => 'publish']))->assertRedirect();

        $blog = BlogDetail::firstOrFail();
        $this->assertSame(BlogDetail::PENDING, $blog->status);
        $this->assertFalse($blog->is_active);
        $this->assertSame($writer->id, $blog->author_id);
        $this->get('/blogs/' . $blog->slug)->assertNotFound();

        Notification::assertSentTo($owner, ArticleWorkflow::class, function ($n) use ($owner) {
            if ($n->event !== 'submitted' || !is_int($n->seo['score'] ?? null)) {
                return false;
            }
            // The approval email shows the SEO score and each finding.
            $html = $n->toMail($owner)->render();

            return str_contains($html, 'SEO checklist') && str_contains($html, $n->seo['score'] . '</span>')
                && (empty($n->seo['issues']) || str_contains($html, e($n->seo['issues'][0]['message'])))
                // ...and the editor's four scores (SEO, AEO, GEO, E-E-A-T).
                && collect($n->seo['pillars'] ?? [])->pluck('label')->all() === ['SEO', 'AEO', 'GEO', 'E-E-A-T']
                && str_contains($html, '>E-E-A-T</p>');
        });
        Notification::assertNotSentTo($writer, ArticleWorkflow::class);
        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}/approve", ['mode' => 'now'])->assertForbidden();
    }

    public function test_approve_with_a_future_time_schedules_and_the_scheduler_publishes(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');
        $at = now()->addDay()->startOfHour();

        $this->actingAs($writer)->post('/admin/blogs', $this->article(['intent' => 'submit', 'scheduled_at' => $at->toIso8601String()]));
        $blog = BlogDetail::firstOrFail();
        $this->assertSame(BlogDetail::PENDING, $blog->status);
        $this->assertTrue($blog->scheduled_at->equalTo($at));

        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}/approve", ['mode' => 'schedule', 'scheduled_at' => $at->toIso8601String()])->assertRedirect();
        $this->assertSame(BlogDetail::SCHEDULED, $blog->fresh()->status);
        Notification::assertSentTo($writer, ArticleWorkflow::class, fn ($n) => $n->event === 'scheduled');

        $this->artisan('articles:publish-scheduled');
        $this->assertSame(BlogDetail::SCHEDULED, $blog->fresh()->status, 'not due yet');

        $this->travelTo($at->copy()->addMinute());
        $this->artisan('articles:publish-scheduled');
        $blog->refresh();
        $this->assertSame(BlogDetail::PUBLISHED, $blog->status);
        $this->assertTrue($blog->is_active);
        $this->assertTrue($blog->published_at->equalTo($at));
        Notification::assertSentTo($writer, ArticleWorkflow::class, fn ($n) => $n->event === 'published');
    }

    public function test_reject_returns_to_draft_with_note_and_emails_the_author(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');
        $this->actingAs($writer)->post('/admin/blogs', $this->article(['intent' => 'submit']));
        $blog = BlogDetail::firstOrFail();

        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}/reject", ['note' => 'Add the 2026 price range.'])->assertRedirect();

        $blog->refresh();
        $this->assertSame(BlogDetail::DRAFT, $blog->status);
        $this->assertSame('Add the 2026 price range.', $blog->review_note);
        Notification::assertSentTo($writer, ArticleWorkflow::class, fn ($n) => $n->event === 'rejected' && $n->note === 'Add the 2026 price range.'
            && in_array('mail', $n->via($writer), true));
    }

    public function test_editing_a_live_article_without_publish_rights_creates_a_revision(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $editor = $this->user('editor');
        $blog = BlogDetail::create(['name' => 'Live Guide', 'desc' => 'Original', 'status' => BlogDetail::PUBLISHED]);

        $this->actingAs($editor)->post("/admin/blogs/{$blog->id}", $this->article(['name' => 'Live Guide', 'desc' => 'Changed', 'intent' => 'submit']))->assertRedirect();

        $this->assertSame('Original', $blog->fresh()->desc);
        $revision = ArticleRevision::firstOrFail();
        Notification::assertSentTo($owner, ArticleWorkflow::class, fn ($n) => $n->event === 'revision_submitted' && isset($n->seo['score']));

        $this->actingAs($owner)->post("/admin/revisions/{$revision->id}/approve")->assertRedirect();
        $this->assertSame('Changed', $blog->fresh()->desc);
        Notification::assertSentTo($editor, ArticleWorkflow::class, fn ($n) => $n->event === 'revision_approved');
    }

    public function test_list_shows_a_waiting_edit_and_then_the_edit_date(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $editor = $this->user('editor');
        $this->travel(-3)->days();
        $blog = BlogDetail::create(['name' => 'Live Guide', 'desc' => 'Original', 'status' => BlogDetail::PUBLISHED]);
        $this->travelBack();

        $count = fn (string $changed) => count($this->actingAs($owner)->get('/admin/blogs?changed=' . $changed)->assertOk()->viewData('page')['props']['blogs']['data']);
        $row = fn () => $this->actingAs($owner)->get('/admin/blogs')->assertOk()->viewData('page')['props']['blogs']['data'][0];
        $this->assertFalse($row()['pending_changes']);
        $this->assertNull($row()['edited_at']);

        $this->actingAs($editor)->post("/admin/blogs/{$blog->id}", $this->article(['name' => 'Live Guide', 'desc' => 'Changed', 'intent' => 'submit']))->assertRedirect();
        $this->assertTrue($row()['pending_changes']);
        $this->assertNull($row()['edited_at']);
        $this->assertSame(1, $count('waiting'));
        $this->assertSame(0, $count('edited'));

        $this->actingAs($owner)->post('/admin/revisions/' . ArticleRevision::firstOrFail()->id . '/approve')->assertRedirect();
        $this->assertFalse($row()['pending_changes']);
        $this->assertNotNull($row()['edited_at']);
        $this->assertSame(0, $count('waiting'));
        $this->assertSame(1, $count('edited'));
        $this->assertSame(1, $count('new')); // added 3 days ago
    }

    public function test_a_rejected_edit_stops_showing_once_the_article_changed(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $editor = $this->user('editor');
        $blog = BlogDetail::create(['name' => 'Live Guide', 'desc' => 'Original', 'status' => BlogDetail::PUBLISHED]);
        $mine = fn () => collect($this->actingAs($editor)->get('/admin/blogs')->viewData('page')['props']['myRevisions'])->where('status', 'rejected');

        $this->actingAs($editor)->post("/admin/blogs/{$blog->id}", $this->article(['name' => 'Live Guide', 'desc' => 'Changed', 'intent' => 'submit']))->assertRedirect();
        $this->actingAs($owner)->post('/admin/revisions/' . ArticleRevision::firstOrFail()->id . '/reject', ['note' => 'Fix the score'])->assertRedirect();
        $this->assertCount(1, $mine());

        $this->travel(5)->minutes();
        $blog->fresh()->update(['desc' => 'Fixed by the admin']);
        $this->assertCount(0, $mine());
    }

    public function test_super_admin_can_schedule_directly_and_past_times_are_refused(): void
    {
        $owner = $this->user('super-admin');

        $this->actingAs($owner)->post('/admin/blogs', $this->article(['intent' => 'publish', 'scheduled_at' => now()->subHour()->toIso8601String()]))
            ->assertSessionHasErrors('scheduled_at');

        $this->actingAs($owner)->post('/admin/blogs', $this->article(['intent' => 'publish', 'scheduled_at' => now()->addHours(3)->toIso8601String()]));
        $this->assertSame(BlogDetail::SCHEDULED, BlogDetail::firstOrFail()->status);
    }

    public function test_web_requests_publish_due_articles_when_cron_is_missing(): void
    {
        BlogDetail::create(['name' => 'Due Guide', 'desc' => 'x', 'status' => BlogDetail::SCHEDULED, 'scheduled_at' => now()->subMinute()]);

        $this->get('/robots.txt');

        $this->assertSame(BlogDetail::PUBLISHED, BlogDetail::firstOrFail()->status);
    }

    public function test_notification_link_marks_it_read(): void
    {
        $writer = $this->user('writer');
        $writer->notify(new ArticleWorkflow('rejected', 'Guide', url('/admin/blogs?tab=mine'), 'Owner', 'Fix it'));
        $n = $writer->notifications()->first();

        $this->actingAs($writer)->get("/admin/notifications/{$n->id}")->assertRedirect(url('/admin/blogs?tab=mine'));
        $this->assertNotNull($n->fresh()->read_at);
    }
}
