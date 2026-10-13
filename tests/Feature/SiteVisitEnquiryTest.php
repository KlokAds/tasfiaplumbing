<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Quote form: a free site visit (day, time, postal code, property) and up to three private photos. */
class SiteVisitEnquiryTest extends TestCase
{
    use RefreshDatabase;

    private function send(array $extra = [])
    {
        return $this->post('/messages', $extra + [
            'name' => 'Lim Boon Keng', 'email' => 'lim@example.com', 'phone' => '91234567',
            'subject' => 'Floor spring', 'message' => 'The glass door does not close by itself any more.',
        ]);
    }

    public function test_a_visit_request_with_photos_is_kept_and_shown_to_staff_only(): void
    {
        Storage::fake('local');
        Notification::fake();
        $owner = User::factory()->create(['is_active' => true])->assignRole('super-admin');
        $day = now()->addDays(3)->toDateString();

        $this->send([
            'help' => 'visit', 'visit_date' => $day, 'visit_time' => 'evening', 'postal_code' => '520123', 'property' => 'HDB',
            'photos' => [UploadedFile::fake()->image('door.jpg', 800, 600), UploadedFile::fake()->image('hinge.png', 400, 300)],
        ])->assertSessionHasNoErrors();

        $m = Message::firstOrFail();
        $this->assertTrue($m->wants_visit);
        $this->assertSame($day, $m->visit_date->toDateString());
        $this->assertSame('evening', $m->visit_time);
        $this->assertSame('520123', $m->postal_code);
        $this->assertSame('HDB', $m->property);
        $this->assertCount(2, $m->photos);
        Storage::disk('local')->assertExists($m->photos[0]);
        $this->assertStringContainsString('evening (5pm – 10pm)', $m->visit_label);

        // The team's email shows the visit and the photos.
        Notification::assertSentTo($owner, NewEnquiry::class);

        // Photos: staff only.
        $this->get($m->photo_urls[0])->assertRedirect();
        $this->actingAs($owner)->get($m->photo_urls[0])->assertOk();
        $this->actingAs($owner)->get("/admin/messages/{$m->id}/photos/7")->assertNotFound();
        $this->actingAs($owner)->get('/admin/messages')->assertInertia(fn ($page) => $page
            ->where('messages.data.0.visit_label', $m->visit_label)
            ->has('messages.data.0.photo_urls', 2));
    }

    public function test_a_visit_needs_a_day_and_a_price_from_photos_does_not(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->send(['help' => 'visit', 'visit_date' => ''])->assertSessionHasErrors('visit_date');
        $this->send(['help' => 'visit', 'visit_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('visit_date');
        $this->send(['help' => 'visit', 'visit_date' => now()->addDay()->toDateString(), 'postal_code' => '12345'])->assertSessionHasErrors('postal_code');
        $this->send(['photos' => array_fill(0, 4, UploadedFile::fake()->image('a.jpg'))])->assertSessionHasErrors('photos');

        $this->send(['help' => 'photos', 'photos' => [UploadedFile::fake()->image('tap.jpg')]])->assertSessionHasNoErrors();
        $m = Message::firstOrFail();
        $this->assertFalse($m->wants_visit);
        $this->assertNull($m->visit_label);
        $this->assertCount(1, $m->photos);
    }
}
