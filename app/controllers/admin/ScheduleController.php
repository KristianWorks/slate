<?php
/**
 * app/controllers/admin/ScheduleController.php
 *
 * Edit the weekly recurring business hours. All seven days are saved in a
 * single submission (atomic).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BusinessHour;

final class ScheduleController
{
    public function __construct()
    {
        require_admin();
    }

    /** GET /admin/schedule */
    public function index(): void
    {
        view('admin/schedule', [
            'title'   => 'Schedule — Slate Admin',
            'active'  => 'schedule',
            'hours'   => BusinessHour::week(),
            'today'   => (int) now()->dayOfWeek, // Carbon: 0=Sunday
        ], 'admin');
    }

    /** POST /admin/schedule */
    public function save(): void
    {
        csrf_check();
        demo_block('Changing business hours is disabled in demo mode.');

        /** @var array<int,array<string,string>> $days */
        $days   = $_POST['days'] ?? [];
        $errors = [];
        $rows   = [];

        for ($d = 0; $d <= 6; $d++) {
            $closed    = !empty($days[$d]['is_closed']);
            $openRaw   = (string) ($days[$d]['open_time']  ?? '');
            $closeRaw  = (string) ($days[$d]['close_time'] ?? '');

            $open  = $this->normaliseTime($openRaw);
            $close = $this->normaliseTime($closeRaw);

            if (!$closed) {
                if ($open === null)  { $errors["{$d}.open_time"]  = 'Open time is required.'; }
                if ($close === null) { $errors["{$d}.close_time"] = 'Close time is required.'; }
                if ($open !== null && $close !== null && $open >= $close) {
                    $errors["{$d}.close_time"] = 'Close time must be after open time.';
                }
            }

            $rows[] = [
                'day_of_week' => $d,
                'open_time'   => $open,
                'close_time'  => $close,
                'is_closed'   => $closed ? 1 : 0,
            ];
        }

        if ($errors !== []) {
            errors_set($errors);
            old_set(['days' => $days]);
            flash('error', 'Please fix the errors in the highlighted rows.');
            redirect('/admin/schedule');
        }

        BusinessHour::saveWeek($rows);
        flash('success', 'Schedule updated.');
        redirect('/admin/schedule');
    }

    /** Accepts "HH:MM" or "HH:MM:SS"; returns "HH:MM:SS" or null. */
    private function normaliseTime(string $raw): ?string
    {
        if ($raw === '') return null;
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $raw, $m)) return null;
        return sprintf('%s:%s:%s', $m[1], $m[2], $m[4] ?? '00');
    }
}
