<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\ArticleWorkflow;
use App\Support\AutoApprove;
use App\Support\SourceCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Content quality 100/100: approved automatically 10 minutes after the approval email, for the Super Admin. */
class AutoApproveTest extends TestCase
{
    use RefreshDatabase;

    private int $score = 100;

    protected function setUp(): void
    {
        parent::setUp();
        SourceCheck::$pause = 0;
        AutoApprove::$reportUsing = fn () => ['score' => $this->score, 'errors' => 0, 'warnings' => 0, 'issues' => [], 'pillars' => [], 'words' => 900];
    }

    protected function tearDown(): void
    {
        AutoApprove::$reportUsing = null;
        parent::tearDown();
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(['is_active' => true] + $attrs)->assignRole($role);
    }

    private function submit(User $writer, string $name = 'Floor Spring Repair Guide'): BlogDetail
    {
        $this->actingAs($writer)->post('/admin/blogs', ['name' => $name, 'desc' => '<p>We replaced the floor spring at a shop in Tampines last week.</p>', 'faqs' => [], 'intent' => 'submit']);

        return BlogDetail::where('name', $name)->firstOrFail();
    }

    public function test_a_100_article_is_published_10_minutes_after_the_email(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin', ['is_hidden' => true]);
        $approver = $this->user('super-admin');
        $writer = $this->user('writer');

        $blog = $this->submit($writer);
        $this->assertSame(BlogDetail::PENDING, $blog->status);
        $this->assertNotNull($blog->auto_approve_at);
        Notification::assertSentTo($owner, ArticleWorkflow::class, fn ($n) => $n->event === 'submitted' && $n->autoApprove !== null && $n->withMail
            && $n->via($owner) === ['database', 'mail'] && str_contains($n->toMail($owner)->render(), 'approved automatically'));
        // Other Super Admins: the bell only, no email.
        Notification::assertSentTo($approver, ArticleWorkflow::class, fn ($n) => $n->event === 'submitted' && $n->via($approver) === ['database']);

        $this->travel(9)->minutes();
        $this->assertSame(0, AutoApprove::due(), 'not before 10 minutes');

        $this->travel(2)->minutes();
        $this->assertSame(1, AutoApprove::due());
        $blog->refresh();
        $this->assertSame(BlogDetail::PUBLISHED, $blog->status);
        $this->assertSame($owner->id, $blog->reviewed_by, 'approved on behalf of the hidden Super Admin');
        $this->assertNull($blog->auto_approve_at);
        Notification::assertSentTo($writer, ArticleWorkflow::class, fn ($n) => $n->event === 'approved');
        // The hidden admin hears about it too.
        Notification::assertSentTo($owner, \App\Notifications\ArticleDecision::class, fn ($n) => $n->decision === 'approved' && $n->auto
            && str_contains($n->toMail($owner)->render(), 'Content quality is 100/100'));
    }

    public function test_an_approver_deciding_by_hand_is_emailed_to_the_hidden_admin(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin', ['is_hidden' => true]);
        $approver = $this->user('super-admin');
        $writer = $this->user('writer');
        $blog = $this->submit($writer);
        $this->actingAs($approver)->post("/admin/blogs/{$blog->id}/reject", ['note' => 'Add a photo from the job.']);

        Notification::assertSentTo($owner, \App\Notifications\ArticleDecision::class, fn ($n) => $n->decision === 'sent_back' && !$n->auto
            && str_contains($n->toMail($owner)->render(), 'Add a photo from the job.') && str_contains($n->toMail($owner)->render(), 'by ' . e($approver->name)));

        $other = $this->submit($writer, 'Glass Door Hinge Guide');
        $this->actingAs($approver)->post("/admin/blogs/{$other->id}/approve", ['mode' => 'now']);
        Notification::assertSentTo($owner, \App\Notifications\ArticleDecision::class, fn ($n) => $n->decision === 'approved' && $n->title === 'Glass Door Hinge Guide');
    }

    public function test_below_100_it_is_sent_back_after_10_minutes_with_what_to_fix(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin', ['is_hidden' => true]);
        $approver = $this->user('super-admin');
        $writer = $this->user('writer');
        AutoApprove::$reportUsing = fn () => ['score' => $this->score, 'errors' => 0, 'warnings' => 1, 'pillars' => [], 'words' => 900,
            'issues' => $this->score === 100 ? [] : [['level' => 'warning', 'message' => 'AEO: Short first paragraph. Answer the question in the first 2 sentences.']]];

        $this->score = 88;
        $blog = $this->submit($writer);
        $this->assertNotNull($blog->auto_approve_at);
        Notification::assertSentTo($approver, ArticleWorkflow::class, fn ($n) => $n->event === 'submitted' && $n->autoApprove === null && $n->autoSendBack !== null
            && str_contains($n->toMail($approver)->render(), 'sent back to the writer automatically'));

        $this->travel(9)->minutes();
        $this->assertSame(0, AutoApprove::due(), 'not before 10 minutes');
        $this->travel(2)->minutes();
        $this->assertSame(1, AutoApprove::due());

        $blog->refresh();
        $this->assertSame(BlogDetail::DRAFT, $blog->status);
        $this->assertSame($owner->id, $blog->reviewed_by);
        $this->assertStringContainsString('Content quality is 88/100', $blog->review_note);
        $this->assertStringContainsString('1. AEO: Short first paragraph.', $blog->review_note);
        Notification::assertSentTo($writer, ArticleWorkflow::class, fn ($n) => $n->event === 'rejected' && str_contains((string) $n->note, 'Short first paragraph'));
        Notification::assertSentTo($owner, \App\Notifications\ArticleDecision::class, fn ($n) => $n->decision === 'sent_back' && $n->auto
            && str_contains($n->toMail($owner)->render(), 'Short first paragraph'));
        Notification::assertNotSentTo($approver, \App\Notifications\ArticleDecision::class);
    }

    public function test_the_check_runs_again_at_the_time_and_an_approver_saving_it_stops_it(): void
    {
        Notification::fake();
        $this->user('super-admin', ['is_hidden' => true]);
        $approver = $this->user('super-admin');
        $writer = $this->user('writer');

        // 100 when submitted, 90 at the time (for example a duplicate appeared): sent back, not published.
        $blog = $this->submit($writer);
        $this->score = 90;
        $this->travel(11)->minutes();
        $this->assertSame(1, AutoApprove::due());
        $this->assertSame(BlogDetail::DRAFT, $blog->fresh()->status);

        // An approver saved another one: they decide.
        $this->score = 100;
        $other = $this->submit($writer, 'Glass Door Hinge Guide');
        $this->assertNotNull($other->auto_approve_at);
        $this->actingAs($approver)->post("/admin/blogs/{$other->id}", ['name' => 'Glass Door Hinge Guide', 'desc' => '<p>Edited by the approver.</p>', 'faqs' => [], 'intent' => 'draft']);
        $this->assertSame(BlogDetail::PENDING, $other->fresh()->status);
        $this->assertNull($other->fresh()->auto_approve_at);
    }

    public function test_it_waits_for_the_source_check_and_copied_text_is_sent_back(): void
    {
        Notification::fake();
        $this->user('super-admin', ['is_hidden' => true]);
        $writer = $this->user('writer');
        SourceCheck::saveKey('BSA-test-key-1234567890');

        $blog = $this->submit($writer);
        $this->travel(11)->minutes();
        $this->assertSame(0, AutoApprove::due(), 'waits for the source check');
        $this->assertNotNull($blog->fresh()->auto_approve_at);

        $blog->forceFill(['source_check' => ['hash' => SourceCheck::hash($blog->desc), 'total' => 1, 'found' => 1,
            'matches' => [['sentence' => 'We replaced the floor spring at a shop in Tampines last week.', 'urls' => ['https://other.example/a']]]]])->saveQuietly();
        $this->assertSame(1, AutoApprove::due());
        $this->assertSame(BlogDetail::DRAFT, $blog->fresh()->status);
        $this->assertStringContainsString('already on other websites', $blog->fresh()->review_note);
    }

    public function test_a_100_change_to_a_live_article_goes_live_after_10_minutes(): void
    {
        Notification::fake();
        Http::fake();
        $owner = $this->user('super-admin', ['is_hidden' => true]);
        $writer = $this->user('editor');
        $blog = BlogDetail::create(['name' => 'Live Guide', 'desc' => '<p>Original</p>', 'status' => BlogDetail::PUBLISHED, 'author_id' => $writer->id]);

        $this->actingAs($writer)->post("/admin/blogs/{$blog->id}", ['name' => 'Live Guide', 'desc' => '<p>New text from a real job in Bedok.</p>', 'faqs' => [], 'intent' => 'submit']);
        $revision = ArticleRevision::firstOrFail();
        $this->assertNotNull($revision->auto_approve_at);
        $this->assertSame('<p>Original</p>', $blog->fresh()->desc);

        $this->travel(11)->minutes();
        $this->assertSame(1, AutoApprove::due());
        $this->assertSame('approved', $revision->fresh()->status);
        $this->assertSame($owner->id, $revision->fresh()->reviewed_by);
        $this->assertSame('<p>New text from a real job in Bedok.</p>', $blog->fresh()->desc);
        Notification::assertSentTo($writer, ArticleWorkflow::class, fn ($n) => $n->event === 'revision_approved');
    }

    public function test_the_text_of_another_article_pasted_in_is_sent_back(): void
    {
        Notification::fake();
        $this->user('super-admin', ['is_hidden' => true]);
        $writer = $this->user('editor');
        BlogDetail::create(['name' => 'Sliding Door Track Repair', 'desc' => '<p>Track</p>', 'status' => BlogDetail::PUBLISHED, 'focus_keyword' => 'sliding door track repair']);
        $spotlight = BlogDetail::create(['name' => 'Spotlight Replacement', 'desc' => '<p>Spotlight</p>', 'status' => BlogDetail::PUBLISHED, 'author_id' => $writer->id]);

        // The sliding door article pasted into the spotlight article: same focus keyword.
        $this->actingAs($writer)->post("/admin/blogs/{$spotlight->id}", ['name' => 'Sliding Door Track Guide', 'focus_keyword' => 'Sliding door track repair', 'desc' => '<p>Track text.</p>', 'faqs' => [], 'intent' => 'submit']);
        $revision = ArticleRevision::firstOrFail();
        $this->assertStringContainsString('is the same as another article, “Sliding Door Track Repair”', (string) AutoApprove::clash($revision));

        $this->travel(11)->minutes();
        $this->assertSame(1, AutoApprove::due());
        $this->assertSame('rejected', $revision->fresh()->status);
        $this->assertStringContainsString('focus keyword', $revision->fresh()->note);
        $this->assertSame('<p>Spotlight</p>', $spotlight->fresh()->desc, 'the live article stays');
    }

    public function test_a_change_with_new_text_counts_as_updated_now(): void
    {
        $writer = $this->user('editor');
        $blog = BlogDetail::create(['name' => 'Old Guide', 'desc' => '<p>Old</p>', 'status' => BlogDetail::PUBLISHED]);
        $blog->forceFill(['content_updated_at' => now()->subYears(2)])->saveQuietly();
        $change = ArticleRevision::create(['article_id' => $blog->id, 'user_id' => $writer->id, 'status' => 'pending', 'payload' => ['name' => 'Old Guide', 'desc' => '<p>New</p>']]);
        $same = ArticleRevision::create(['article_id' => $blog->id, 'user_id' => $writer->id, 'status' => 'pending', 'payload' => ['name' => 'Old Guide 2', 'desc' => '<p>Old</p>']]);

        $this->assertTrue(\App\Support\ArticleNotifier::proposed($change->load('article'))->content_updated_at->gt(now()->subMinute()));
        $this->assertTrue(\App\Support\ArticleNotifier::proposed($same->load('article'))->content_updated_at->lt(now()->subYear()), 'same text: not fresher');
    }

    public function test_a_change_sent_back_opens_with_the_writers_own_text_to_fix(): void
    {
        $writer = $this->user('editor');
        $other = $this->user('editor');
        $blog = BlogDetail::create(['name' => 'Live Guide', 'desc' => '<p>Live text</p>', 'status' => BlogDetail::PUBLISHED]);
        $revision = ArticleRevision::create(['article_id' => $blog->id, 'user_id' => $writer->id, 'status' => 'rejected', 'note' => 'Add the price.',
            'payload' => ['name' => 'Live Guide', 'desc' => '<p>My changed text</p>', 'faqs' => [['question' => 'Q?', 'answer' => 'A.']]]]);

        $this->actingAs($writer)->get("/admin/blogs?edit={$blog->id}&resume={$revision->id}")->assertInertia(fn ($page) => $page
            ->where('editBlog.desc', '<p>My changed text</p>')
            ->where('editBlog.resumed_note', 'Add the price.')
            ->where('editBlog.faqs.0.question', 'Q?'));

        // Someone else's change is not loaded: they see the live article.
        $this->actingAs($other)->get("/admin/blogs?edit={$blog->id}&resume={$revision->id}")->assertInertia(fn ($page) => $page
            ->where('editBlog.desc', '<p>Live text</p>')
            ->missing('editBlog.resumed_note'));
    }

    public function test_the_sent_back_tab_shows_each_writer_their_own_and_approvers_everyone(): void
    {
        $owner = $this->user('super-admin');
        $writer = $this->user('editor');
        $other = $this->user('editor');
        $live = BlogDetail::create(['name' => 'Live Guide', 'desc' => '<p>Live</p>', 'status' => BlogDetail::PUBLISHED]);
        ArticleRevision::create(['article_id' => $live->id, 'user_id' => $writer->id, 'status' => 'rejected', 'note' => 'Add the price.', 'reviewed_at' => now(),
            'payload' => ['name' => 'Live Guide', 'desc' => '<p>Mine</p>']]);
        BlogDetail::create(['name' => 'Draft Back', 'desc' => '<p>x</p>', 'status' => BlogDetail::DRAFT, 'author_id' => $other->id, 'review_note' => 'Too short.']);

        $this->actingAs($writer)->get('/admin/blogs?tab=sent_back')->assertInertia(fn ($page) => $page
            ->where('counts.sent_back', 1)
            ->has('blogs.data', 1)
            ->where('blogs.data.0.name', 'Live Guide')
            ->where('blogs.data.0.sent_back.kind', 'Changes')
            ->where('blogs.data.0.sent_back.mine', true));

        $this->actingAs($owner)->get('/admin/blogs?tab=sent_back')->assertInertia(fn ($page) => $page
            ->where('counts.sent_back', 2)
            ->has('blogs.data', 2));

        // A new change from the writer clears it.
        ArticleRevision::create(['article_id' => $live->id, 'user_id' => $writer->id, 'status' => 'pending', 'payload' => ['name' => 'Live Guide', 'desc' => '<p>Fixed</p>']]);
        $this->actingAs($writer)->get('/admin/blogs?tab=sent_back')->assertInertia(fn ($page) => $page->where('counts.sent_back', 0));
    }
}
