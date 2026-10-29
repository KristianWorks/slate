<?php
/**
 * app/controllers/admin/AppointmentController.php
 *
 * Admin appointment management.
 *
 *   GET  /admin/appointments                          full page (Today by default)
 *   GET  /admin/appointments/list                     list fragment (HTMX)
 *   GET  /admin/appointments/calendar                 month-view fragment (HTMX)
 *   GET  /admin/appointments/export                   CSV download
 *   GET  /admin/appointments/day/{date}               slide-panel: "this day"
 *   GET  /admin/appointments/{id}/panel               slide-panel: one appointment
 *   POST /admin/appointments/{id}/status              status change (action menu)
 *   POST /admin/appointments/{id}/notes               notes auto-save (HTMX debounce)
 *   POST /admin/appointments/{id}/resend-confirmation re-send the confirmation email
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Appointment;
use App\Models\Service;
use App\Services\Mailer;
use App\Services\SlotGenerator;
use App\Services\WebhookDispatcher;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class AppointmentController
{
    private const PER_PAGE = 25;

    public function __construct()
    {
        require_admin();
    }

    /* ============================================================
     * Pages / fragments
     * ============================================================ */

    /** GET /admin/appointments */
    public function index(): void
    {
        $filters = $this->extractFilters();

        // Pre-render either the list or the calendar fragment so the page
        // arrives populated (no initial-loading flash).
        if ($filters['view'] === 'calendar') {
            $main = $this->buildCalendarPayload($filters);
            $main['__partial'] = 'admin/appointments/_calendar';
        } else {
            $main = $this->buildListPayload($filters);
            $main['__partial'] = 'admin/appointments/_list';
        }

        view('admin/appointments/index', [
            'title'        => 'Appointments — Slate Admin',
            'active'       => 'appointments',
            'services'     => Service::all(),
            'filters'      => $filters,
            'currentMonth' => $filters['month'],
            'main'         => $main,
        ], 'admin');
    }

    /** GET /admin/appointments/list  (HTMX fragment) */
    public function list(): void
    {
        $filters = $this->extractFilters();
        view('admin/appointments/_list', $this->buildListPayload($filters), null);
    }

    /** GET /admin/appointments/calendar  (HTMX fragment) */
    public function calendarView(): void
    {
        $filters = $this->extractFilters();
        view('admin/appointments/_calendar', $this->buildCalendarPayload($filters), null);
    }

    /** GET /admin/appointments/{id}/panel  (HTMX fragment) */
    public function panel(string $id): void
    {
        $apptId = (int) $id;
        $appt   = Appointment::findById($apptId);
        if ($appt === null) {
            http_response_code(404);
            echo '<div class="panel-empty">Appointment not found.</div>';
            return;
        }

        $gen    = SlotGenerator::fromSettings();
        $tz     = $gen->timezone;
        $starts = new DateTimeImmutable((string) $appt['starts_at'], $tz);
        $ends   = new DateTimeImmutable((string) $appt['ends_at'],   $tz);

        view('admin/appointments/_panel', [
            'appt'      => $appt,
            'starts'    => $starts,
            'ends'      => $ends,
            'reference' => strtoupper(substr((string) $appt['cancel_token'], -8)),
            'tzName'    => $tz->getName(),
        ], null);
    }

    /** GET /admin/appointments/day/{date}  (HTMX fragment) */
    public function dayPanel(string $date): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)) {
            http_response_code(400);
            echo '<div class="panel-empty">Invalid date.</div>';
            return;
        }

        $gen = SlotGenerator::fromSettings();
        $tz  = $gen->timezone;

        $rows = Appointment::listFiltered([
            'date_from' => $date . ' 00:00:00',
            'date_to'   => $date . ' 23:59:59',
            'order'     => 'asc',
        ]);

        view('admin/appointments/_day_panel', [
            'date'      => $date,
            'dateLabel' => (new DateTimeImmutable($date, $tz))->format('l, F j, Y'),
            'rows'      => $rows,
            'tzName'    => $tz->getName(),
        ], null);
    }

    /* ============================================================
     * Mutations
     * ============================================================ */

    /** POST /admin/appointments/{id}/status */
    public function changeStatus(string $id): void
    {
        csrf_check();

        $apptId = (int) $id;
        $status = (string) ($_POST['status'] ?? '');

        if (!in_array($status, Appointment::VALID_STATUSES, true)) {
            http_response_code(400);
            echo 'Invalid status';
            return;
        }

        // Demo mode: block destructive transitions only (cancel). Marking
        // an appointment completed / no-show is fine — it's forward-only.
        if ($status === 'cancelled') {
            demo_block('Cancelling appointments is disabled in demo mode.');
        }

        $appt = Appointment::findById($apptId);
        if ($appt === null) {
            http_response_code(404);
            return;
        }

        // Only mutate + notify when something actually changes.
        if ($appt['status'] !== $status) {
            Appointment::updateStatus($apptId, $status);

            logger()->info('Appointment status changed', [
                'appointment_id' => $apptId,
                'from'           => (string) $appt['status'],
                'to'             => $status,
                'changed_by'     => current_user_id(),
            ]);

            // Customer-facing email + webhook fan-out, scoped by transition.
            if ($status === 'cancelled') {
                Mailer::queueCancellation($apptId);
                WebhookDispatcher::dispatch('booking.cancelled', $apptId);
            } elseif ($status === 'completed') {
                WebhookDispatcher::dispatch('booking.completed', $apptId);
            }
        }

        http_response_code(204);
    }

    /** POST /admin/appointments/{id}/notes */
    public function saveNotes(string $id): void
    {
        csrf_check();

        $apptId = (int) $id;
        $notes  = (string) ($_POST['notes'] ?? '');

        // Schema column is TEXT (65,535 bytes). Clamp generously to protect
        // from accidental paste-of-novel.
        if (mb_strlen($notes) > 5000) {
            $notes = mb_substr($notes, 0, 5000);
        }

        if (Appointment::findById($apptId) === null) {
            http_response_code(404);
            return;
        }

        Appointment::updateNotes($apptId, trim($notes));
        http_response_code(204);
    }

    /** POST /admin/appointments/{id}/resend-confirmation */
    public function resendConfirmation(string $id): void
    {
        csrf_check();

        $apptId = (int) $id;
        if (Appointment::findById($apptId) === null) {
            http_response_code(404);
            return;
        }

        $ok = Mailer::queueConfirmation($apptId);
        http_response_code($ok ? 204 : 502);
    }

    /* ============================================================
     * CSV export
     * ============================================================ */

    /** GET /admin/appointments/export */
    public function export(): void
    {
        $filters = $this->extractFilters();
        // For export we ignore the view's date scoping and respect the
        // user's status/service/search filters across the whole table.
        // Date scoping would surprise admins clicking Export from "Today".
        // If they want today only, status filtering still applies.
        if ($filters['view'] === 'today' || $filters['view'] === 'upcoming') {
            $filters = $this->applyViewDateRange($filters);
        }

        $rows = Appointment::listFiltered($filters);

        $gen = SlotGenerator::fromSettings();
        $tz  = $gen->timezone;

        $filename = 'appointments-' . (new DateTimeImmutable('now', $tz))->format('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            http_response_code(500);
            return;
        }

        // UTF-8 BOM so Excel doesn't mojibake non-ASCII customer names.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'ID', 'Reference', 'Status', 'Customer', 'Email', 'Phone',
            'Service', 'Date', 'Time', 'Duration (min)', 'Price', 'Notes', 'Created',
        ]);

        foreach ($rows as $r) {
            $starts = new DateTimeImmutable((string) $r['starts_at'], $tz);
            fputcsv($out, [
                (int) $r['id'],
                strtoupper(substr((string) $r['cancel_token'], -8)),
                (string) $r['status'],
                (string) $r['customer_name'],
                (string) $r['customer_email'],
                (string) $r['customer_phone'],
                (string) $r['service_name'],
                $starts->format('Y-m-d'),
                $starts->format('H:i'),
                (int) $r['service_duration'],
                (string) $r['service_price'],
                (string) ($r['notes'] ?? ''),
                (string) $r['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    /* ============================================================
     * Internals
     * ============================================================ */

    /**
     * @return array{
     *   view:string, status:string, service_id:int, q:string,
     *   page:int, month:string
     * }
     */
    private function extractFilters(): array
    {
        $view = (string) ($_GET['view'] ?? 'today');
        if (!in_array($view, ['today', 'upcoming', 'all', 'calendar'], true)) {
            $view = 'today';
        }

        $status = (string) ($_GET['status'] ?? 'all');
        if ($status !== 'all' && !in_array($status, Appointment::VALID_STATUSES, true)) {
            $status = 'all';
        }

        $month = (string) ($_GET['month'] ?? '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $tz    = SlotGenerator::fromSettings()->timezone;
            $month = (new DateTimeImmutable('now', $tz))->format('Y-m');
        }

        return [
            'view'       => $view,
            'status'     => $status,
            'service_id' => (int) ($_GET['service_id'] ?? 0),
            'q'          => trim((string) ($_GET['q'] ?? '')),
            'page'       => max(1, (int) ($_GET['page'] ?? 1)),
            'month'      => $month,
        ];
    }

    /**
     * Decorate filters with a concrete date_from / date_to range based on
     * the active view. Computed in the business timezone so "today" matches
     * the booking calendar's idea of today.
     *
     * @param  array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function applyViewDateRange(array $filters): array
    {
        $tz  = SlotGenerator::fromSettings()->timezone;
        $now = new DateTimeImmutable('now', $tz);

        if ($filters['view'] === 'today') {
            $filters['date_from'] = $now->format('Y-m-d') . ' 00:00:00';
            $filters['date_to']   = $now->format('Y-m-d') . ' 23:59:59';
        } elseif ($filters['view'] === 'upcoming') {
            $filters['date_from'] = $now->format('Y-m-d H:i:s');
            $filters['date_to']   = $now->add(new DateInterval('P7D'))->format('Y-m-d H:i:s');
        }
        return $filters;
    }

    /**
     * @param  array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function buildListPayload(array $filters): array
    {
        $filters = $this->applyViewDateRange($filters);

        // 'all' shows newest-first; everything else is chronological.
        $filters['order'] = $filters['view'] === 'all' ? 'desc' : 'asc';

        $total = Appointment::countFiltered($filters);
        $rows  = Appointment::listFiltered(
            $filters,
            limit:  self::PER_PAGE,
            offset: ($filters['page'] - 1) * self::PER_PAGE,
        );
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $tz = SlotGenerator::fromSettings()->timezone;

        return [
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $filters['page'],
            'totalPages' => $totalPages,
            'view'       => $filters['view'],
            'filters'    => $filters,
            'tzName'     => $tz->getName(),
        ];
    }

    /**
     * @param  array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function buildCalendarPayload(array $filters): array
    {
        $tz    = SlotGenerator::fromSettings()->timezone;
        $month = (string) $filters['month'];
        $first = new DateTimeImmutable("{$month}-01", $tz);

        $weekday   = (int) $first->format('w');
        $gridStart = $first->modify("-{$weekday} days");

        $cells = [];
        for ($i = 0; $i < 42; $i++) {
            $cells[] = $gridStart->modify("+{$i} days");
        }

        // Fetch all appointments visible in the grid, respecting filter chips.
        $rangeStart = $cells[0]->format('Y-m-d')  . ' 00:00:00';
        $rangeEnd   = $cells[41]->format('Y-m-d') . ' 23:59:59';

        $rows = Appointment::listFiltered([
            'status'     => $filters['status'],
            'service_id' => $filters['service_id'],
            'q'          => $filters['q'],
            'date_from'  => $rangeStart,
            'date_to'    => $rangeEnd,
            'order'      => 'asc',
        ]);

        $byDate = [];
        foreach ($rows as $r) {
            $d = substr((string) $r['starts_at'], 0, 10);
            $byDate[$d][] = $r;
        }

        return [
            'month'      => $month,
            'monthLabel' => $first->format('F Y'),
            'prevMonth'  => $first->modify('-1 month')->format('Y-m'),
            'nextMonth'  => $first->modify('+1 month')->format('Y-m'),
            'cells'      => $cells,
            'byDate'     => $byDate,
            'today'      => (new DateTimeImmutable('now', $tz))->format('Y-m-d'),
            'filters'    => $filters,
            'view'       => 'calendar',
            'tzName'     => $tz->getName(),
        ];
    }
}
