<?php
/**
 * app/models/User.php
 *
 * Thin data-access wrapper for the `users` table. Every query uses
 * prepared statements via App\Core\Database — no string interpolation.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Role enum keeps controllers honest. */
enum UserRole: string
{
    case Admin    = 'admin';
    case Customer = 'customer';
}

final class User
{
    /**
     * @return array<string,mixed>|null
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT id, name, email, password_hash, role, is_super, created_at
               FROM users WHERE id = :id LIMIT 1',
            ['id' => $id],
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT id, name, email, password_hash, role, is_super, created_at
               FROM users WHERE email = :email LIMIT 1',
            ['email' => $email],
        );
    }

    /** Create a user and return its new id. */
    public static function create(
        string $name,
        string $email,
        string $passwordHash,
        UserRole $role = UserRole::Customer,
    ): int {
        $id = Database::getInstance()->insert('users', [
            'name'          => $name,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'role'          => $role->value,
        ]);
        return (int) $id;
    }

    /** Rehash a user's password (used when the algorithm/cost changes). */
    public static function updatePasswordHash(int $id, string $passwordHash): void
    {
        Database::getInstance()->update(
            table: 'users',
            data:  ['password_hash' => $passwordHash],
            where: ['id' => $id],
        );
    }
}
