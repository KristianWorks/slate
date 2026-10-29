<?php
/**
 * app/controllers/api/BusinessHoursController.php
 *
 * GET /api/v1/business-hours — weekly hours + timezone + booking rules.
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\BusinessHour;
use App\Models\Setting;
use App\Services\SlotGenerator;

final class BusinessHoursController extends ApiController
{
    private const DAY_NAMES = [
        0 => 'sunday',    1 => 'monday',  2 => 'tuesday', 3 => 'wednesday',
        4 => 'thursday',  5 => 'friday',  6 => 'saturday',
    ];

    public function index(): void
    {
        $this->applyPublicRateLimit('business_hours');

        $week = [];
        foreach (BusinessHour::week() as $dow => $row) {
            $week[] = [
                'day_of_week' => (int) $dow,
                'name'        => self::DAY_NAMES[$dow],
                'is_closed'   => ((int) $row['is_closed']) === 1,
                'open_time'   => $row['open_time'],
                'close_time'  => $row['close_time'],
            ];
        }

        $gen = SlotGenerator::fromSettings();

        $this->ok([
            'timezone'             => $gen->timezone->getName(),
            'business_name'        => (string) (Setting::get('business_name', 'Slate') ?? 'Slate'),
            'location'             => (string) (Setting::get('location') ?? ''),
            'buffer_minutes'       => $gen->bufferMinutes,
            'min_notice_hours'     => $gen->minNoticeHours,
            'advance_booking_days' => $gen->advanceBookingDays,
            'week'                 => $week,
        ]);
    }
}
