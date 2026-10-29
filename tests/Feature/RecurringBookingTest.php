<?php
/**
 * tests/Feature/RecurringBookingTest.php
 *
 * The "repeats" option on the booking form: store() should create an
 * appointment_series row alongside the first appointment, and cancelling
 * with scope=all_future should stop the series and cancel not-yet-started
 * instances without touching the one being cancelled twice or past ones.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\BookingController;
use App\Core\Database;
use Tests\TestCase;

final class RecurringBookingTest extends TestCase
{
    private function standardService(): array
    {
        return Database::getInstance()->fetch("SELECT * FROM services WHERE name = 'Standard Appointment'");
    }

    private function bookingPostBody(array $overrides = []): array
    {
        $service = $this->standardService();
        $defaults = [
            'name'       => 'Jane Doe',
            'email'      => 'jane@example.com',
            'phone'      => '+63 912 345 6789',
            'notes'      => '',
            'terms'      => '1',
            'service_id' => (int) $service['id'],
            'date'       => $this->nextWeekday(),
            'time'       => '10:15',
            'repeat'     => 'none',
        ];
        return $this->withCsrf(array_merge($defaults, $overrides));
    }

    public function test_booking_with_repeat_creates_a_series_and_links_the_first_instance(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->bookingPostBody(['repeat' => 'weekly']);

        $url = $this->captureRedirect(fn () => (new BookingController())->store());
        $this->assertMatchesRegularExpression('#^/booking/[a-f0-9]{32}$#', $url);

        $seriesCount = (int) Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM appointment_series');
        $this->assertSame(1, $seriesCount);

        $series = Database::getInstance()->fetch('SELECT * FROM appointment_series LIMIT 1');
        $this->assertSame('weekly', $series['freq']);
        $this->assertSame('active', $series['status']);

        $appt = Database::getInstance()->fetch('SELECT * FROM appointments LIMIT 1');
        $this->assertSame((int) $series['id'], (int) $appt['series_id']);
    }

    public function test_booking_without_repeat_leaves_series_id_null(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->bookingPostBody(['repeat' => 'none']);
        $this->captureRedirect(fn () => (new BookingController())->store());

        $appt = Database::getInstance()->fetch('SELECT * FROM appointments LIMIT 1');
        $this->assertNull($appt['series_id']);

        $seriesCount = (int) Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM appointment_series');
        $this->assertSame(0, $seriesCount);
    }

    public function test_an_invalid_repeat_value_is_rejected(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->bookingPostBody(['repeat' => 'daily']); // not a supported frequency

        $url = $this->captureRedirect(fn () => (new BookingController())->store());
        $this->assertStringStartsWith('/book?', $url);

        $count = (int) Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM appointments');
        $this->assertSame(0, $count);
    }

    public function test_cancelling_with_scope_all_future_cancels_the_series_and_future_instances(): void
    {
        $series = $this->createSeries(['freq' => 'weekly']);

        $thisWeek = $this->createAppointment([
            'series_id' => (int) $series['id'],
            'status'    => 'confirmed',
            'starts_at' => date('Y-m-d H:i:s', strtotime('+1 day 10:00')),
            'ends_at'   => date('Y-m-d H:i:s', strtotime('+1 day 11:00')),
        ]);
        $nextWeek = $this->createAppointment([
            'series_id' => (int) $series['id'],
            'status'    => 'confirmed',
            'starts_at' => date('Y-m-d H:i:s', strtotime('+8 days 10:00')),
            'ends_at'   => date('Y-m-d H:i:s', strtotime('+8 days 11:00')),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf(['scope' => 'all_future']);
        $this->captureRedirect(fn () => (new BookingController())->cancelDo((string) $thisWeek['cancel_token']));

        $seriesRow = Database::getInstance()->fetch(
            'SELECT status FROM appointment_series WHERE id = :id',
            ['id' => (int) $series['id']],
        );
        $this->assertSame('cancelled', $seriesRow['status']);

        $thisWeekRow = Database::getInstance()->fetch('SELECT status FROM appointments WHERE id = :id', ['id' => (int) $thisWeek['id']]);
        $nextWeekRow = Database::getInstance()->fetch('SELECT status FROM appointments WHERE id = :id', ['id' => (int) $nextWeek['id']]);

        $this->assertSame('cancelled', $thisWeekRow['status']);
        $this->assertSame('cancelled', $nextWeekRow['status']);
    }

    public function test_cancelling_with_default_scope_leaves_future_instances_alone(): void
    {
        $series = $this->createSeries(['freq' => 'weekly']);

        $thisWeek = $this->createAppointment([
            'series_id' => (int) $series['id'],
            'status'    => 'confirmed',
            'starts_at' => date('Y-m-d H:i:s', strtotime('+1 day 10:00')),
            'ends_at'   => date('Y-m-d H:i:s', strtotime('+1 day 11:00')),
        ]);
        $nextWeek = $this->createAppointment([
            'series_id' => (int) $series['id'],
            'status'    => 'confirmed',
            'starts_at' => date('Y-m-d H:i:s', strtotime('+8 days 10:00')),
            'ends_at'   => date('Y-m-d H:i:s', strtotime('+8 days 11:00')),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf(); // no 'scope' field — defaults to "this"
        $this->captureRedirect(fn () => (new BookingController())->cancelDo((string) $thisWeek['cancel_token']));

        $seriesRow = Database::getInstance()->fetch(
            'SELECT status FROM appointment_series WHERE id = :id',
            ['id' => (int) $series['id']],
        );
        $this->assertSame('active', $seriesRow['status']);

        $nextWeekRow = Database::getInstance()->fetch('SELECT status FROM appointments WHERE id = :id', ['id' => (int) $nextWeek['id']]);
        $this->assertSame('confirmed', $nextWeekRow['status']);
    }
}
