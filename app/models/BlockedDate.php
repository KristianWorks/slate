<?php
/**
 * app/models/BlockedDate.php
 *
 * One-off date closures (holidays, vacations, time off).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class BlockedDate
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT id, date, reason, created_at FROM blocked_dates ORDER BY date ASC',
        );
    }

    /**
     * Lookup map of "YYYY-MM-DD" => row, suitable for O(1) calendar checks.
     *
     * @return array<string, array<string,mixed>>
     */
    public static function map(): array
    {
        $out = [];
        foreach (self::all() as $row) {
            $out[(string) $row['date']] = $row;
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public static function findByDate(string $date): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT id, date, reason, created_at FROM blocked_dates WHERE date = :date LIMIT 1',
            ['date' => $date],
        );
    }

    public static function create(string $date, ?string $reason): int
    {
        return (int) Database::getInstance()->insert('blocked_dates', [
            'date'   => $date,
            'reason' => $reason,
        ]);
    }

    public static function deleteByDate(string $date): int
    {
        return Database::getInstance()->query(
            'DELETE FROM blocked_dates WHERE date = :date',
            ['date' => $date],
        )->rowCount();
    }

    public static function deleteById(int $id): int
    {
        return Database::getInstance()->query(
            'DELETE FROM blocked_dates WHERE id = :id',
            ['id' => $id],
        )->rowCount();
    }
}
