<?php
/**
 * app/services/Mailer.php
 *
 * Transactional email for Slate. Wraps PHPMailer over Gmail (or any) SMTP.
 *
 * Public surface:
 *   instance:
 *     sendConfirmation($appointment, $toOverride = null): bool
 *     sendCancellation($appointment, $toOverride = null): bool
 *     sendReschedule($appointment, $oldStartsAt, $toOverride = null): bool
 *     sendReminder($appointment, $toOverride = null): bool
 *     sendAdminNotification($appointment, $toOverride = null): bool
 *
 *   static convenience (used by BookingController & jobs):
 *     queueConfirmation($appointmentId): bool
 *     queueCancellation($appointmentId): bool
 *     queueReschedule($appointmentId, $oldStartsAt = null): bool
 *     queueReminder($appointmentId): bool
 *     queueAdminNotification($appointmentId): bool
 *
 * Behaviour:
 *   - HTML body from /app/views/emails/<template>.php
 *   - Plain-text AltBody from /app/views/emails/text/<template>.txt.php
 *     (auto-falls back to strip_tags(HTML) if a text file is missing)
 *   - Successful send → one row in `email_log`
 *   - Failures are caught, logged via error_log(), and return false — so a
 *     bad SMTP config never breaks the booking flow.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Appointment;
use App\Models\Setting;
use DateTimeImmutable;
use DateTimeZone;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_CANCELLATION = 'cancellation';
    public const TYPE_RESCHEDULE   = 'reschedule';
    public const TYPE_REMINDER     = 'reminder';
    public const TYPE_ADMIN_NOTICE = 'admin_notification';

    /** @var array<string,mixed> */
    private readonly array $config;
    private readonly DateTimeZone $timezone;
    private readonly string $appUrl;
    private readonly string $businessName;
    private readonly string $location;
    private readonly string $contactPhone;
    private readonly string $contactEmail;
    private readonly string $adminEmail;

    public function __construct()
    {
        /** @var array<string,mixed> $cfg */
        $cfg = require base_path('config/mail.php');
        $this->config = $cfg;

        $tzName = Setting::get('timezone') ?? date_default_timezone_get();
        try {
            $this->timezone = new DateTimeZone($tzName);
        } catch (\Exception) {
            $this->timezone = new DateTimeZone('UTC');
        }

        $this->appUrl       = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');
        $this->businessName = (string) (Setting::get('business_name', 'Slate') ?? 'Slate');
        $this->location     = (string) (Setting::get('location')      ?? '');
        $this->contactPhone = (string) (Setting::get('contact_phone') ?? '');
        $this->contactEmail = (string) (Setting::get('contact_email') ?? '');
        $this->adminEmail   = $this->contactEmail !== ''
            ? $this->contactEmail
            : (string) ($this->config['from_address'] ?? '');
    }

    /* ============================================================
     * Static convenience API
     * ============================================================ */

    public static function queueConfirmation(int $appointmentId): bool
    {
        $appt = Appointment::findById($appointmentId);
        if ($appt === null) return false;
        return (new self())->sendConfirmation($appt);
    }

    public static function queueCancellation(int $appointmentId): bool
    {
        $appt = Appointment::findById($appointmentId);
        if ($appt === null) return false;
        return (new self())->sendCancellation($appt);
    }

    public static function queueReschedule(int $appointmentId, ?string $oldStartsAt = null): bool
    {
        $appt = Appointment::findById($appointmentId);
        if ($appt === null) return false;
        return (new self())->sendReschedule($appt, $oldStartsAt ?? (string) $appt['starts_at']);
    }

    public static function queueReminder(int $appointmentId): bool
    {
        $appt = Appointment::findById($appointmentId);
        if ($appt === null) return false;
        return (new self())->sendReminder($appt);
    }

    public static function queueAdminNotification(int $appointmentId): bool
    {
        $appt = Appointment::findById($appointmentId);
        if ($appt === null) return false;
        return (new self())->sendAdminNotification($appt);
    }

    /* ============================================================
     * Instance senders
     * ============================================================ */

    /** @param array<string,mixed> $appointment */
    public function sendConfirmation(array $appointment, ?string $toOverride = null): bool
    {
        $vars = $this->commonVars($appointment);
        $vars['headline'] = "You're booked";
        $vars['title']    = 'Booking confirmed · ' . $this->businessName;
        $sequence = $this->nextSequence((int) $appointment['id'], self::TYPE_CONFIRMATION);
        $ics      = (new IcsGenerator())->generateForAppointment($appointment, $sequence);
        return $this->send(
            to:            $toOverride ?? (string) $appointment['customer_email'],
            subject:       'Your booking is confirmed · ' . $vars['startsAtLong'],
            template:      'confirmation',
            vars:          $vars,
            appointmentId: (int) $appointment['id'],
            type:          self::TYPE_CONFIRMATION,
            icsContent:    $ics,
            icsMethod:     'REQUEST',
        );
    }

    /** @param array<string,mixed> $appointment */
    public function sendCancellation(array $appointment, ?string $toOverride = null): bool
    {
        $vars = $this->commonVars($appointment);
        $vars['headline'] = 'Your booking is cancelled';
        $vars['title']    = 'Booking cancelled · ' . $this->businessName;
        $sequence = $this->nextSequence((int) $appointment['id'], self::TYPE_CANCELLATION);
        // METHOD:CANCEL with same UID — removes the event from the customer's calendar.
        $ics      = (new IcsGenerator())->generateForCancellation($appointment, $sequence);
        return $this->send(
            to:            $toOverride ?? (string) $appointment['customer_email'],
            subject:       'Booking cancelled · ' . $vars['startsAtLong'],
            template:      'cancellation',
            vars:          $vars,
            appointmentId: (int) $appointment['id'],
            type:          self::TYPE_CANCELLATION,
            icsContent:    $ics,
            icsMethod:     'CANCEL',
        );
    }

    /** @param array<string,mixed> $appointment */
    public function sendReschedule(array $appointment, string $oldStartsAt, ?string $toOverride = null): bool
    {
        $vars = $this->commonVars($appointment);
        try {
            $oldDt = new DateTimeImmutable($oldStartsAt, $this->timezone);
            $vars['oldStartsAtLong'] = $oldDt->format('l, F j · g:i A');
        } catch (\Exception) {
            $vars['oldStartsAtLong'] = $oldStartsAt;
        }
        $vars['headline'] = 'Your booking has moved';
        $vars['title']    = 'Booking rescheduled · ' . $this->businessName;
        $sequence = $this->nextSequence((int) $appointment['id'], self::TYPE_RESCHEDULE);
        // METHOD:REQUEST with incremented SEQUENCE — calendars update the existing event.
        $ics      = (new IcsGenerator())->generateForAppointment($appointment, $sequence);
        return $this->send(
            to:            $toOverride ?? (string) $appointment['customer_email'],
            subject:       'Booking moved · now ' . $vars['startsAtLong'],
            template:      'reschedule',
            vars:          $vars,
            appointmentId: (int) $appointment['id'],
            type:          self::TYPE_RESCHEDULE,
            icsContent:    $ics,
            icsMethod:     'REQUEST',
        );
    }

    /** @param array<string,mixed> $appointment */
    public function sendReminder(array $appointment, ?string $toOverride = null): bool
    {
        $vars = $this->commonVars($appointment);
        $vars['headline'] = 'See you tomorrow';
        $vars['title']    = 'Reminder · ' . $this->businessName;
        return $this->send(
            to:            $toOverride ?? (string) $appointment['customer_email'],
            subject:       'Reminder: ' . $vars['startsAtTime'] . ' tomorrow',
            template:      'reminder',
            vars:          $vars,
            appointmentId: (int) $appointment['id'],
            type:          self::TYPE_REMINDER,
        );
    }

    /** @param array<string,mixed> $appointment */
    public function sendAdminNotification(array $appointment, ?string $toOverride = null): bool
    {
        $to = $toOverride ?? $this->adminEmail;
        if ($to === '') return false;

        $vars = $this->commonVars($appointment);
        $vars['headline'] = 'New booking';
        $vars['title']    = 'New booking · ' . $this->businessName;
        return $this->send(
            to:            $to,
            subject:       'New booking: ' . $appointment['customer_name'] . ' · ' . $vars['startsAtLong'],
            template:      'admin_notification',
            vars:          $vars,
            appointmentId: (int) $appointment['id'],
            type:          self::TYPE_ADMIN_NOTICE,
        );
    }

    /* ============================================================
     * Internals
     * ============================================================ */

    /**
     * Build the variable bag that every template expects.
     * @param  array<string,mixed> $appointment
     * @return array<string,mixed>
     */
    private function commonVars(array $appointment): array
    {
        $starts = new DateTimeImmutable((string) $appointment['starts_at'], $this->timezone);
        $ends   = new DateTimeImmutable((string) $appointment['ends_at'],   $this->timezone);
        $token  = (string) $appointment['cancel_token'];

        return [
            'appt'          => $appointment,
            'businessName'  => $this->businessName,
            'location'      => $this->location,
            'contactPhone'  => $this->contactPhone,
            'contactEmail'  => $this->contactEmail,
            'appUrl'        => $this->appUrl,
            'viewUrl'       => $this->appUrl . '/booking/' . $token,
            'cancelUrl'     => $this->appUrl . '/booking/' . $token . '/cancel',
            'rescheduleUrl' => $this->appUrl . '/booking/' . $token . '/reschedule',
            'bookUrl'       => $this->appUrl . '/book',
            'name'          => (string) $appointment['customer_name'],
            'email'         => (string) $appointment['customer_email'],
            'phone'         => (string) $appointment['customer_phone'],
            'notes'         => (string) ($appointment['notes'] ?? ''),
            'reference'     => strtoupper(substr($token, -8)),
            'serviceName'   => (string) ($appointment['service_name']  ?? ''),
            'serviceColor'  => (string) ($appointment['service_color'] ?? '#1A1A1A'),
            'duration'      => (int)    ($appointment['service_duration'] ?? 0),
            'startsAt'      => $starts,
            'endsAt'        => $ends,
            'startsAtLong'  => $starts->format('l, F j, Y · g:i A'),
            'startsAtDate'  => $starts->format('l, F j, Y'),
            'startsAtTime'  => $starts->format('g:i A'),
            'tzName'        => $this->timezone->getName(),
        ];
    }

    /** @param array<string,mixed> $vars */
    private function renderHtml(string $template, array $vars): string
    {
        return $this->renderFile("app/views/emails/{$template}.php", $vars);
    }

    /** @param array<string,mixed> $vars */
    private function renderText(string $template, array $vars): string
    {
        $path = "app/views/emails/text/{$template}.txt.php";
        if (!is_file(base_path($path))) {
            // Last-ditch fallback: strip the HTML.
            return trim(preg_replace('/\s+/', ' ', strip_tags($this->renderHtml($template, $vars))) ?? '');
        }
        return $this->renderFile($path, $vars);
    }

    /** @param array<string,mixed> $vars */
    private function renderFile(string $relPath, array $vars): string
    {
        $full = base_path($relPath);
        if (!is_file($full)) {
            throw new \RuntimeException("Email template not found: {$relPath}");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        require $full;
        return (string) ob_get_clean();
    }

    /**
     * Build PHPMailer, render templates, attach the .ics (if any), send,
     * log to email_log on success.
     *
     * @param array<string,mixed> $vars
     */
    private function send(
        string $to,
        string $subject,
        string $template,
        array $vars,
        int $appointmentId,
        string $type,
        ?string $icsContent = null,
        string $icsMethod = 'REQUEST',
    ): bool {
        try {
            $html = $this->renderHtml($template, $vars);
            $text = $this->renderText($template, $vars);

            // Test / local-dev mode — skip SMTP entirely, just record the
            // email_log row. Enabled by MAIL_DRIVER=log in the environment.
            if (strtolower((string) ($_ENV['MAIL_DRIVER'] ?? 'smtp')) === 'log') {
                Database::getInstance()->insert('email_log', [
                    'appointment_id' => $appointmentId,
                    'type'           => $type,
                    'sent_to'        => $to,
                ]);
                return true;
            }

            $m = new PHPMailer(true);
            $m->isSMTP();
            $m->Host     = (string) ($this->config['host']     ?? '');
            $m->Port     = (int)    ($this->config['port']     ?? 587);
            $m->Username = (string) ($this->config['username'] ?? '');
            $m->Password = (string) ($this->config['password'] ?? '');
            $m->SMTPAuth = $m->Username !== '';

            $enc = strtolower((string) ($this->config['encryption'] ?? 'tls'));
            if ($enc === 'ssl' || $enc === 'smtps') {
                $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls' || $enc === 'starttls') {
                $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $m->SMTPAutoTLS = false;
            }

            $m->CharSet  = PHPMailer::CHARSET_UTF8;
            $m->Encoding = PHPMailer::ENCODING_BASE64;

            $m->setFrom(
                (string) ($this->config['from_address'] ?? 'no-reply@localhost'),
                (string) ($this->config['from_name']    ?? $this->businessName),
            );
            if ($this->contactEmail !== '') {
                $m->addReplyTo($this->contactEmail, $this->businessName);
            }
            $m->addAddress($to);
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body    = $html;
            $m->AltBody = $text;

            // Attach calendar invite — single file ("booking.ics") with the
            // method-aware Content-Type that Outlook and Apple Mail look at
            // to auto-render the invite UI in the email preview.
            if ($icsContent !== null) {
                $mime = 'text/calendar; charset=UTF-8; method=' . strtoupper($icsMethod);
                $m->addStringAttachment(
                    $icsContent,
                    'booking.ics',
                    PHPMailer::ENCODING_BASE64,
                    $mime,
                );
            }

            $m->send();

            Database::getInstance()->insert('email_log', [
                'appointment_id' => $appointmentId,
                'type'           => $type,
                'sent_to'        => $to,
            ]);
            return true;
        } catch (PHPMailerException $e) {
            logger()->error('Email send failed', [
                'type'           => $type,
                'to'             => $to,
                'appointment_id' => $appointmentId,
                'error'          => $e->getMessage(),
                'cause'          => 'smtp',
            ]);
            return false;
        } catch (\Throwable $e) {
            logger()->error('Email send failed (unexpected)', [
                'type'           => $type,
                'to'             => $to,
                'appointment_id' => $appointmentId,
                'error'          => $e->getMessage(),
                'cause'          => 'unexpected',
            ]);
            return false;
        }
    }

    /**
     * Compute the next SEQUENCE number for this appointment's iCal UID.
     *
     * SEQUENCE in RFC 5545 must monotonically increase per UID across the
     * lifetime of the event. We derive it from `email_log` rows of types
     * that REPRESENT a state change (reschedule, cancellation). Reminders
     * and confirmations don't bump the sequence — they're notifications,
     * not updates — but they DO need to send a sequence equal to the
     * current state so the calendar doesn't reject them.
     */
    private function nextSequence(int $appointmentId, string $type): int
    {
        $priorChanges = (int) Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM email_log
              WHERE appointment_id = :id
                AND type IN ('reschedule', 'cancellation')",
            ['id' => $appointmentId],
        );

        if ($type === self::TYPE_RESCHEDULE || $type === self::TYPE_CANCELLATION) {
            return $priorChanges + 1;
        }
        return $priorChanges;
    }
}
