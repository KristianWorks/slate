<?php
/**
 * app/services/RecurrenceExpander.php
 *
 * Pure date math for recurring appointment series: given a series' rule
 * and a date window, returns every date it's due on. No DB access — the
 * existence check ("has this date already been materialized?") and the
 * actual booking happen separately in app/jobs/ExpandRecurringSeries.php,
 * which keeps this class trivially unit-testable.
 */

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

final class RecurrenceExpander
{
    /**
     * @param array{freq:string, starts_on:string, ends_on:?string} $series
     * @return list<string> Y-m-d dates, inclusive of both $today and $horizon
     */
    public static function datesFor(
        array $series,
        \DateTimeZone $tz,
        CarbonImmutable $today,
        CarbonImmutable $horizon,
    ): array {
        $dates  = [];
        $cursor = CarbonImmutable::createFromFormat('Y-m-d', (string) $series['starts_on'], $tz)->startOfDay();
        $endsOn = $series['ends_on'] !== null
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $series['ends_on'], $tz)->endOfDay()
            : null;

        while ($cursor->lessThanOrEqualTo($horizon)) {
            if ($endsOn !== null && $cursor->greaterThan($endsOn)) {
                break;
            }
            if ($cursor->greaterThanOrEqualTo($today)) {
                $dates[] = $cursor->format('Y-m-d');
            }
            $cursor = match ($series['freq']) {
                'weekly'   => $cursor->addWeek(),
                'biweekly' => $cursor->addWeeks(2),
                // Known limitation: a series starting on the 31st lands on
                // the last day of shorter months rather than skipping them.
                'monthly'  => $cursor->addMonthNoOverflow(),
                default    => $cursor->addWeek(),
            };
        }

        return $dates;
    }
}
