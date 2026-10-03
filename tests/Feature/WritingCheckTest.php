<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\ArticleWorkflow;
use App\Support\WritingCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Free checks before approval: AI-style phrases, text copied from another article on the site,
 * and the "updated after submission" email.
 */
class WritingCheckTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['is_active' => true])->assignRole($role);
    }

    private function jobs(): string
    {
        return '<h2>What we see on real jobs</h2><p><strong>A recent job:</strong> a shop in Bedok had a floor spring that leaked oil, so the glass door slammed shut. We replaced the spring box and set the closing speed in about two hours.</p>';
    }

    private function body(string $extra = ''): string
    {
        return '<p>Floor springs hold heavy glass doors and control how fast they close. When the oil seal fails the door swings too fast and can hurt someone walking through it.</p>'
            . '<p>Most shops in Singapore use a floor spring with a hold-open point at ninety degrees so staff can keep the door open during busy hours.</p>'
            . $extra . $this->jobs();
    }

    public function test_it_finds_ai_style_phrases_and_text_copied_from_another_article(): void
    {
        $this->assertSame(['delve into', "it's important to note"], WritingCheck::aiPhrases("<p>Let us delve into it. It’s important to note the oil.</p>"));
        $this->assertSame([], WritingCheck::aiPhrases('<p>We fix floor springs in Bedok shops.</p>'));

        $live = BlogDetail::create(['name' => 'Floor Spring Repair', 'slug' => 'floor-spring-repair', 'desc' => $this->body(), 'status' => BlogDetail::PUBLISHED]);
        $dup = WritingCheck::duplicate($this->body('<p>One new line about door closers in condos and offices around the island.</p>'), null);
        $this->assertSame($live->id, $dup['id']);
        $this->assertGreaterThanOrEqual(70, $dup['percent']);
        $this->assertNull(WritingCheck::duplicate($this->body(), $live->id), 'an article is not compared with itself');
    }

    public function test_the_check_is_stored_for_approvers(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');
        $text = $this->body('<p>In today’s fast-paced world it is worth noting how doors matter.</p>');

        $this->actingAs($writer)->post('/admin/blogs', ['name' => 'Floor Spring Price', 'desc' => $text, 'faqs' => [], 'intent' => 'submit']);
        $blog = BlogDetail::firstOrFail();
        $check = $blog->quality_check;
        $this->assertContains("in today's fast-paced world", $check['ai_phrases']);

        // The approval email lists it; the writer does not see the check in the list.
        Notification::assertSentTo($owner, ArticleWorkflow::class, fn ($n) => $n->event === 'submitted'
            && collect($n->seo['issues'])->contains(fn ($i) => str_contains($i['message'], 'AI-style phrases')));
        $this->actingAs($owner)->get('/admin/blogs?tab=review')->assertInertia(fn ($page) => $page->has('blogs.data.0.quality_check.ai_phrases'));
        $this->actingAs($writer)->get('/admin/blogs?tab=review')->assertInertia(fn ($page) => $page->missing('blogs.data.0.quality_check'));
    }

    public function test_approvers_hear_about_updates_after_submission_at_most_once_an_hour(): void
    {
        Notification::fake();
        Cache::flush();
        $owner = $this->user('super-admin');
        $writer = $this->user('writer');
        $this->actingAs($writer)->post('/admin/blogs', ['name' => 'Floor Spring Price', 'desc' => $this->body(), 'faqs' => [], 'intent' => 'submit']);
        $blog = BlogDetail::firstOrFail();

        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}", ['name' => 'Floor Spring Price', 'desc' => $this->body('<p>A first fix.</p>'), 'faqs' => [], 'intent' => 'submit']);
        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}", ['name' => 'Floor Spring Price', 'desc' => $this->body('<p>A second fix.</p>'), 'faqs' => [], 'intent' => 'submit']);

        Notification::assertSentToTimes($owner, ArticleWorkflow::class, 2); // submitted + one "updated"
        Notification::assertSentTo($owner, ArticleWorkflow::class, fn ($n) => $n->event === 'updated');

        // A change to a live article that is sent again.
        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}/approve", ['mode' => 'now']);
        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}", ['name' => 'Floor Spring Price', 'desc' => $this->body('<p>Change one.</p>'), 'faqs' => [], 'intent' => 'submit']);
        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}", ['name' => 'Floor Spring Price', 'desc' => $this->body('<p>Change two.</p>'), 'faqs' => [], 'intent' => 'submit']);
        Notification::assertSentTo($owner, ArticleWorkflow::class, fn ($n) => $n->event === 'revision_updated');
        $this->assertNotNull(ArticleRevision::firstOrFail()->quality_check['checked_at']);
    }
}
