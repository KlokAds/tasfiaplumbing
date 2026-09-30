<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Google\GoogleSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_saves_how_often_each_task_runs(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($owner)->post('/admin/insights/google/sync', [
            'reports' => 3, 'index' => 0, 'reviews' => 168, 'index_batch' => 50,
        ])->assertSessionHas('success');

        $this->assertSame(3, GoogleSync::every('reports'));
        $this->assertSame(0, GoogleSync::every('index'));
        $this->assertSame(168, GoogleSync::every('reviews'));
        $this->assertSame(50, GoogleSync::indexBatch());
        $this->assertSame(4, GoogleSync::reportHours());
    }

    public function test_values_outside_the_choices_are_refused(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($owner)->post('/admin/insights/google/sync', [
            'reports' => 2, 'index' => 24, 'reviews' => 24, 'index_batch' => 5000,
        ])->assertSessionHasErrors(['reports', 'index_batch']);
    }

    public function test_nothing_runs_while_google_is_not_connected(): void
    {
        $this->assertFalse(GoogleSync::available('index'));
        $this->assertNull(GoogleSync::next('index'));
        $this->assertSame([], GoogleSync::runDue());
        $this->artisan('google:sync')->expectsOutput('Google is not connected.')->assertSuccessful();
    }

    public function test_a_task_is_due_once_its_interval_has_passed(): void
    {
        SiteSetting::putMany(['google.sync.reviews_every' => '24', 'google.sync.reviews_last' => now()->subHours(5)->toIso8601String()]);
        $this->assertEquals(now()->subHours(5)->addHours(24)->timestamp, GoogleSync::last('reviews')->addHours(24)->timestamp);

        $status = collect(GoogleSync::status()['tasks'])->keyBy('key');
        $this->assertSame(24, $status['reviews']['every']);
        $this->assertFalse($status['reviews']['available']);
    }

    public function test_reports_page_opens_without_waiting_for_google(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($owner)->get('/admin/insights')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Insights/Reports')->where('hasGsc', false)->where('hasGa4', false));
    }
}
