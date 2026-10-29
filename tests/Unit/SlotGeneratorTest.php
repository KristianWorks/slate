<?php
/**
 * tests/Unit/SlotGeneratorTest.php
 *
 * The booking engine — the single highest-risk piece of logic in the app.
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\SlotGenerator;
use Tests\TestCase;

final class SlotGeneratorTest extends TestCase
{
    private function standardService(): array
    {
        return Database::getInstance()->fetch("SELECT * FROM services WHERE name = 'Standard Appointment'");
    }

    private function extendedService(): array
    {
        return Database::getInstance()->fetch("SELECT * FROM services WHERE name = 'Extended Session'");
    }

    public function test_it_returns_empty_slots_on_closed_days(): void
    {
        // Sunday is is_closed=1 in seedDefaults().
        $gen   = SlotGenerator::fromSettings();
        $slots = $gen->slotsForDate($this->nextSunday(), $this->standardService());
        $this->assertSame([], $slots);
    }

    public function test_it_returns_empty_slots_on_blocked_dates(): void
    {
        $date = $this->nextWeekday();
        Database::getInstance()->insert('blocked_dates', [
            'date'   => $date,
            'reason' => 'Holiday',
        ]);

        $gen   = SlotGenerator::fromSettings();
        $slots = $gen->slotsForDate($date, $this->standardService());
        $this->assertSame([], $slots);
    }

    public function test_it_returns_empty_slots_for_past_dates(): void
    {
        $past = (new \DateTimeImmutable('yesterday', new \DateTimeZone('America/Toronto')))->format('Y-m-d');
        $gen  = SlotGenerator::fromSettings();
        $this->assertSame([], $gen->slotsForDate($past, $this->standardService()));
    }

    public function test_it_generates_slots_in_service_duration_plus_buffer_increments(): void
    {
        // Standard service: 60 min + 15 min buffer = 75 min step.
        $gen   = SlotGenerator::fromSettings();
        $slots = $gen->slotsForDate($this->nextWeekday(), $this->standardService());

        $this->assertNotEmpty($slots);

        for ($i = 1; $i < count($slots); $i++) {
            $diff = $slots[$i]['starts_at']->getTimestamp()
                  - $slots[$i - 1]['starts_at']->getTimestamp();
            $this->assertSame(
                75 * 60,
                $diff,
                "Slot {$i} should start 75 minutes after slot " . ($i - 1),
            );
        }
    }

    public function test_it_excludes_slots_that_overlap_existing_appointments(): void
    {
        $date    = $this->nextWeekday();
        $service = $this->standardService();

        // 10:15 is slot #2 on the 9:00 / 75-min-step grid.
        Database::getInstance()->insert('appointments', [
            'service_id'     => $service['id'],
            'customer_name'  => 'X',
            'customer_email' => 'x@x.com',
            'customer_phone' => '+1234567',
            'starts_at'      => $date . ' 10:15:00',
            'ends_at'        => $date . ' 11:15:00',
            'status'         => 'confirmed',
            'cancel_token'   => bin2hex(random_bytes(16)),
        ]);

        $gen   = SlotGenerator::fromSettings();
        $times = array_map(
            static fn($s) => $s['starts_at']->format('H:i'),
            $gen->slotsForDate($date, $service),
        );

        $this->assertNotContains('10:15', $times, '10:15 should be blocked by the confirmed appointment');
    }

    public function test_it_respects_buffer_time_between_appointments(): void
    {
        // buffer_minutes=15 → first slot 09:00, next 10:15, etc.
        $gen   = SlotGenerator::fromSettings();
        $slots = $gen->slotsForDate($this->nextWeekday(), $this->standardService());

        $this->assertNotEmpty($slots);
        $this->assertSame('09:00:00', $slots[0]['starts_at']->format('H:i:s'));
        $this->assertSame('10:15:00', $slots[1]['starts_at']->format('H:i:s'));
    }

    public function test_it_does_not_return_slots_past_close_time(): void
    {
        // Mon-Fri close at 18:00.
        $gen   = SlotGenerator::fromSettings();
        $slots = $gen->slotsForDate($this->nextWeekday(), $this->standardService());

        foreach ($slots as $slot) {
            $this->assertLessThanOrEqual(
                '18:00:00',
                $slot['ends_at']->format('H:i:s'),
                'No slot may end after close time',
            );
        }
    }

    public function test_it_handles_a_service_duration_longer_than_remaining_hours(): void
    {
        // Saturday: 10:00-14:00 (4 hours window).
        // Extended Session = 90 min + 15 buffer = 105 min step.
        //   Slot 1: 10:00 → 11:30  (fits)
        //   Slot 2: 11:45 → 13:15  (fits)
        //   Slot 3: 13:30 → 15:00  (past close, dropped)
        $gen   = SlotGenerator::fromSettings();
        $slots = $gen->slotsForDate($this->nextSaturday(), $this->extendedService());

        $this->assertCount(2, $slots);
        $this->assertSame('10:00:00', $slots[0]['starts_at']->format('H:i:s'));
        $this->assertSame('13:15:00', $slots[1]['ends_at']->format('H:i:s'));
    }

    public function test_it_excludes_only_confirmed_and_pending_not_cancelled(): void
    {
        $date    = $this->nextWeekday();
        $service = $this->standardService();

        $apptId = (int) Database::getInstance()->insert('appointments', [
            'service_id'     => $service['id'],
            'customer_name'  => 'Blocker',
            'customer_email' => 'b@x.com',
            'customer_phone' => '+1234567',
            'starts_at'      => $date . ' 10:15:00',
            'ends_at'        => $date . ' 11:15:00',
            'status'         => 'confirmed',
            'cancel_token'   => bin2hex(random_bytes(16)),
        ]);

        $gen = SlotGenerator::fromSettings();

        $before = array_map(
            static fn($s) => $s['starts_at']->format('H:i'),
            $gen->slotsForDate($date, $service),
        );
        $this->assertNotContains('10:15', $before, 'Confirmed appointment must block its slot');

        // Cancel it — same row, same time — and the slot must reappear.
        Database::getInstance()->query(
            "UPDATE appointments SET status = 'cancelled' WHERE id = :id",
            ['id' => $apptId],
        );

        $after = array_map(
            static fn($s) => $s['starts_at']->format('H:i'),
            $gen->slotsForDate($date, $service),
        );
        $this->assertContains('10:15', $after, 'Cancelled appointment must not block its slot');
    }
}
