<?php
/**
 * tests/Feature/BookingFlowTest.php
 *
 * End-to-end booking lifecycle through the real controller methods.
 * No HTTP layer — we drive $_POST / $_SESSION / $_SERVER directly
 * and invoke the controller, catching the redirect() it would emit.
 *
 * MAIL_DRIVER=log (set in tests/bootstrap.php) means the Mailer
 * records to email_log without touching SMTP.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\BookingController;
use App\Core\Database;
use Tests\TestCase;

final class BookingFlowTest extends TestCase
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
            'phone'      => '+1 555-555-1234',
            'notes'      => '',
            'terms'      => '1',
            'service_id' => (int) $service['id'],
            'date'       => $this->nextWeekday(),
            'time'       => '10:15',
        ];
        return $this->withCsrf(array_merge($defaults, $overrides));
    }

    public function test_guest_can_book_an_available_slot(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->bookingPostBody();

        $url = $this->captureRedirect(fn () => (new BookingController())->store());

        $this->assertMatchesRegularExpression('#^/booking/[a-f0-9]{32}$#', $url);

        $count = (int) Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM appointments');
        $this->assertSame(1, $count);
    }

    public function test_booking_a_taken_slot_returns_an_error(): void
    {
        $date    = $this->nextWeekday();
        $service = $this->standardService();

        $this->createAppointment([
            'service_id'  => (int) $service['id'],
            'starts_at'   => $date . ' 10:15:00',
            'ends_at'     => $date . ' 11:15:00',
            'status'      => 'confirmed',
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->bookingPostBody(['date' => $date, 'time' => '10:15']);

        $url = $this->captureRedirect(fn () => (new BookingController())->store());

        // The store handler bounces back to /book on conflict, NOT /booking/{token}.
        $this->assertStringStartsWith('/book?', $url);

        // Only the pre-existing appointment remains.
        $count = (int) Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM appointments');
        $this->assertSame(1, $count);
    }

    public function test_two_concurrent_bookings_for_the_same_slot_only_one_succeeds(): void
    {
        // Single-process simulation: write the first booking directly, then
        // hit the controller for the second. The SELECT … FOR UPDATE inside
        // the transaction sees the existing row and rolls back. (True
        // wall-clock concurrency is verified by InnoDB's lock semantics,
        // not by PHPUnit — that's a property of the storage engine.)
        $date    = $this->nextWeekday();
        $service = $this->standardService();
        $startsAt = $date . ' 10:15:00';
        $endsAt   = $date . ' 11:15:00';

        Database::getInstance()->insert('appointments', [
            'service_id'     => (int) $service['id'],
            'customer_name'  => 'First Mover',
            'customer_email' => 'first@example.com',
            'customer_phone' => '+1 555-0001',
            'starts_at'      => $startsAt,
            'ends_at'        => $endsAt,
            'status'         => 'confirmed',
            'cancel_token'   => bin2hex(random_bytes(16)),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->bookingPostBody(['date' => $date, 'time' => '10:15']);

        $url = $this->captureRedirect(fn () => (new BookingController())->store());

        $this->assertStringStartsWith('/book?', $url);

        $count = (int) Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM appointments WHERE starts_at = :s",
            ['s' => $startsAt],
        );
        $this->assertSame(1, $count, 'Only one booking should exist for the contested slot');
    }

    public function test_booking_creates_a_unique_cancel_token(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->bookingPostBody();
        $this->captureRedirect(fn () => (new BookingController())->store());

        $row = Database::getInstance()->fetch('SELECT cancel_token FROM appointments LIMIT 1');
        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) $row['cancel_token']);
    }

    public function test_booking_sends_confirmation_email(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->bookingPostBody(['email' => 'jane@example.com']);
        $this->captureRedirect(fn () => (new BookingController())->store());

        $rows = Database::getInstance()->fetchAll(
            "SELECT type, sent_to FROM email_log WHERE type = 'confirmation' AND sent_to = :e",
            ['e' => 'jane@example.com'],
        );
        $this->assertCount(1, $rows, 'Exactly one confirmation should be logged');
    }

    public function test_cancellation_via_token_works(): void
    {
        $appt = $this->createAppointment(['status' => 'confirmed']);
        $token = (string) $appt['cancel_token'];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf();
        $this->captureRedirect(fn () => (new BookingController())->cancelDo($token));

        $row = Database::getInstance()->fetch(
            'SELECT status FROM appointments WHERE id = :id',
            ['id' => (int) $appt['id']],
        );
        $this->assertSame('cancelled', $row['status']);

        // Email log should now have a cancellation row.
        $emailCount = (int) Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM email_log WHERE appointment_id = :id AND type = 'cancellation'",
            ['id' => (int) $appt['id']],
        );
        $this->assertSame(1, $emailCount);
    }

    public function test_invalid_token_does_not_match_any_appointment(): void
    {
        // The controller 404s when findByToken returns null. We assert the
        // null invariant directly — exercising the controller flow would
        // require neutralising exit() which adds little extra coverage.
        $this->assertNull(\App\Models\Appointment::findByToken('not-a-real-token'));
        $this->assertNull(\App\Models\Appointment::findByToken(''));
        $this->assertNull(\App\Models\Appointment::findByToken('abc'));
    }

    public function test_well_formed_but_unknown_token_does_not_match(): void
    {
        // Valid hex shape, but no row exists → 404 path in the controller.
        $this->assertNull(\App\Models\Appointment::findByToken(str_repeat('f', 32)));
    }
}
