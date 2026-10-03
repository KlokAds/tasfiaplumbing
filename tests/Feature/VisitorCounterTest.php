<?php

namespace Tests\Feature;

use App\Models\SiteEvent;
use App\Models\User;
use App\Notifications\DailyVisitorReport;
use App\Support\VisitorStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The website's own visitor counter: real visitors, countries, contact clicks and the 6 pm email. */
class VisitorCounterTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private function signal(array $data, string $agent = self::BROWSER, string $ip = '203.0.113.5')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeader('User-Agent', $agent)
            ->postJson('/t', $data + ['z' => 'Asia/Dhaka', 'w' => 390]);
    }

    public function test_real_visits_and_clicks_are_counted_without_storing_the_ip(): void
    {
        $this->signal(['t' => 'visit', 'p' => '/service/door-repair', 'r' => 'https://www.google.com/'])->assertNoContent();
        $this->signal(['t' => 'visit', 'p' => '/contact']);
        $this->signal(['t' => 'whatsapp', 'p' => '/contact']);
        $this->signal(['t' => 'visit', 'p' => '/'], self::BROWSER, '198.51.100.9');

        // Not counted: bots, unknown types, admin pages, signed-in admins.
        $this->signal(['t' => 'visit', 'p' => '/'], 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->signal(['t' => 'hack', 'p' => '/']);
        $this->signal(['t' => 'visit', 'p' => '/admin/blogs']);
        $this->actingAs(User::factory()->create(['is_active' => true])->assignRole('super-admin'));
        $this->signal(['t' => 'visit', 'p' => '/']);

        $this->assertSame(4, SiteEvent::count());
        $event = SiteEvent::where('type', 'visit')->where('path', '/service/door-repair')->firstOrFail();
        $this->assertSame('BD', $event->country);
        $this->assertSame('google.com', $event->source);
        $this->assertSame('mobile', $event->device);
        $this->assertSame(16, strlen($event->visitor));
        $this->assertStringNotContainsString('203.0.113.5', json_encode(SiteEvent::all()->toArray()));

        $today = now(config('admin.timezone'))->startOfDay();
        $s = VisitorStats::summary($today->copy(), $today->copy());
        $this->assertSame(2, $s['totals']['visitors'], 'the same person twice is one visitor');
        $this->assertSame(3, $s['totals']['visit']);
        $this->assertSame(1, $s['totals']['whatsapp']);
        $this->assertSame('Bangladesh', $s['countries'][0]['name']);
        $this->assertSame(1, $s['countries'][0]['contacts']);
        $this->assertSame('/contact', $s['contactPages'][0]['path']);
    }

    public function test_only_people_with_access_see_the_counter_and_get_the_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['is_active' => true])->assignRole('super-admin');
        $writer = User::factory()->create(['is_active' => true])->assignRole('writer');
        $this->signal(['t' => 'visit', 'p' => '/']);
        $this->signal(['t' => 'call', 'p' => '/']);

        $this->actingAs($writer)->get('/admin/visitors')->assertForbidden();
        $this->actingAs($owner)->get('/admin/visitors?range=today')
            ->assertInertia(fn ($page) => $page->component('Admin/Insights/Visitors')->where('stats.totals.visitors', 1)->where('stats.totals.call', 1));

        $this->assertSame(1, VisitorStats::sendDaily());
        Notification::assertSentTo($owner, DailyVisitorReport::class, fn ($n) => $n->today['totals']['call'] === 1
            && str_contains($n->toMail($owner)->render(), 'Call clicks'));
        Notification::assertNotSentTo($writer, DailyVisitorReport::class);

        // Switched off: no email.
        $this->actingAs($owner)->post('/admin/visitors/daily-email', ['on' => false]);
        $this->assertSame(0, VisitorStats::sendDaily());
    }
}
