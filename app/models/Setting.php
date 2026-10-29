<?php
/**
 * app/models/Setting.php
 *
 * Simple key/value store backed by the `settings` table.
 * Note: `key` is reserved in MySQL — always backticked in SQL.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Setting
{
    /** @return array<string,string> */
    public static function all(): array
    {
        $rows = Database::getInstance()->fetchAll('SELECT `key`, `value` FROM `settings`');
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['key']] = (string) ($row['value'] ?? '');
        }
        return $out;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Database::getInstance()->fetchColumn(
            'SELECT `value` FROM `settings` WHERE `key` = :key LIMIT 1',
            ['key' => $key],
        );
        return $value === false || $value === null ? $default : (string) $value;
    }

    /** Upsert a single key. */
    public static function set(string $key, ?string $value): void
    {
        Database::getInstance()->query(
            'INSERT INTO `settings` (`key`, `value`) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            ['key' => $key, 'value' => $value],
        );
    }

    /**
     * Upsert several keys atomically.
     *
     * @param array<string,?string> $pairs
     */
    public static function setMany(array $pairs): void
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            foreach ($pairs as $k => $v) {
                self::set($k, $v);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
