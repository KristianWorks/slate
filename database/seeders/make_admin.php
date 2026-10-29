<?php
/**
 * database/seeders/make_admin.php
 *
 * CLI helper. Prints an Argon2id hash for pasting into seed.sql, or for
 * piping straight into an UPDATE.
 *
 * Usage:
 *   php database/seeders/make_admin.php                # uses default "admin123"
 *   php database/seeders/make_admin.php "P@ssw0rd!"
 */

declare(strict_types=1);

$password = $argv[1] ?? 'admin123';
$hash     = password_hash($password, PASSWORD_ARGON2ID);

echo "Password: {$password}\n";
echo "Hash:     {$hash}\n\n";
echo "-- Paste into seed.sql, or run directly:\n";
echo "UPDATE users SET password_hash = '{$hash}' WHERE email = 'admin@slate.test';\n";
