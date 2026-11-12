<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Support\DailyRelease;
use App\Support\RewriteImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The imported AI rewrites go live a few a day, one per slot, and doubtful ones wait for a person. */
class DailyReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function rewrite(int $i, string $text = 'We fixed the hinge at a flat in Tampines.'): ArticleRevision
    {
        $blog = BlogDetail::create(['name' => "Old title {$i}", 'slug' => "old-title-{$i}", 'desc' => '<p>Old text.</p>', 'status' => BlogDetail::PUBLISHED]);

        return ArticleRevision::create([
            'article_id' => $blog->id,
            'payload' => ['name' => "New title {$i}", 'slug' => "old-title-{$i}", 'desc' => "<p>{$text}</p>", 'focus_keyword' => "keyword {$i}", 'faqs' => []],
            'status' => 'pending',
            'note' => RewriteImport::MARK . ': checklist',
        ]);
    }

    /** Singapore time. */
    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse("2026-11-13 {$time}", 'Asia/Singapore'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_one_rewrite_goes_live_per_slot_and_four_a_day_at_most(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['is_active' => true, 'is_hidden' => true])->assignRole('super-admin');
        foreach (range(1, 6) as $i) {
            $this->rewrite($i);
        }

        $this->at('08:30');
        $this->assertSame([], DailyRelease::run()['released'], 'nothing before 9am');

        $this->at('09:02');
        $this->assertSame(['New title 1'], DailyRelease::run()['released']);
        $this->assertSame([], DailyRelease::run()['released'], 'the same slot does not release twice');

        // The 12pm and 3pm runs were missed: the 4pm run catches up.
        $this->at('16:02');
        $this->assertSame(['New title 2', 'New title 3'], DailyRelease::run()['released']);

        $this->at('18:02');
        $r = DailyRelease::run();
        $this->assertSame(['New title 4'], $r['released']);
        $this->assertSame(2, $r['waiting']);

        $this->at('23:02');
        $this->assertSame([], DailyRelease::run()['released'], 'four a day at most');

        $blog = BlogDetail::where('slug', 'old-title-1')->first();
        $this->assertSame('New title 1', $blog->name);
        $this->assertSame($owner->id, $blog->reviewed_by);
        $this->assertSame(1, \App\Models\ArticleVersion::where('article_id', $blog->id)->count(), 'the old version is kept in History');
        $this->assertSame(DailyRelease::NOTE, ArticleRevision::where('article_id', $blog->id)->first()->note);
        Notification::assertNothingSent();

        // Next day it starts again.
        Carbon::setTestNow(Carbon::parse('2026-11-14 09:02', 'Asia/Singapore'));
        $this->assertSame(['New title 5'], DailyRelease::run()['released']);
    }

    public function test_round_the_clock_claims_and_people_changes_are_left_for_a_person(): void
    {
        User::factory()->create(['is_active' => true, 'is_hidden' => true])->assignRole('super-admin');
        $held = $this->rewrite(1, 'Call our 24/7 locksmith team.');
        $this->rewrite(2, 'A dripping tap wastes water around the clock.');
        $byPerson = $this->rewrite(3);
        $byPerson->update(['note' => null]);

        $this->at('18:30');
        $r = DailyRelease::run();
        $this->assertSame(['New title 2'], $r['released'], '"around the clock" about water is fine');
        $this->assertArrayHasKey($held->id, $r['held']);
        $this->assertSame('pending', $held->fresh()->status);
        $this->assertSame('pending', $byPerson->fresh()->status, 'only imported AI rewrites are released');
    }

    public function test_dry_run_changes_nothing(): void
    {
        User::factory()->create(['is_active' => true, 'is_hidden' => true])->assignRole('super-admin');
        $rev = $this->rewrite(1);

        $this->at('10:00');
        $this->assertSame(['New title 1'], DailyRelease::run(dry: true)['released']);
        $this->assertSame('pending', $rev->fresh()->status);
    }
}
