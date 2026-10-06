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

    private function submit(User $writer): BlogDetail
    {
        $this->actingAs($writer)->post('/admin/blogs', ['name' => 'Floor Spring Repair Guide', 'desc' => '<p>We replaced the floor spring at a shop in Tampines last week.</p>', 'faqs' => [], 'intent' => 'submit']);

        return BlogDetail::firstOrFail();
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
        Notification::assertSentTo($approver, ArticleWorkflow::class, fn ($n) => $n->event === 'submitted' && $n->autoApprove !== null
            && str_contains($n->toMail($approver)->render(), 'approved automatically'));

        $this->travel(9)->minutes();
        $this->assertSame(0, AutoApprove::due(), 'not before 10 minutes');

        $this->travel(2)->minutes();
        $this->assertSame(1, AutoApprove::due());
        $blog->refresh();
        $this->assertSame(BlogDetail::PUBLISHED, $blog->status);
        $this->assertSame($owner->id, $blog->reviewed_by, 'approved on behalf of the hidden Super Admin');
        $this->assertNull($blog->auto_approve_at);
        Notification::assertSentTo($writer, ArticleWorkflow::class, fn ($n) => $n->event === 'approved');
    }

    public function test_below_100_or_an_approver_taking_over_means_no_automatic_approval(): void
    {
        Notification::fake();
        $this->user('super-admin', ['is_hidden' => true]);
        $approver = $this->user('super-admin');
        $writer = $this->user('writer');

        $this->score = 96;
        $blog = $this->submit($writer);
        $this->assertNull($blog->auto_approve_at);
        Notification::assertSentTo($approver, ArticleWorkflow::class, fn ($n) => $n->event === 'submitted' && $n->autoApprove === null);

        // 100 now, but the score fell before the time came: it waits for a person.
        $this->score = 100;
        AutoApprove::plan($blog, (AutoApprove::$reportUsing)());
        $this->score = 90;
        $this->travel(11)->minutes();
        $this->assertSame(0, AutoApprove::due());
        $this->assertSame(BlogDetail::PENDING, $blog->fresh()->status);
        $this->assertNull($blog->fresh()->auto_approve_at);

        // An approver saved it: they decide.
        $this->score = 100;
        AutoApprove::plan($blog->fresh(), (AutoApprove::$reportUsing)());
        $this->actingAs($approver)->post("/admin/blogs/{$blog->id}", ['name' => 'Floor Spring Repair Guide', 'desc' => '<p>Edited by the approver.</p>', 'faqs' => [], 'intent' => 'draft']);
        $this->assertSame(BlogDetail::PENDING, $blog->fresh()->status);
        $this->assertNull($blog->fresh()->auto_approve_at);
    }

    public function test_copied_text_or_template_text_stops_it(): void
    {
        Notification::fake();
        $this->user('super-admin', ['is_hidden' => true]);
        $writer = $this->user('writer');
        SourceCheck::saveKey('BSA-test-key-1234567890');

        $blog = $this->submit($writer);
        $this->travel(11)->minutes();
        $this->assertSame(0, AutoApprove::due(), 'waits for the source check');
        $this->assertNotNull($blog->fresh()->auto_approve_at);

        $blog->forceFill(['source_check' => ['hash' => SourceCheck::hash($blog->desc), 'total' => 1, 'found' => 1, 'matches' => []]])->saveQuietly();
        $this->assertSame(0, AutoApprove::due());
        $this->assertNull($blog->fresh()->auto_approve_at);
        $this->assertSame(BlogDetail::PENDING, $blog->fresh()->status);
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
}
