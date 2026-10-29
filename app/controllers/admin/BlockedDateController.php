<?php
/**
 * app/controllers/admin/BlockedDateController.php
 *
 * Calendar of one-off blocked days. Clicking a date toggles it; the list
 * below the calendar lets the admin delete an individual entry.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BlockedDate;
use DateTimeImmutable;

final class BlockedDateController
{
    public function __construct()
    {
        require_admin();
    }

    /** GET /admin/blocked-dates?month=YYYY-MM */
    public function index(): void
    {
        $month = (string) ($_GET['month'] ?? now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }
        $first = new DateTimeImmutable("{$month}-01");

        // Build a 6 x 7 grid starting on the Sunday on/before the 1st.
        $weekday   = (int) $first->format('w'); // 0 = Sun
        $gridStart = $first->modify('-' . $weekday . ' days');
        $cells     = [];
        for ($i = 0; $i < 42; $i++) {
            $cells[] = $gridStart->modify("+{$i} days");
        }

        view('admin/blocked_dates', [
            'title'      => 'Blocked dates — Slate Admin',
            'active'     => 'blocked',
            'month'      => $month,
            'monthLabel' => $first->format('F Y'),
            'prevMonth'  => $first->modify('-1 month')->format('Y-m'),
            'nextMonth'  => $first->modify('+1 month')->format('Y-m'),
            'firstOfMonth' => $first->format('Y-m-d'),
            'cells'      => $cells,
            'blocked'    => BlockedDate::map(),
            'blockedList' => BlockedDate::all(),
            'today'      => now()->format('Y-m-d'),
        ], 'admin');
    }

    /** POST /admin/blocked-dates/toggle */
    public function toggle(): void
    {
        csrf_check();

        $date   = (string) ($_POST['date']   ?? '');
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: null;
        $month  = (string) ($_POST['month'] ?? now()->format('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || !checkdate(
                (int) substr($date, 5, 2),
                (int) substr($date, 8, 2),
                (int) substr($date, 0, 4),
            )
        ) {
            flash('error', 'Invalid date.');
            redirect('/admin/blocked-dates');
        }

        if (BlockedDate::findByDate($date) !== null) {
            BlockedDate::deleteByDate($date);
            flash('success', "Unblocked {$date}.");
        } else {
            BlockedDate::create($date, $reason);
            flash('success', "Blocked {$date}.");
        }

        redirect('/admin/blocked-dates?month=' . urlencode($month));
    }

    /** POST /admin/blocked-dates/{id}/delete */
    public function destroy(string $id): void
    {
        csrf_check();
        $rowId = (int) $id;
        if ($rowId > 0) {
            BlockedDate::deleteById($rowId);
            flash('success', 'Date unblocked.');
        }
        redirect('/admin/blocked-dates');
    }
}
