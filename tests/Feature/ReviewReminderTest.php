<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\PendingReviewReminder;
use App\Support\ReviewReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Waiting for approval longer than 12 hours: one email to the hidden maintenance account, at most once per 12 hours. */
class ReviewReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_waiting_articles_email_the_hidden_admin_only_once_per_period(): void
    {
        $owner = User::factory()->create(['is_active' => true])->assignRole('super-admin');
        $hidden = User::factory()->create(['is_active' => true, 'is_hidden' => true, 'name' => 'System maintenance'])->assignRole('super-admin');
        $writer = User::factory()->create(['is_active' => true])->assignRole('writer');

        $this->actingAs($writer)->post('/admin/blogs', ['name' => 'Floor Spring Repair Singapore', 'desc' => '<p>Body</p>', 'faqs' => [], 'intent' => 'submit']);
        $this->assertSame(BlogDetail::PENDING, BlogDetail::firstOrFail()->status);

        Notification::fake();
        $this->assertSame(0, ReviewReminder::run(), 'not 12 hours yet');

        $this->travel(13)->hours();
        $this->assertSame(1, ReviewReminder::run());
        Notification::assertSentTo($hidden, PendingReviewReminder::class, fn ($n) => $n->items[0]['title'] === 'Floor Spring Repair Singapore');
        Notification::assertNotSentTo($owner, PendingReviewReminder::class);
        $html = (new PendingReviewReminder(Notification::sent($hidden, PendingReviewReminder::class)->first()->items, 12))->toMail($hidden)->render();
        $this->assertStringContainsString('more than 12 hours', $html);

        $this->travel(2)->hours();
        $this->assertSame(0, ReviewReminder::run(), 'already reminded in the last 12 hours');

        $this->travel(11)->hours();
        $this->assertSame(1, ReviewReminder::run(), 'reminded again after 12 hours');
    }
}
