<?php
/**
 * app/models/ApiToken.php
 *
 * Bearer tokens for the admin API. Plain tokens are shown to the
 * user exactly once on creation; only the SHA-256 hash is stored.
 *
 * Token format: "slate_<48 hex chars>" — the "slate_" prefix makes
 * leaked tokens easy to grep for in logs / git history.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ApiToken
{
    public const PREFIX = 'slate_';

    /** @return list<array<string,mixed>> */
    public static function listForUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT id, name, token_prefix, last_used_at, created_at
               FROM api_tokens
              WHERE user_id = :uid
           ORDER BY created_at DESC",
            ['uid' => $userId],
        );
    }

    /**
     * Generate a fresh token and persist its hash.
     *
     * @return array{plain:string, id:int} The plain token (show ONCE)
     *         plus its db id.
     */
    public static function generate(int $userId, string $name): array
    {
        $plain  = self::PREFIX . bin2hex(random_bytes(24));   // 6 + 48 chars
        $prefix = substr($plain, 0, 14);                      // for display lookup
        $hash   = hash('sha256', $plain);

        $id = (int) Database::getInstance()->insert('api_tokens', [
            'user_id'      => $userId,
            'name'         => $name,
            'token_prefix' => $prefix,
            'token_hash'   => $hash,
        ]);
        return ['plain' => $plain, 'id' => $id];
    }

    /** @return array<string,mixed>|null */
    public static function findByPlainToken(string $plain): ?array
    {
        if (!str_starts_with($plain, self::PREFIX)) {
            return null;
        }
        $hash = hash('sha256', $plain);
        return Database::getInstance()->fetch(
            "SELECT t.id, t.user_id, t.name, t.token_prefix, t.last_used_at, t.created_at,
                    u.role  AS user_role,
                    u.name  AS user_name,
                    u.email AS user_email,
                    u.is_super
               FROM api_tokens t
               JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = :hash
              LIMIT 1",
            ['hash' => $hash],
        );
    }

    public static function touch(int $id): void
    {
        Database::getInstance()->query(
            'UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id',
            ['id' => $id],
        );
    }

    public static function revoke(int $id, int $userId): int
    {
        return Database::getInstance()->query(
            'DELETE FROM api_tokens WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId],
        )->rowCount();
    }
}
