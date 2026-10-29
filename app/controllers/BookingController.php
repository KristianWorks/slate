<?php
/**
 * app/controllers/BookingController.php
 *
 * Public booking surface for Slate.
 *
 *  GET  /book                          calendar page (Alpine + HTMX)
 *  GET  /book/calendar                 HTMX fragment, month grid
 *  GET  /api/slots                     HTMX fragment, slot cards for one date
 *  POST /book                          customer submits the booking form
 *  GET  /booking/{token}               confirmation page (also handles cancelled state)
 *  GET  /booking/{token}/cancel        cancel confirmation dialog
 *  POST /booking/{token}/cancel        actually cancels
 *  GET  /booking/{token}/reschedule    calendar in reschedule-mode
 *  POST /booking/{token}/reschedule    moves the existing appointment
 *
 * Race-safety: the store/reschedule paths wrap their conflict check + write
 * in a single transaction and lock candidate rows with `SELECT ... FOR
 * UPDATE`. MySQL InnoDB at the default REPEATABLE READ isolation level adds
 * next-key (gap) locks, which prevents two simultaneous customers from
 * both passing their conflict check for the same empty interval.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Middleware\RateLimit;
use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\Setting;
use App\Services\IcsGenerator;
use App\Services\Mailer;
use App\Services\SlotGenerator;
use App\Services\WebhookDispatcher;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class BookingController
{
    /* ============================================================
     * Calendar page + HTMX fragments
     * ============================================================ */

    /** GET /book */
    public function index(): void
    {
        $services = Service::active();
        if ($services === []) {
            view('booking/no_services', ['title' => 'Booking — Slate']);
            return;
        }

        $service = $this->resolveService($services, (int) ($_GET['service_id'] ?? 0));
        $gen     = SlotGenerator::fromSettings();
        $month   = $this->resolveMonth($_GET['month'] ?? null, $gen->timezone);

        $payload                   = $this->buildCalendarPayload($month, $service, $gen);
        $payload['services']       = $services;
        $payload['service']        = $service;
        $payload['title']          = 'Book — ' . ($_ENV['APP_NAME'] ?? 'Slate');
        $payload['businessTz']     = $gen->timezone->getName();
        $payload['mode']           = 'book';
        $payload['rescheduleToken']= null;
        $payload['rescheduleAppt'] = null;

        view('booking/index', $payload);
    }

    /** GET /book/calendar — HTMX fragment */
    public function calendar(): void
    {
        $services = Service::active();
        if ($services === []) { echo ''; return; }
        $service = $this->resolveService($services, (int) ($_GET['service_id'] ?? 0));
        $gen     = SlotGenerator::fromSettings();
        $month   = $this->resolveMonth($_GET['month'] ?? null, $gen->timezone);

        view('booking/_calendar', $this->buildCalendarPayload($month, $service, $gen), null);
    }

    /** GET /api/slots — HTMX fragment */
    public function slots(): void
    {
        // Cheap to compute but hammer-able — keep it civil at 60/min/IP.
        RateLimit::guard('api_slots', maxAttempts: 60, windowSeconds: 60);

        $services = Service::active();
        if ($services === []) {
            echo '<div class="slots-empty"><p>No services are currently bookable.</p></div>';
            return;
        }
        $service = $this->resolveService($services, (int) ($_GET['service_id'] ?? 0));

        $date = (string) ($_GET['date'] ?? '');
        if (!$this->isValidDate($date)) {
            http_response_code(400);
            echo '<div class="slots-empty"><p>Invalid date.</p></div>';
            return;
        }

        $gen   = SlotGenerator::fromSettings();
        $slots = $gen->slotsForDate($date, $service);
        $dt    = new DateTimeImmutable($date, $gen->timezone);

        view('booking/_slots', [
            'date'      => $date,
            'service'   => $service,
            'slots'     => $slots,
            'dateLabel' => $dt->format('l, F j'),
        ], null);
    }

    /* ============================================================
     * Booking form submission
     * ============================================================ */

    /** POST /book */
    public function store(): void
    {
        // 10 booking submits per hour per IP. Catches form-spammers without
        // blocking a real customer who fat-fingers their first attempt.
        RateLimit::guard('book', maxAttempts: 10, windowSeconds: 3600);
        csrf_check();

        $services = Service::active();
        if ($services === []) {
            flash('error', 'No services are currently bookable.');
            redirect('/book');
        }

        $input = $this->extractFormInput();
        $service = null;
        foreach ($services as $s) {
            if ((int) $s['id'] === $input['service_id']) { $service = $s; break; }
        }

        $errors = $this->validateFormInput($input, $service !== null);
        if ($errors !== []) {
            old_set($input);
            errors_set($errors);
            flash('error', 'Please fix the errors below.');
            redirect('/book?service_id=' . (int) $input['service_id']);
        }
        /** @var array<string,mixed> $service */

        $gen = SlotGenerator::fromSettings();
        $tz  = $gen->timezone;

        $startsAt = $this->buildDateTime($input['date'], $input['time'], $tz);
        if ($startsAt === null) {
            flash('error', 'That time is no longer valid.');
            redirect('/book?service_id=' . (int) $input['service_id']);
        }
        $endsAt = $startsAt->add(new DateInterval('PT' . (int) $service['duration_minutes'] . 'M'));

        $startsAtStr = $startsAt->format('Y-m-d H:i:s');
        $endsAtStr   = $endsAt->format('Y-m-d H:i:s');

        // Re-derive the slot grid for the day to make sure this exact slot is
        // legitimately allowed by current rules (business hours / blocked / past).
        if (!$this->slotIsInGeneratedGrid($input['date'], $input['time'], $service, $gen)) {
            flash('error', 'That time is no longer available. Please pick another.');
            redirect('/book?service_id=' . (int) $service['id']);
        }

        // --- Race-safe insert -------------------------------------------------
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // FOR UPDATE locks both matching rows and (under REPEATABLE READ) the gap.
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
                logger()->warning('Slot conflict on booking', [
                    'service_id' => (int) $service['id'],
                    'starts_at'  => $startsAtStr,
                    'ends_at'    => $endsAtStr,
                    'conflict_ids' => array_map(static fn($r) => (int) $r['id'], $conflicts),
                    'customer_email' => $input['email'],
                ]);
                flash('error', 'Sorry, that slot was just taken. Please pick another time.');
                redirect('/book?service_id=' . (int) $service['id']);
            }

            $token = bin2hex(random_bytes(16)); // 32 hex chars

            // TODO: when Stripe is integrated, set status='pending' until payment
            //       intent succeeds; flip to 'confirmed' on webhook.
            $apptId = (int) $db->insert('appointments', [
                'user_id'        => current_user_id(),
                'service_id'     => (int) $service['id'],
                'customer_name'  => $input['name'],
                'customer_email' => $input['email'],
                'customer_phone' => $input['phone'],
                'starts_at'      => $startsAtStr,
                'ends_at'        => $endsAtStr,
                'status'         => 'confirmed',
                'notes'          => $input['notes'] !== '' ? $input['notes'] : null,
                'cancel_token'   => $token,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->pdo()->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        // ---------------------------------------------------------------------

        logger()->info('Booking created', [
            'appointment_id' => $apptId,
            'service_id'     => (int) $service['id'],
            'starts_at'      => $startsAtStr,
            'user_id'        => current_user_id(),
            'customer_email' => $input['email'],
        ]);

        Mailer::queueConfirmation($apptId);
        Mailer::queueAdminNotification($apptId);
        WebhookDispatcher::dispatch('booking.created', $apptId);

        redirect('/booking/' . $token);
    }

    /* ============================================================
     * Confirmation page
     * ============================================================ */

    /** GET /booking/{token} */
    public function confirmation(string $token): void
    {
        $appt = $this->loadByToken($token);

        $gen = SlotGenerator::fromSettings();
        $tz  = $gen->timezone;
        $startsAt = new DateTimeImmutable((string) $appt['starts_at'], $tz);
        $endsAt   = new DateTimeImmutable((string) $appt['ends_at'],   $tz);

        view('booking/confirmation', [
            'title'        => 'Booking confirmed — Slate',
            'appt'         => $appt,
            'startsAt'     => $startsAt,
            'endsAt'       => $endsAt,
            'token'        => $token,
            'reference'    => strtoupper(substr($token, -8)),
            'gcalUrl'      => $this->buildGoogleCalendarUrl($appt, $startsAt, $endsAt),
            'outlookUrl'   => $this->buildOutlookCalendarUrl($appt, $startsAt, $endsAt),
            'icsUrl'       => '/booking/' . $token . '/calendar.ics',
            'location'     => (string) (Setting::get('location') ?? ''),
            'businessName' => (string) (Setting::get('business_name', 'Slate') ?? 'Slate'),
            'tzName'       => $tz->getName(),
        ]);
    }

    /**
     * GET /booking/{token}/calendar.ics
     *
     * Streams an RFC 5545 .ics file for the appointment. If the appointment
     * is currently cancelled, returns a METHOD:CANCEL invite so any prior
     * import is automatically removed from the customer's calendar.
     */
    public function calendarFile(string $token): void
    {
        $appt = $this->loadByToken($token);

        // Sequence = number of state-changing events already logged. Keeps
        // re-downloads consistent with what was last emailed.
        $sequence = (int) \App\Core\Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM email_log
              WHERE appointment_id = :id
                AND type IN ('reschedule','cancellation')",
            ['id' => (int) $appt['id']],
        );

        $gen = new IcsGenerator();
        $ics = $appt['status'] === 'cancelled'
            ? $gen->generateForCancellation($appt, $sequence)
            : $gen->generateForAppointment($appt, $sequence);

        // Method-aware MIME so the served file matches what the email attaches.
        $method = $appt['status'] === 'cancelled' ? 'CANCEL' : 'REQUEST';

        header('Content-Type: text/calendar; charset=UTF-8; method=' . $method);
        header('Content-Disposition: attachment; filename="booking.ics"');
        header('Content-Length: ' . strlen($ics));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $ics;
        exit;
    }

    /* ============================================================
     * Cancel
     * ============================================================ */

    /** GET /booking/{token}/cancel */
    public function cancelConfirm(string $token): void
    {
        $appt = $this->loadByToken($token);

        if ($appt['status'] === 'cancelled') {
            flash('info', 'This appointment is already cancelled.');
            redirect('/booking/' . $token);
        }

        $gen = SlotGenerator::fromSettings();
        $startsAt = new DateTimeImmutable((string) $appt['starts_at'], $gen->timezone);

        view('booking/cancel_confirm', [
            'title'    => 'Cancel booking — Slate',
            'appt'     => $appt,
            'startsAt' => $startsAt,
            'token'    => $token,
        ]);
    }

    /** POST /booking/{token}/cancel */
    public function cancelDo(string $token): void
    {
        csrf_check();
        $appt = $this->loadByToken($token);

        if ($appt['status'] !== 'cancelled') {
            Appointment::cancel((int) $appt['id']);
            logger()->info('Booking cancelled by customer', [
                'appointment_id' => (int) $appt['id'],
                'starts_at'      => (string) $appt['starts_at'],
                'customer_email' => (string) $appt['customer_email'],
            ]);
            Mailer::queueCancellation((int) $appt['id']);
            WebhookDispatcher::dispatch('booking.cancelled', (int) $appt['id']);
            flash('success', 'Your appointment has been cancelled.');
        } else {
            flash('info', 'This appointment was already cancelled.');
        }
        redirect('/booking/' . $token);
    }

    /* ============================================================
     * Reschedule
     * ============================================================ */

    /** GET /booking/{token}/reschedule */
    public function rescheduleShow(string $token): void
    {
        $appt = $this->loadByToken($token);
        if ($appt['status'] === 'cancelled') {
            flash('error', 'This appointment was cancelled. Please make a new booking.');
            redirect('/book');
        }

        $services = Service::active();
        if ($services === []) {
            flash('error', 'No services available right now.');
            redirect('/booking/' . $token);
        }

        $service = $this->resolveService($services, (int) $appt['service_id']);
        $gen     = SlotGenerator::fromSettings();
        $tz      = $gen->timezone;

        $oldStart = new DateTimeImmutable((string) $appt['starts_at'], $tz);
        $month    = $this->resolveMonth($_GET['month'] ?? null, $tz);

        $payload                    = $this->buildCalendarPayload($month, $service, $gen);
        $payload['services']        = $services;
        $payload['service']         = $service;
        $payload['title']           = 'Reschedule — Slate';
        $payload['businessTz']      = $tz->getName();
        $payload['mode']            = 'reschedule';
        $payload['rescheduleToken'] = $token;
        $payload['rescheduleAppt']  = $appt;
        $payload['rescheduleOldLabel'] = $oldStart->format('l, F j · g:i A');

        view('booking/index', $payload);
    }

    /** POST /booking/{token}/reschedule */
    public function rescheduleDo(string $token): void
    {
        csrf_check();
        $appt = $this->loadByToken($token);

        if ($appt['status'] === 'cancelled') {
            flash('error', 'Cancelled appointments cannot be rescheduled.');
            redirect('/booking/' . $token);
        }

        $services = Service::active();
        $serviceId = (int) ($_POST['service_id'] ?? $appt['service_id']);
        $service = null;
        foreach ($services as $s) {
            if ((int) $s['id'] === $serviceId) { $service = $s; break; }
        }
        if ($service === null) {
            flash('error', 'That service is no longer available.');
            redirect('/booking/' . $token . '/reschedule');
        }

        $date = (string) ($_POST['date'] ?? '');
        $time = (string) ($_POST['time'] ?? '');
        if (!$this->isValidDate($date) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
            flash('error', 'Please pick a valid time.');
            redirect('/booking/' . $token . '/reschedule');
        }

        $gen = SlotGenerator::fromSettings();
        $tz  = $gen->timezone;
        $startsAt = $this->buildDateTime($date, $time, $tz);
        if ($startsAt === null) {
            flash('error', 'That time is no longer valid.');
            redirect('/booking/' . $token . '/reschedule');
        }
        $endsAt = $startsAt->add(new DateInterval('PT' . (int) $service['duration_minutes'] . 'M'));

        if (!$this->slotIsInGeneratedGrid($date, $time, $service, $gen)) {
            flash('error', 'That time is no longer available. Please pick another.');
            redirect('/booking/' . $token . '/reschedule');
        }

        $startsAtStr = $startsAt->format('Y-m-d H:i:s');
        $endsAtStr   = $endsAt->format('Y-m-d H:i:s');

        // Capture the OLD time before the update so the reschedule email can
        // show "was → now" struck-through next to the new time.
        $oldStartsAt = (string) $appt['starts_at'];

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $conflicts = $db->fetchAll(
                "SELECT id FROM appointments
                  WHERE status IN ('pending','confirmed')
                    AND starts_at < :end
                    AND ends_at   > :start
                    AND id <> :self
                  FOR UPDATE",
                ['start' => $startsAtStr, 'end' => $endsAtStr, 'self' => (int) $appt['id']],
            );
            if ($conflicts !== []) {
                $db->rollBack();
                logger()->warning('Slot conflict on reschedule', [
                    'appointment_id' => (int) $appt['id'],
                    'service_id'     => (int) $service['id'],
                    'starts_at'      => $startsAtStr,
                    'conflict_ids'   => array_map(static fn($r) => (int) $r['id'], $conflicts),
                ]);
                flash('error', 'Sorry, that slot was just taken. Please pick another time.');
                redirect('/booking/' . $token . '/reschedule');
            }

            Appointment::reschedule(
                id:        (int) $appt['id'],
                serviceId: (int) $service['id'],
                startsAt:  $startsAtStr,
                endsAt:    $endsAtStr,
            );

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->pdo()->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        Mailer::queueReschedule((int) $appt['id'], $oldStartsAt);
        WebhookDispatcher::dispatch('booking.rescheduled', (int) $appt['id']);

        flash('success', 'Your appointment has been moved.');
        redirect('/booking/' . $token);
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    /**
     * @return array{
     *   name:string, email:string, phone:string, notes:string,
     *   terms:int, service_id:int, date:string, time:string
     * }
     */
    private function extractFormInput(): array
    {
        return [
            'name'       => trim((string) ($_POST['name']       ?? '')),
            'email'      => trim((string) ($_POST['email']      ?? '')),
            'phone'      => trim((string) ($_POST['phone']      ?? '')),
            'notes'      => trim((string) ($_POST['notes']      ?? '')),
            'terms'      => !empty($_POST['terms']) ? 1 : 0,
            'service_id' => (int)  ($_POST['service_id']        ?? 0),
            'date'       => (string) ($_POST['date']            ?? ''),
            'time'       => (string) ($_POST['time']            ?? ''),
        ];
    }

    /**
     * @param  array<string,scalar> $i
     * @return array<string,string>
     */
    private function validateFormInput(array $i, bool $serviceExists): array
    {
        $errors = [];

        if (mb_strlen((string) $i['name']) < 2 || mb_strlen((string) $i['name']) > 150) {
            $errors['name'] = 'Please enter your full name (at least 2 characters).';
        }
        if (!filter_var($i['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        $phoneDigits = preg_replace('/\D+/', '', (string) $i['phone']);
        if (!preg_match('/^[\d\s+().\-]{7,40}$/', (string) $i['phone'])
            || strlen((string) $phoneDigits) < 7
            || strlen((string) $phoneDigits) > 15) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }
        if (mb_strlen((string) $i['notes']) > 500) {
            $errors['notes'] = 'Notes must be 500 characters or fewer.';
        }
        if ((int) $i['terms'] !== 1) {
            $errors['terms'] = 'Please agree to the booking terms to continue.';
        }
        if (!$serviceExists) {
            $errors['service_id'] = 'Please pick a service.';
        }
        if (!$this->isValidDate((string) $i['date'])) {
            $errors['date'] = 'Please pick a valid date.';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $i['time'])) {
            $errors['time'] = 'Please pick a valid time.';
        }
        return $errors;
    }

    /** @return DateTimeImmutable|null */
    private function buildDateTime(string $date, string $time, DateTimeZone $tz): ?DateTimeImmutable
    {
        try {
            // Normalise "HH:MM" -> "HH:MM:00"
            if (preg_match('/^\d{2}:\d{2}$/', $time)) $time .= ':00';
            return new DateTimeImmutable("{$date} {$time}", $tz);
        } catch (\Exception) {
            return null;
        }
    }

    private function slotIsInGeneratedGrid(
        string $date,
        string $time,
        array $service,
        SlotGenerator $gen,
    ): bool {
        $target = preg_match('/^\d{2}:\d{2}$/', $time) ? "{$time}:00" : $time;
        foreach ($gen->slotsForDate($date, $service) as $slot) {
            if ($slot['starts_at']->format('H:i:s') === $target) return true;
        }
        return false;
    }

    /** @return array<string,mixed> never returns null — sends 404 instead */
    private function loadByToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            http_response_code(404);
            view('errors/404', ['title' => 'Not found — Slate']);
            exit;
        }
        $appt = Appointment::findByToken($token);
        if ($appt === null) {
            http_response_code(404);
            view('errors/404', ['title' => 'Not found — Slate']);
            exit;
        }
        return $appt;
    }

    /**
     * @param  list<array<string,mixed>> $services
     * @return array<string,mixed>
     */
    private function resolveService(array $services, int $id): array
    {
        foreach ($services as $s) {
            if ((int) $s['id'] === $id) return $s;
        }
        return $services[0];
    }

    private function resolveMonth(?string $value, DateTimeZone $tz): string
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return $value;
        }
        return (new DateTimeImmutable('now', $tz))->format('Y-m');
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)) return false;
        return checkdate(
            (int) substr($date, 5, 2),
            (int) substr($date, 8, 2),
            (int) substr($date, 0, 4),
        );
    }

    /**
     * @param  array<string,mixed> $service
     * @return array<string,mixed>
     */
    private function buildCalendarPayload(string $month, array $service, SlotGenerator $gen): array
    {
        $tz        = $gen->timezone;
        $first     = new DateTimeImmutable("{$month}-01", $tz);
        $weekday   = (int) $first->format('w');
        $gridStart = $first->modify("-{$weekday} days");

        $cells = [];
        for ($i = 0; $i < 42; $i++) {
            $cells[] = $gridStart->modify("+{$i} days");
        }

        return [
            'month'        => $month,
            'monthLabel'   => $first->format('F Y'),
            'prevMonth'    => $first->modify('-1 month')->format('Y-m'),
            'nextMonth'    => $first->modify('+1 month')->format('Y-m'),
            'cells'        => $cells,
            'today'        => (new DateTimeImmutable('now', $tz))->format('Y-m-d'),
            'hoursByDow'   => BusinessHour::week(),
            'blocked'      => BlockedDate::map(),
            'availability' => $gen->availabilityMap(
                                $cells[0]->format('Y-m-d'),
                                $cells[41]->format('Y-m-d'),
                                $service,
                             ),
            'service'      => $service,
        ];
    }

    /**
     * @param array<string,mixed> $appt
     */
    private function buildGoogleCalendarUrl(array $appt, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): string
    {
        $utc        = new DateTimeZone('UTC');
        $businessNm = (string) (Setting::get('business_name', 'Slate') ?? 'Slate');
        $location   = (string) (Setting::get('location') ?? '');

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => ($appt['service_name'] ?? 'Appointment') . ' @ ' . $businessNm,
            'dates'    => $startsAt->setTimezone($utc)->format('Ymd\THis\Z')
                        . '/' . $endsAt->setTimezone($utc)->format('Ymd\THis\Z'),
            'details'  => 'Booking reference: ' . strtoupper(substr((string) $appt['cancel_token'], -8)),
            'location' => $location,
        ]);
    }

    /**
     * Build an Outlook Online "compose event" deeplink.
     * Outlook expects ISO 8601 with explicit Z suffix (UTC).
     *
     * @param array<string,mixed> $appt
     */
    private function buildOutlookCalendarUrl(array $appt, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): string
    {
        $utc        = new DateTimeZone('UTC');
        $businessNm = (string) (Setting::get('business_name', 'Slate') ?? 'Slate');
        $location   = (string) (Setting::get('location') ?? '');

        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query([
            'path'    => '/calendar/action/compose',
            'rru'     => 'addevent',
            'startdt' => $startsAt->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'enddt'   => $endsAt->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'subject' => ($appt['service_name'] ?? 'Appointment') . ' @ ' . $businessNm,
            'body'    => 'Booking reference: ' . strtoupper(substr((string) $appt['cancel_token'], -8)),
            'location'=> $location,
        ]);
    }
}
