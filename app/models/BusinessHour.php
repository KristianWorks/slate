<?php
/**
 * app/models/BusinessHour.php
 *
 * Weekly recurring open/close hours, one row per weekday (0 = Sunday).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class BusinessHour
{
    /**
     * Return all seven days, keyed by day_of_week.
     * Missing rows are filled in as "closed" so the view can render a full week.
     *
     * @return array<int, array{day_of_week:int, open_time:?string, close_time:?string, is_closed:int}>
     */
    public static function week(): array
    {
        $rows = Database::getInstance()->fetchAll(
            'SELECT day_of_week, open_time, close_time, is_closed
               FROM business_hours
              ORDER BY day_of_week ASC',
        );

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(int) $r['day_of_week']] = [
                'day_of_week' => (int) $r['day_of_week'],
                'open_time'   => $r['open_time']  !== null ? substr((string) $r['open_time'], 0, 5)  : null,
                'close_time'  => $r['close_time'] !== null ? substr((string) $r['close_time'], 0, 5) : null,
                'is_closed'   => (int) $r['is_closed'],
            ];
        }

        for ($d = 0; $d <= 6; $d++) {
            $byDay[$d] ??= [
                'day_of_week' => $d,
                'open_time'   => null,
                'close_time'  => null,
                'is_closed'   => 1,
            ];
        }
        ksort($byDay);
        return $byDay;
    }

    /**
     * Replace the entire week atomically.
     *
     * @param list<array{day_of_week:int, open_time:?string, close_time:?string, is_closed:int}> $rows
     */
    public static function saveWeek(array $rows): void
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->query('DELETE FROM business_hours');
            foreach ($rows as $row) {
                $db->insert('business_hours', [
                    'day_of_week' => $row['day_of_week'],
                    'open_time'   => $row['is_closed'] ? null : $row['open_time'],
                    'close_time'  => $row['is_closed'] ? null : $row['close_time'],
                    'is_closed'   => $row['is_closed'] ? 1 : 0,
                ]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
