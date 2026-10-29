<?php
/**
 * app/models/Service.php
 *
 * Bookable services (length, price, brand colour). Prepared-statement only.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

final class Service
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT id, name, description, duration_minutes, price, color, is_active, created_at
               FROM services
              ORDER BY is_active DESC, name ASC',
        );
    }

    /** @return list<array<string,mixed>> */
    public static function active(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT id, name, description, duration_minutes, price, color
               FROM services
              WHERE is_active = 1
              ORDER BY name ASC',
        );
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT id, name, description, duration_minutes, price, color, is_active, created_at
               FROM services WHERE id = :id LIMIT 1',
            ['id' => $id],
        );
    }

    /** @param array<string,scalar|null> $data */
    public static function create(array $data): int
    {
        return (int) Database::getInstance()->insert('services', $data);
    }

    /** @param array<string,scalar|null> $data */
    public static function update(int $id, array $data): int
    {
        return Database::getInstance()->update('services', $data, ['id' => $id]);
    }

    public static function toggleActive(int $id): void
    {
        Database::getInstance()->query(
            'UPDATE services SET is_active = 1 - is_active WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Try a hard delete. Falls back to soft-delete (is_active = 0) when the
     * FK constraint blocks removal because appointments reference this service.
     *
     * @return 'deleted'|'deactivated'
     */
    public static function deleteOrDeactivate(int $id): string
    {
        try {
            Database::getInstance()->query('DELETE FROM services WHERE id = :id', ['id' => $id]);
            return 'deleted';
        } catch (PDOException $e) {
            // SQLSTATE 23000 — integrity constraint violation (FK).
            if ($e->getCode() === '23000') {
                Database::getInstance()->update('services', ['is_active' => 0], ['id' => $id]);
                return 'deactivated';
            }
            throw $e;
        }
    }
}
