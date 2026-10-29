<?php
/**
 * app/services/IcsGenerator.php
 *
 * RFC 5545–compliant iCalendar (.ics) builder for booking events.
 *
 *   $gen = new IcsGenerator();
 *   $body = $gen->generateForAppointment($appt, $sequence);     // METHOD:REQUEST
 *   $body = $gen->generateForCancellation($appt, $sequence);    // METHOD:CANCEL
 *
 * Output is a single string with CRLF line endings, lines folded at
 * 75 octets per RFC 5545 §3.1, TEXT values escaped per §3.3.11.
 *
 * SEQUENCE handling: callers (Mailer / BookingController) pass in the
 * current sequence number — it monotonically increases per UID across
 * the lifetime of the booking. The Mailer derives it from how many
 * reschedule/cancellation rows already exist in `email_log` for that
 * appointment.
 *
 * Validated against https://icalendar.org/validator.html — no errors.
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use DateTimeImmutable;
use DateTimeZone;

final class IcsGenerator
{
    private readonly string $appUrl;
    private readonly string $businessName;
    private readonly string $businessEmail;
    private readonly string $location;
    private readonly string $appHost;
    private readonly DateTimeZone $businessTz;

    public function __construct()
    {
        $this->appUrl       = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');
        $this->businessName = (string) (Setting::get('business_name', 'Slate') ?? 'Slate');
        $this->businessEmail= (string) (Setting::get('contact_email', 'no-reply@slate.test') ?? 'no-reply@slate.test');
        $this->location     = (string) (Setting::get('location', '') ?? '');

        $host = parse_url($this->appUrl, PHP_URL_HOST);
        $this->appHost = (is_string($host) && $host !== '') ? $host : 'slate.local';

        $tzName = Setting::get('timezone') ?? date_default_timezone_get();
        try {
            $this->businessTz = new DateTimeZone($tzName);
        } catch (\Exception) {
            $this->businessTz = new DateTimeZone('UTC');
        }
    }

    /**
     * Calendar invite. Compatible with Google, Apple, Outlook, Thunderbird.
     * @param array<string,mixed> $appointment
     */
    public function generateForAppointment(array $appointment, int $sequence = 0): string
    {
        return $this->build($appointment, $sequence, 'REQUEST', 'CONFIRMED');
    }

    /**
     * Calendar cancellation. SAME UID + higher SEQUENCE + METHOD:CANCEL —
     * calendar clients use this to remove the event from the user's calendar.
     * @param array<string,mixed> $appointment
     */
    public function generateForCancellation(array $appointment, int $sequence = 0): string
    {
        return $this->build($appointment, $sequence, 'CANCEL', 'CANCELLED');
    }

    /* ============================================================
     * Internals
     * ============================================================ */

    /**
     * @param array<string,mixed> $appointment
     */
    private function build(array $appointment, int $sequence, string $method, string $status): string
    {
        $utc = new DateTimeZone('UTC');

        $starts = (new DateTimeImmutable((string) $appointment['starts_at'], $this->businessTz))->setTimezone($utc);
        $ends   = (new DateTimeImmutable((string) $appointment['ends_at'],   $this->businessTz))->setTimezone($utc);
        $now    = new DateTimeImmutable('now', $utc);

        $token         = (string) $appointment['cancel_token'];
        $reference     = strtoupper(substr($token, -8));
        $serviceName   = (string) ($appointment['service_name']  ?? 'Appointment');
        $customerName  = (string) ($appointment['customer_name'] ?? '');
        $customerEmail = (string) ($appointment['customer_email']?? '');
        $notes         = (string) ($appointment['notes']         ?? '');

        $uid           = $token . '@' . $this->appHost;
        $viewUrl       = $this->appUrl . '/booking/' . $token;
        $cancelUrl     = $this->appUrl . '/booking/' . $token . '/cancel';
        $rescheduleUrl = $this->appUrl . '/booking/' . $token . '/reschedule';

        // Description body — reads naturally when calendars render it.
        $descParts = [];
        if ($notes !== '') {
            $descParts[] = 'Notes: ' . $notes;
            $descParts[] = '';
        }
        $descParts[] = 'View booking: ' . $viewUrl;
        $descParts[] = 'Reschedule:   ' . $rescheduleUrl;
        $descParts[] = 'Cancel:       ' . $cancelUrl;
        $descParts[] = '';
        $descParts[] = 'Reference: ' . $reference;
        $description = implode("\n", $descParts);

        $summary = $serviceName . ' — ' . $this->businessName;

        // ----- Build the content lines (unfolded). -----
        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//Slate//Slate Booking//EN';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:' . $method;
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . $now->format('Ymd\THis\Z');
        $lines[] = 'DTSTART:' . $starts->format('Ymd\THis\Z');
        $lines[] = 'DTEND:'   . $ends->format('Ymd\THis\Z');
        $lines[] = 'SEQUENCE:' . max(0, $sequence);
        $lines[] = 'STATUS:' . $status;
        $lines[] = 'SUMMARY:' . $this->escapeText($summary);
        $lines[] = 'DESCRIPTION:' . $this->escapeText($description);
        if ($this->location !== '') {
            $lines[] = 'LOCATION:' . $this->escapeText($this->location);
        }
        $lines[] = 'URL:' . $viewUrl;
        $lines[] = 'ORGANIZER;CN=' . $this->escapeParam($this->businessName)
                 . ':mailto:' . $this->businessEmail;
        if ($customerEmail !== '') {
            $lines[] = 'ATTENDEE;CN=' . $this->escapeParam($customerName)
                     . ';ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE'
                     . ':mailto:' . $customerEmail;
        }
        $lines[] = 'TRANSP:OPAQUE'; // appointment blocks the time slot
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // ----- Fold each line to ≤75 octets, join with CRLF, terminate with CRLF. -----
        $folded = array_map([$this, 'fold'], $lines);
        return implode("\r\n", $folded) . "\r\n";
    }

    /**
     * RFC 5545 §3.3.11 — TEXT value escaping.
     * Order matters: backslashes are doubled FIRST so we don't double-escape
     * the backslashes we'll introduce for the other replacements.
     */
    private function escapeText(string $text): string
    {
        return str_replace(
            ['\\',   "\r\n", "\r",  "\n",  ';',   ','],
            ['\\\\', '\\n',  '\\n', '\\n', '\\;', '\\,'],
            $text,
        );
    }

    /**
     * RFC 5545 §3.2 — param values containing : ; , or DQUOTE must be
     * wrapped in double quotes. There's no in-quote escape mechanism, so
     * any embedded DQUOTE has to be substituted (single quote is the
     * conventional choice).
     */
    private function escapeParam(string $value): string
    {
        // CR/LF aren't allowed in params at all.
        $value = str_replace(["\r", "\n"], '', $value);
        if (preg_match('/[":;,]/', $value) === 1) {
            return '"' . str_replace('"', "'", $value) . '"';
        }
        return $value;
    }

    /**
     * RFC 5545 §3.1 — fold any content line longer than 75 octets onto
     * subsequent lines that begin with a single SPACE.
     *
     * Octet count, not character count — multi-byte chars are heavier than
     * they look. We back off from a split point that would land mid-byte
     * inside a UTF-8 sequence so the parser never sees a malformed run.
     */
    private function fold(string $line): string
    {
        $maxFirst = 75;
        $maxCont  = 74; // 1 octet eaten by the leading SPACE
        if (strlen($line) <= $maxFirst) {
            return $line;
        }

        $out       = '';
        $remaining = $line;
        $first     = true;

        while (strlen($remaining) > ($first ? $maxFirst : $maxCont)) {
            $maxLen = $first ? $maxFirst : $maxCont;
            $chunk  = substr($remaining, 0, $maxLen);

            // Don't split mid-UTF-8 sequence.
            while ($chunk !== '' && !mb_check_encoding($chunk, 'UTF-8')) {
                $chunk = substr($chunk, 0, -1);
            }
            if ($chunk === '') break; // safety — should never happen

            $out      .= ($first ? '' : ' ') . $chunk . "\r\n";
            $remaining = substr($remaining, strlen($chunk));
            $first     = false;
        }
        $out .= ($first ? '' : ' ') . $remaining;
        return $out;
    }
}
