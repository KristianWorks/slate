<?php
/**
 * app/controllers/api/BookingsController.php
 *
 *   POST /api/v1/bookings                       create
 *   GET  /api/v1/bookings/{token}               retrieve
 *   POST /api/v1/bookings/{token}/cancel        cancel
 *
 * The cancel_token serves as the lookup key — possession of it acts as
 * auth (same as the web confirmation URL).
 *
 * Race-safety is preserved here too: the create path runs the same
 * transaction + SELECT … FOR UPDATE that the web flow uses.
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\Mailer;
use App\Services\SlotGenerator;
use App\Services\WebhookDispatcher;
use DateInterval;
use DateTimeImmutable;

final class BookingsController extends ApiController
{
    /* ============================================================
     * POST /api/v1/bookings
     * ============================================================ */

    public function store(): void
    {
        $this->applyPublicRateLimit('bookings_create');

        $body = $this->input();

        $name    = trim((string) ($body['name']    ?? ''));
        $email   = trim((string) ($body['email']   ?? ''));
        $phone   = trim((string) ($body['phone']   ?? ''));
        $notes   = trim((string) ($body['notes']   ?? ''));
        $svcId   = (int)         ($body['service_id'] ?? 0);
        $date    = (string)      ($body['date']    ?? '');
        $time    = (string)      ($body['time']    ?? '');

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) $errors['name']  = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors['email'] = 'Valid email is required.';
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (!preg_match('/^[\d\s+().\-]{7,40}$/', $phone) || strlen($digits) < 7 || strlen($digits) > 15) {
            $errors['phone'] = 'Valid phone number is required.';
        }
        if (mb_strlen($notes) > 500) $errors['notes'] = 'Notes must be 500 characters or fewer.';
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)) {
            $errors['date'] = 'date must be YYYY-MM-DD.';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
            $errors['time'] = 'time must be HH:MM or HH:MM:SS (24-hour).';
        }
        if ($svcId <= 0) {
            $errors['service_id'] = 'service_id is required.';
        }
        if ($errors !== []) {
            $this->unprocessable($errors);
        }

        $service = null;
        foreach (Service::active() as $s) {
            if ((int) $s['id'] === $svcId) { $service = $s; break; }
        }
        if ($service === null) {
            $this->error(404, 'service_not_found', "No active service with id {$svcId}.");
        }

        $gen = SlotGenerator::fromSettings();
        $tz  = $gen->timezone;
        if (preg_match('/^\d{2}:\d{2}$/', $time)) $time .= ':00';

        try {
            $startsAt = new DateTimeImmutable("{$date} {$time}", $tz);
        } catch (\Exception) {
            $this->error(422, 'invalid_datetime', 'Could not construct the appointment time.');
        }
        $endsAt      = $startsAt->add(new DateInterval('PT' . (int) $service['duration_minutes'] . 'M'));
        $startsAtStr = $startsAt->format('Y-m-d H:i:s');
        $endsAtStr   = $endsAt->format('Y-m-d H:i:s');

        // Same generator-grid sanity check as the web flow.
        $valid = false;
        foreach ($gen->slotsForDate($date, $service) as $slot) {
            if ($slot['starts_at']->format('H:i:s') === $time) { $valid = true; break; }
        }
        if (!$valid) {
            $this->error(409, 'slot_unavailable', 'That time is not in the available slot grid.');
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $conflicts = $db->fetchAll(
                "SELECT id FROM appointments
                  WHERE status IN ('pending','confirmed')
                    AND starts_at < :end
                    AND ends_at   > :start
                  FOR UPDATE",
                ['start' => $startsAtStr, 'end' => $endsAtStr],
            );
            if ($conflicts !== []) {
                $db->rollBack();
                logger()->warning('API slot conflict on booking', [
                    'service_id'   => (int) $service['id'],
                    'starts_at'    => $startsAtStr,
                    'conflict_ids' => array_map(static fn($r) => (int) $r['id'], $conflicts),
                ]);
                $this->error(409, 'slot_taken', 'That slot was just taken by another booking.');
            }

            $token  = bin2hex(random_bytes(16));
            $apptId = (int) $db->insert('appointments', [
                'user_id'        => null,
                'service_id'     => (int) $service['id'],
                'customer_name'  => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'starts_at'      => $startsAtStr,
                'ends_at'        => $endsAtStr,
                'status'         => 'confirmed',
                'notes'          => $notes !== '' ? $notes : null,
                'cancel_token'   => $token,
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->pdo()->inTransaction()) $db->rollBack();
            throw $e;
        }

        logger()->info('API booking created', [
            'appointment_id' => $apptId,
            'service_id'     => (int) $service['id'],
            'starts_at'      => $startsAtStr,
        ]);

        Mailer::queueConfirmation($apptId);
        Mailer::queueAdminNotification($apptId);
        WebhookDispatcher::dispatch('booking.created', $apptId);

        $this->created($this->presentBooking(Appointment::findById($apptId)));
    }

    /* ============================================================
     * GET /api/v1/bookings/{token}
     * ============================================================ */

    public function show(string $token): void
    {
        $this->applyPublicRateLimit('bookings_show');
        $appt = $this->loadByToken($token);
        $this->ok($this->presentBooking($appt));
    }

    /* ============================================================
     * POST /api/v1/bookings/{token}/cancel
     * ============================================================ */

    public function cancel(string $token): void
    {
        $this->applyPublicRateLimit('bookings_cancel');
        $appt = $this->loadByToken($token);

        if ($appt['status'] === 'cancelled') {
            // Idempotent — return current state with 200.
            $this->ok($this->presentBooking($appt));
        }

        Appointment::cancel((int) $appt['id']);
        logger()->info('API booking cancelled', ['appointment_id' => (int) $appt['id']]);

        Mailer::queueCancellation((int) $appt['id']);
        WebhookDispatcher::dispatch('booking.cancelled', (int) $appt['id']);

        $this->ok($this->presentBooking(Appointment::findById((int) $appt['id'])));
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    /** @return array<string,mixed> */
    private function loadByToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $this->error(404, 'not_found', 'No booking found for that token.');
        }
        $appt = Appointment::findByToken($token);
        if ($appt === null) {
            $this->error(404, 'not_found', 'No booking found for that token.');
        }
        return $appt;
    }

    /**
     * @param  array<string,mixed> $appt
     * @return array<string,mixed>
     */
    private function presentBooking(array $appt): array
    {
        $utc = new \DateTimeZone('UTC');
        $tz  = SlotGenerator::fromSettings()->timezone;

        return [
            'id'              => (int)    $appt['id'],
            'reference'       => strtoupper(substr((string) $appt['cancel_token'], -8)),
            'cancel_token'    => (string) $appt['cancel_token'],
            'status'          => (string) $appt['status'],
            'service' => [
                'id'               => (int)    $appt['service_id'],
                'name'             => (string) $appt['service_name'],
                'duration_minutes' => (int)    $appt['service_duration'],
                'price'            => (float)  $appt['service_price'],
                'color'            => (string) $appt['service_color'],
            ],
            'customer' => [
                'name'  => (string) $appt['customer_name'],
                'email' => (string) $appt['customer_email'],
                'phone' => (string) $appt['customer_phone'],
            ],
            'starts_at' => (new \DateTimeImmutable((string) $appt['starts_at'], $tz))->setTimezone($utc)->format('c'),
            'ends_at'   => (new \DateTimeImmutable((string) $appt['ends_at'],   $tz))->setTimezone($utc)->format('c'),
            'timezone'  => $tz->getName(),
            'notes'     => $appt['notes'] !== null ? (string) $appt['notes'] : null,
            'created_at'=> (new \DateTimeImmutable((string) $appt['created_at']))->setTimezone($utc)->format('c'),
            'links' => [
                'self'       => '/api/v1/bookings/' . $appt['cancel_token'],
                'cancel'     => '/api/v1/bookings/' . $appt['cancel_token'] . '/cancel',
                'web_view'   => '/booking/' . $appt['cancel_token'],
            ],
        ];
    }
}
