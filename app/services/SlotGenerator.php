<?php
/**
 * app/services/SlotGenerator.php
 *
 * Pure-PHP slot computation engine. Given a date and a service, it produces
 * the list of available booking slots, honouring:
 *
 *   - business_hours for the weekday
 *   - blocked_dates (whole-day closures)
 *   - existing pending/confirmed appointments (conflict detection)
 *   - settings.buffer_minutes        (gap between slots on the grid)
 *   - settings.min_notice_hours      (earliest slot from "now")
 *   - settings.advance_booking_days  (latest date allowed)
 *
 * The slot grid is fixed: open_time, open_time + duration + buffer,
 * open_time + 2(duration + buffer), ... up to close_time. Conflicting
 * grid slots are then subtracted.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\BlockedDate;
use App\Models\BusinessHour;
use App\Models\Setting;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class SlotGenerator
{
    public function __construct(
        public readonly DateTimeZone $timezone,
        public readonly int $bufferMinutes,
        public readonly int $minNoticeHours,
        public readonly int $advanceBookingDays,
    ) {}

    /** Build the generator from the live `settings` table. */
    public static function fromSettings(): self
    {
        $tzName = Setting::get('timezone') ?? date_default_timezone_get();
        try {
            $tz = new DateTimeZone($tzName);
        } catch (\Exception) {
            $tz = new DateTimeZone('UTC');
        }
        return new self(
            timezone:           $tz,
            bufferMinutes:      max(0, (int) (Setting::get('buffer_minutes', '15') ?? '15')),
            minNoticeHours:     max(0, (int) (Setting::get('min_notice_hours', '2') ?? '2')),
            advanceBookingDays: max(1, (int) (Setting::get('advance_booking_days', '60') ?? '60')),
        );
    }

    /**
     * Compute slots for one date + service.
     *
     * @param  array<string,mixed> $service
     * @return list<array{starts_at: DateTimeImmutable, ends_at: DateTimeImmutable}>
     */
    public function slotsForDate(string $date, array $service): array
    {
        $hoursByDow   = BusinessHour::week();
        $blocked      = BlockedDate::findByDate($date) !== null;
        $appointments = $this->loadAppointmentsForRange($date, $date);

        return $this->generate(
            date:         $date,
            service:      $service,
            hoursByDow:   $hoursByDow,
            blocked:      $blocked,
            appointments: $appointments,
        );
    }

    /**
     * Map "YYYY-MM-DD" => bool over the inclusive range — true when at least
     * one slot is available for the supplied service.
     *
     * Batched: appointments are loaded once for the whole range, not per day.
     *
     * @param  array<string,mixed> $service
     * @return array<string,bool>
     */
    public function availabilityMap(string $startDate, string $endDate, array $service): array
    {
        $hoursByDow   = BusinessHour::week();
        $blockedMap   = BlockedDate::map();
        $appointments = $this->loadAppointmentsForRange($startDate, $endDate);

        // Bucket appointments by date so per-day generation is O(slots × appts-that-day).
        $byDate = [];
        foreach ($appointments as $row) {
            $d = substr((string) $row['starts_at'], 0, 10);
            $byDate[$d][] = $row;
        }

        $out = [];
        $cursor = new DateTimeImmutable($startDate, $this->timezone);
        $end    = new DateTimeImmutable($endDate,   $this->timezone);
        $oneDay = new DateInterval('P1D');
        while ($cursor <= $end) {
            $d = $cursor->format('Y-m-d');
            $slots = $this->generate(
                date:         $d,
                service:      $service,
                hoursByDow:   $hoursByDow,
                blocked:      isset($blockedMap[$d]),
                appointments: $byDate[$d] ?? [],
            );
            $out[$d] = $slots !== [];
            $cursor  = $cursor->add($oneDay);
        }
        return $out;
    }

    /**
     * Core slot generator. Pure over its inputs; no DB calls.
     *
     * @param  array<string,mixed>                                                                     $service
     * @param  array<int,array{day_of_week:int, open_time:?string, close_time:?string, is_closed:int}> $hoursByDow
     * @param  list<array<string,mixed>>                                                               $appointments
     * @return list<array{starts_at: DateTimeImmutable, ends_at: DateTimeImmutable}>
     */
    private function generate(
        string $date,
        array $service,
        array $hoursByDow,
        bool $blocked,
        array $appointments,
    ): array {
        if ($blocked) {
            return [];
        }

        $day   = new DateTimeImmutable($date, $this->timezone);
        $dow   = (int) $day->format('w');                  // 0 = Sunday
        $hours = $hoursByDow[$dow] ?? null;
        if ($hours === null
            || (int) $hours['is_closed'] === 1
            || $hours['open_time'] === null
            || $hours['close_time'] === null
        ) {
            return [];
        }

        $now      = new DateTimeImmutable('now', $this->timezone);
        $minStart = $now->add(new DateInterval('PT' . $this->minNoticeHours . 'H'));

        // Advance-booking window: don't allow dates further out than today + N days.
        $today          = $now->setTime(0, 0);
        $maxBookingDay  = $today->add(new DateInterval('P' . $this->advanceBookingDays . 'D'));
        if ($day->setTime(0, 0) > $maxBookingDay) {
            return [];
        }

        // Don't return slots for past days at all.
        if ($day->setTime(0, 0) < $today) {
            return [];
        }

        $open  = new DateTimeImmutable("{$date} {$hours['open_time']}",  $this->timezone);
        $close = new DateTimeImmutable("{$date} {$hours['close_time']}", $this->timezone);

        $duration = max(5, (int) $service['duration_minutes']);
        $step     = $duration + $this->bufferMinutes;

        $durationInterval = new DateInterval("PT{$duration}M");
        $stepInterval     = new DateInterval("PT{$step}M");

        $slots  = [];
        $cursor = $open;
        $safety = 0;
        while ($safety++ < 500) {
            $slotEnd = $cursor->add($durationInterval);
            if ($slotEnd > $close) {
                break;
            }

            // Min-notice / past-time check.
            if ($cursor < $minStart) {
                $cursor = $cursor->add($stepInterval);
                continue;
            }

            // Conflict: any appointment that overlaps [cursor, slotEnd) blocks the slot.
            $conflict = false;
            foreach ($appointments as $a) {
                $aStart = new DateTimeImmutable((string) $a['starts_at'], $this->timezone);
                $aEnd   = new DateTimeImmutable((string) $a['ends_at'],   $this->timezone);
                if ($aStart < $slotEnd && $aEnd > $cursor) {
                    $conflict = true;
                    break;
                }
            }

            if (!$conflict) {
                $slots[] = ['starts_at' => $cursor, 'ends_at' => $slotEnd];
            }
            $cursor = $cursor->add($stepInterval);
        }
        return $slots;
    }

    /** @return list<array{id:int|string, starts_at:string, ends_at:string}> */
    private function loadAppointmentsForRange(string $startDate, string $endDate): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT id, starts_at, ends_at
               FROM appointments
              WHERE starts_at >= :s
                AND starts_at < DATE_ADD(:e, INTERVAL 1 DAY)
                AND status IN ('pending','confirmed')",
            ['s' => $startDate, 'e' => $endDate],
        );
    }
}
