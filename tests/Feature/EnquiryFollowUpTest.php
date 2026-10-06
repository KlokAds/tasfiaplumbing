<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Notifications\EnquiryReminder;
use App\Notifications\NewEnquiry;
use App\Support\Enquiries;
use App\Support\SiteMonitor;
use App\Support\SpamCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Spam folder, follow-up status, ready replies, the no-reply reminder and the website monitor. */
class EnquiryFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (['monitor.state', 'monitor.recipients', 'monitor.log', 'monitor.last_run'] as $k) {
            Cache::store('file')->forget($k);
        }
        parent::tearDown();
    }

    public function test_sales_pitches_and_bots_are_spam_but_customers_are_not(): void
    {
        [$spam] = SpamCheck::check(['name' => 'Moran', 'email' => 'm@x.io', 'phone' => '+1 555 123 4567', 'subject' => 'We Designed a Free Homepage Mockup for You',
            'message' => 'I came across your website and made a mockup: https://example.com/mock']);
        $this->assertTrue($spam);
        [$bot] = SpamCheck::check(['name' => 'eutwLIEtICVXfPilgTJplZpM', 'email' => 'a@b.c', 'phone' => '', 'subject' => 'aFQJlRheapROtgBYqVpkIn', 'message' => 'xx']);
        $this->assertTrue($bot);

        [$customer] = SpamCheck::check(['name' => 'Mei Ling', 'email' => 'mei@gmail.com', 'phone' => '9123 4567', 'subject' => 'Sliding door repair',
            'message' => 'My sliding door in Bedok is stuck and makes a noise. Can you come this week? Also want to redesign the kitchen cabinet later.']);
        $this->assertFalse($customer);
        [$noPhone] = SpamCheck::check(['name' => 'Raj', 'email' => 'raj@yahoo.com', 'phone' => '', 'subject' => 'Door closer', 'message' => 'How much to replace a door closer?']);
        $this->assertFalse($noPhone, 'a missing phone alone is not spam');
    }

    public function test_spam_from_the_website_goes_to_the_spam_folder_without_an_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['is_active' => true])->assignRole('super-admin');

        $this->withHeader('Referer', config('app.url') . '/service/door-repair')->post('/messages', [
            'name' => 'Mei Ling', 'email' => 'mei@gmail.com', 'phone' => '91234567', 'subject' => 'Door repair', 'message' => 'My door is stuck.',
        ]);
        $this->post('/messages', ['name' => 'Moran', 'email' => 'm@x.io', 'phone' => '+15551234567', 'subject' => 'Free website mockup',
            'message' => 'I came across your website, more leads with our SEO: https://a.io https://b.io']);

        $real = Message::where('name', 'Mei Ling')->firstOrFail();
        $this->assertFalse($real->is_spam);
        $this->assertSame('/service/door-repair', $real->page);
        $this->assertTrue(Message::where('name', 'Moran')->value('is_spam'));
        Notification::assertSentToTimes($owner, NewEnquiry::class, 1);

        // Inbox and Spam folder.
        $this->actingAs($owner)->get('/admin/messages')->assertInertia(fn ($p) => $p->has('messages.data', 1)->where('spamCount', 1));
        $this->actingAs($owner)->get('/admin/messages?box=spam')->assertInertia(fn ($p) => $p->where('messages.data.0.name', 'Moran'));

        // Not spam brings it back; status changes.
        $spam = Message::where('name', 'Moran')->firstOrFail();
        $this->actingAs($owner)->post("/admin/messages/{$spam->id}/spam", ['spam' => false]);
        $this->assertFalse($spam->fresh()->is_spam);
        $this->actingAs($owner)->post("/admin/messages/{$real->id}/status", ['status' => 'won']);
        $this->assertSame('won', $real->fresh()->status);
        $this->actingAs($owner)->get('/admin/messages?status=won')->assertInertia(fn ($p) => $p->has('messages.data', 1)->where('statusCounts.won', 1));

        // Checking old enquiries moves the spam again (nothing is deleted).
        $this->actingAs($owner)->post('/admin/messages-scan-spam');
        $this->assertTrue($spam->fresh()->is_spam);
        $this->assertSame(2, Message::count());
    }

    public function test_ready_replies_are_saved(): void
    {
        $owner = User::factory()->create(['is_active' => true])->assignRole('super-admin');
        $this->assertCount(3, Enquiries::templates());
        $this->actingAs($owner)->post('/admin/messages-templates', ['templates' => [['name' => 'Price', 'text' => 'Hi {name}, the price is …']]])->assertSessionHas('success');
        $this->assertSame('Price', Enquiries::templates()[0]['name']);
        $this->assertSame('6591234567', Enquiries::whatsappNumber('9123 4567'));
    }

    public function test_enquiries_without_a_reply_are_reminded_once_in_the_daytime(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['is_active' => true, 'is_hidden' => true])->assignRole('super-admin');
        $admin = User::factory()->create(['is_active' => true])->assignRole('super-admin');
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00', config('admin.timezone')));
        $old = Message::create(['name' => 'Mei', 'email' => 'm@g.com', 'phone' => '91234567', 'message' => 'Door', 'status' => 'new']);
        $old->forceFill(['created_at' => now()->subHours(3)])->save();
        $fresh = Message::create(['name' => 'Tan', 'email' => 't@g.com', 'phone' => '81234567', 'message' => 'Lock', 'status' => 'new']);
        $done = Message::create(['name' => 'Lim', 'email' => 'l@g.com', 'phone' => '81234568', 'message' => 'Hinge', 'status' => 'contacted']);
        $done->forceFill(['created_at' => now()->subHours(5)])->save();

        $this->assertSame(1, Enquiries::remind());
        Notification::assertSentTo($owner, EnquiryReminder::class, fn ($n) => count($n->messages) === 1 && $n->messages[0]->id === $old->id);
        Notification::assertSentTo($admin, EnquiryReminder::class);
        $this->assertSame(0, Enquiries::remind(), 'each enquiry is reminded once');

        Carbon::setTestNow(Carbon::parse('2026-10-05 23:00', config('admin.timezone')));
        $fresh->forceFill(['created_at' => now()->subHours(3)])->save();
        $this->assertSame(0, Enquiries::remind(), 'not at night');
    }

    public function test_the_monitor_emails_after_two_failed_checks_and_when_it_works_again(): void
    {
        Notification::fake();
        config(['app.url' => 'https://tasfiadoorrepairsg.com']);
        User::factory()->create(['is_active' => true, 'is_hidden' => true, 'email' => 'owner@example.com'])->assignRole('super-admin');
        $this->assertSame('https://tasfiaplumbing.sg', SiteMonitor::watched());

        $up = false;
        Http::fake(function () use (&$up) {
            return $up ? Http::response('ok', 200) : Http::response('', 500);
        });
        SiteMonitor::run();
        Notification::assertNothingSent();
        SiteMonitor::run();
        Notification::assertSentOnDemand(\App\Notifications\SiteMonitorAlert::class, fn ($n, $channels, $notifiable) => str_starts_with($n->subject, 'Down: tasfiaplumbing.sg')
            && $notifiable->routes['mail'] === ['owner@example.com']);

        $up = true;
        $checks = SiteMonitor::run();
        $this->assertSame('', $checks['tasfiaplumbing.sg']);
        Notification::assertSentOnDemand(\App\Notifications\SiteMonitorAlert::class, fn ($n) => str_starts_with($n->subject, 'Working again'));
        $this->assertCount(1, SiteMonitor::status()['incidents']);
    }
}
