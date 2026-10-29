<?php
/**
 * app/models/AppointmentSeries.php
 *
 * Recurring booking rules. A series describes the recurrence (frequency,
 * weekday, time, duration); actual bookings live in `appointments` with
 * `series_id` set, materialized ahead of time by
 * app/jobs/ExpandRecurringSeries.php.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class AppointmentSeries
{
    public const VALID_FREQS = ['weekly', 'biweekly', 'monthly'];

    /** @param array<string,scalar|null> $data */
    public static function create(array $data): int
    {
        return (int) Database::getInstance()->insert('appointment_series', $data);
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT * FROM appointment_series WHERE id = :id LIMIT 1',
            ['id' => $id],
        );
    }

    /** @return list<array<string,mixed>> */
    public static function active(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM appointment_series WHERE status = 'active'",
        );
    }

    /** Stops future expansion. Does not touch already-materialized rows. */
    public static function cancel(int $id): int
    {
        return Database::getInstance()->update(
            table: 'appointment_series',
            data:  ['status' => 'cancelled'],
            where: ['id' => $id],
        );
    }
}
