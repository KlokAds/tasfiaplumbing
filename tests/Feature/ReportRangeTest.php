<?php

namespace Tests\Feature;

use App\Support\Google\ReportRange;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_presets_end_at_the_latest_complete_day_and_compare_with_the_period_before(): void
    {
        $r = ReportRange::make('28d', null, null, 2, 16);
        $this->assertSame(['2026-09-01', '2026-09-28'], [$r->start->toDateString(), $r->end->toDateString()]);
        $this->assertSame(['2026-08-04', '2026-08-31'], [$r->previousStart()->toDateString(), $r->previousEnd()->toDateString()]);
        $this->assertFalse($r->weekly());
        $this->assertSame('', $r->cacheSuffix());

        $year = ReportRange::make('12m', null, null, 1, 26);
        $this->assertSame(365, $year->days());
        $this->assertTrue($year->weekly());
    }

    public function test_custom_dates_are_kept_inside_what_google_has(): void
    {
        $r = ReportRange::make('custom', '2020-01-01', '2026-12-31', 2, 16);
        $this->assertSame('2025-05-30', $r->start->toDateString());
        $this->assertSame('2026-09-28', $r->end->toDateString());

        $swapped = ReportRange::make('custom', '2026-09-10', '2026-09-01', 1, 26);
        $this->assertSame(['2026-09-01', '2026-09-10'], [$swapped->start->toDateString(), $swapped->end->toDateString()]);
        $this->assertSame(10, $swapped->days());
        $this->assertNotSame('', $swapped->cacheSuffix());
    }

    public function test_bad_input_falls_back_to_28_days(): void
    {
        $this->assertSame('28d', ReportRange::make('forever', null, null, 2, 16)->key);
        $this->assertSame('28d', ReportRange::make('custom', 'not-a-date', 'x', 2, 16)->key);
    }

    public function test_days_add_up_into_weeks(): void
    {
        $weeks = ReportRange::toWeeks([
            ['date' => '2026-09-21', 'clicks' => 2, 'impressions' => 10],
            ['date' => '2026-09-27', 'clicks' => 3, 'impressions' => 5],
            ['date' => '2026-09-28', 'clicks' => 1, 'impressions' => 1],
        ], ['clicks', 'impressions']);

        $this->assertSame([
            ['date' => '2026-09-21', 'clicks' => 5, 'impressions' => 15],
            ['date' => '2026-09-28', 'clicks' => 1, 'impressions' => 1],
        ], $weeks);
    }

    public function test_missing_days_are_filled_with_zero(): void
    {
        $r = ReportRange::make('7d', null, null, 2, 16);
        $days = $r->fillDays([['date' => '2026-09-24', 'clicks' => 3, 'impressions' => 40]], ['clicks', 'impressions']);

        $this->assertCount(7, $days);
        $this->assertSame(['date' => '2026-09-22', 'clicks' => 0, 'impressions' => 0], $days[0]);
        $this->assertSame(3, $days[2]['clicks']);
    }
}
