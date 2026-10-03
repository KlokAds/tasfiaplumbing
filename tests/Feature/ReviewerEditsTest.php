<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\ArticleWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The approver can correct what the team sends: a submitted article (saving keeps it in review)
 * and a writer's changes to a live article (Edit before approving, then publish).
 */
class ReviewerEditsTest extends TestCase
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

    public function test_saving_a_submitted_article_keeps_it_in_review_and_the_approver_can_publish_it_edited(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');
        $this->actingAs($writer)->post('/admin/blogs', $this->article(['intent' => 'submit']));
        $blog = BlogDetail::firstOrFail();

        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}", $this->article(['intent' => 'draft', 'desc' => '<p>Corrected by the owner</p>']))->assertRedirect();
        $blog->refresh();
        $this->assertSame(BlogDetail::PENDING, $blog->status);
        $this->assertStringContainsString('Corrected by the owner', $blog->desc);

        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}", $this->article(['intent' => 'publish', 'desc' => '<p>Final text</p>']))->assertRedirect();
        $this->assertSame(BlogDetail::PUBLISHED, $blog->fresh()->status);
    }

    public function test_the_writer_sees_and_keeps_editing_the_changes_waiting_for_approval(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');
        $this->actingAs($writer)->post('/admin/blogs', $this->article(['intent' => 'submit']));
        $blog = BlogDetail::firstOrFail();
        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}/approve", ['mode' => 'now']);

        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}", $this->article(['intent' => 'submit', 'desc' => '<p>First change</p>']));

        // In the Review tab, marked as waiting, and the editor holds the change, not the live text.
        $this->actingAs($writer)->get('/admin/blogs?tab=review')
            ->assertInertia(fn ($page) => $page->where('blogs.data.0.id', $blog->id)->where('blogs.data.0.my_pending', true)
                ->where('blogs.data.0.desc', '<p>First change</p>')->where('counts.review', 1));

        // Sending again updates the same waiting change.
        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}", $this->article(['intent' => 'submit', 'desc' => '<p>Second change</p>']));
        $this->assertSame(1, ArticleRevision::where('status', 'pending')->count());
        $this->assertSame('<p>Second change</p>', ArticleRevision::firstOrFail()->payload['desc']);
        $this->assertSame('<p>Body</p>', $blog->fresh()->desc, 'the live page is unchanged');
    }

    public function test_the_approver_edits_a_writers_changes_to_a_live_article_before_they_go_live(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');
        $this->actingAs($writer)->post('/admin/blogs', $this->article(['intent' => 'submit']));
        $blog = BlogDetail::firstOrFail();
        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}/approve", ['mode' => 'now']);

        // The writer changes the live article: it waits for approval.
        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}", $this->article(['intent' => 'submit', 'desc' => '<p>Writer change with a typo</p>']));
        $revision = ArticleRevision::where('status', 'pending')->firstOrFail();
        $this->assertStringNotContainsString('typo', $blog->fresh()->desc);

        // The editor opens with the writer's change...
        $this->actingAs($owner)->get("/admin/blogs?tab=review&edit={$blog->id}&revision={$revision->id}")
            ->assertInertia(fn ($page) => $page->where('editBlog.revision_id', $revision->id)->where('editBlog.desc', '<p>Writer change with a typo</p>'));

        // ...the owner corrects it and publishes: live, and the change counts as approved.
        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}", $this->article(['intent' => 'publish', 'desc' => '<p>Writer change, corrected</p>', 'revision_id' => $revision->id]))->assertRedirect();
        $this->assertSame('<p>Writer change, corrected</p>', $blog->fresh()->desc);
        $this->assertSame('approved', $revision->fresh()->status);
        Notification::assertSentTo($writer, ArticleWorkflow::class, fn ($n) => $n->event === 'revision_approved');

        // A writer cannot use another person's pending change this way.
        $this->actingAs($writer)->get("/admin/blogs?edit={$blog->id}&revision={$revision->id}")
            ->assertInertia(fn ($page) => $page->missing('editBlog.revision_id'));
    }
}
