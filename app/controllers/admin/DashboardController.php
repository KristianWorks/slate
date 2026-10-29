<?php
/**
 * app/controllers/admin/DashboardController.php
 *
 * Admin landing page. Aggregates light-weight stats for the cards on /admin.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Appointment;

final class DashboardController
{
    public function __construct()
    {
        require_admin();
    }

    public function index(): void
    {
        $today      = Appointment::countToday();
        $weekStart  = now()->startOfWeek()->toDateTimeString();
        $weekEnd    = now()->endOfWeek()->addSecond()->toDateTimeString();
        $monthStart = now()->startOfMonth()->toDateTimeString();
        $monthEnd   = now()->endOfMonth()->addSecond()->toDateTimeString();

        view('admin/dashboard', [
            'title'         => 'Dashboard — Slate Admin',
            'active'        => 'dashboard',
            'todayCount'    => $today,
            'weekCount'     => Appointment::countThisWeek($weekStart, $weekEnd),
            'monthRevenue'  => Appointment::revenueBetween($monthStart, $monthEnd),
            'nextUpcoming'  => Appointment::nextUpcoming(),
        ], 'admin');
    }
}
