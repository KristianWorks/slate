<?php
/**
 * tests/Unit/RecurrenceExpanderTest.php
 *
 * Pure date math, no DB — RecurrenceExpander only ever sees a `$series`
 * array and a time window, never the database.
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RecurrenceExpander;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class RecurrenceExpanderTest extends TestCase
{
    private \DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new \DateTimeZone('Asia/Manila');
    }

    public function test_weekly_steps_by_seven_days(): void
    {
        $series = ['freq' => 'weekly', 'starts_on' => '2026-01-06', 'ends_on' => null]; // a Tuesday
        $dates  = RecurrenceExpander::datesFor(
            $series,
            $this->tz,
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-01', $this->tz),
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-27', $this->tz)->endOfDay(),
        );

        $this->assertSame(['2026-01-06', '2026-01-13', '2026-01-20', '2026-01-27'], $dates);
    }

    public function test_biweekly_steps_by_fourteen_days(): void
    {
        $series = ['freq' => 'biweekly', 'starts_on' => '2026-01-06', 'ends_on' => null];
        $dates  = RecurrenceExpander::datesFor(
            $series,
            $this->tz,
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-01', $this->tz),
            CarbonImmutable::createFromFormat('Y-m-d', '2026-02-10', $this->tz)->endOfDay(),
        );

        $this->assertSame(['2026-01-06', '2026-01-20', '2026-02-03'], $dates);
    }

    public function test_monthly_steps_by_calendar_month(): void
    {
        $series = ['freq' => 'monthly', 'starts_on' => '2026-01-15', 'ends_on' => null];
        $dates  = RecurrenceExpander::datesFor(
            $series,
            $this->tz,
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-01', $this->tz),
            CarbonImmutable::createFromFormat('Y-m-d', '2026-04-01', $this->tz)->endOfDay(),
        );

        $this->assertSame(['2026-01-15', '2026-02-15', '2026-03-15'], $dates);
    }

    public function test_dates_before_today_are_excluded(): void
    {
        // Series started two weeks before "today" — only future occurrences
        // from today onward should be returned.
        $series = ['freq' => 'weekly', 'starts_on' => '2026-01-01', 'ends_on' => null];
        $dates  = RecurrenceExpander::datesFor(
            $series,
            $this->tz,
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-10', $this->tz), // "today"
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-25', $this->tz)->endOfDay(),
        );

        foreach ($dates as $d) {
            $this->assertGreaterThanOrEqual('2026-01-10', $d);
        }
        $this->assertSame(['2026-01-15', '2026-01-22'], $dates);
    }

    public function test_series_ends_on_stops_expansion(): void
    {
        $series = ['freq' => 'weekly', 'starts_on' => '2026-01-06', 'ends_on' => '2026-01-15'];
        $dates  = RecurrenceExpander::datesFor(
            $series,
            $this->tz,
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-01', $this->tz),
            CarbonImmutable::createFromFormat('Y-m-d', '2026-02-01', $this->tz)->endOfDay(),
        );

        // Jan 6 and Jan 13 are <= ends_on (Jan 15); Jan 20 is not.
        $this->assertSame(['2026-01-06', '2026-01-13'], $dates);
    }

    public function test_horizon_bounds_the_far_end(): void
    {
        $series = ['freq' => 'weekly', 'starts_on' => '2026-01-06', 'ends_on' => null];
        $dates  = RecurrenceExpander::datesFor(
            $series,
            $this->tz,
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-01', $this->tz),
            CarbonImmutable::createFromFormat('Y-m-d', '2026-01-10', $this->tz)->endOfDay(),
        );

        $this->assertSame(['2026-01-06'], $dates);
    }
}
