<?php
/**
 * tests/bootstrap.php
 *
 * Boots the application in test mode:
 *   - Points the DB at slate_test instead of slate
 *   - Sets APP_ENV=testing, MAIL_DRIVER=log
 *   - Defines APP_TESTING so redirect() / RateLimit::reject() throw instead of exit()
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// Define APP_TESTING BEFORE any helper code runs so redirect() picks it up.
define('APP_TESTING', true);

// Force testing values into $_ENV before dotenv loads. createImmutable()
// won't overwrite an already-set key, so these win even if .env has them.
$_ENV['APP_ENV']      = 'testing';
$_ENV['APP_DEBUG']    = 'false';
$_ENV['APP_NAME']     = 'Slate Test';
$_ENV['APP_URL']      = $_ENV['APP_URL']      ?? 'http://localhost:8000';
$_ENV['APP_TIMEZONE'] = $_ENV['APP_TIMEZONE'] ?? 'America/Toronto';
$_ENV['DB_DATABASE']  = $_ENV['DB_DATABASE']  ?? 'slate_test';
$_ENV['DB_USERNAME']  = $_ENV['DB_USERNAME']  ?? 'root';
$_ENV['DB_PASSWORD']  = $_ENV['DB_PASSWORD']  ?? '';
$_ENV['DB_HOST']      = $_ENV['DB_HOST']      ?? '127.0.0.1';
$_ENV['DB_PORT']      = $_ENV['DB_PORT']      ?? '3306';
$_ENV['MAIL_DRIVER']  = 'log';

// Load .env if present — won't overwrite the values above.
$dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

// Belt-and-braces: re-assert critical overrides post-dotenv.
$_ENV['APP_ENV']     = 'testing';
$_ENV['MAIL_DRIVER'] = 'log';

date_default_timezone_set((string) $_ENV['APP_TIMEZONE']);

// Hand-build the DB config so we definitely point at slate_test.
$dbConfig = [
    'host'     => (string) $_ENV['DB_HOST'],
    'port'     => (int)    $_ENV['DB_PORT'],
    'database' => (string) $_ENV['DB_DATABASE'],
    'username' => (string) $_ENV['DB_USERNAME'],
    'password' => (string) $_ENV['DB_PASSWORD'],
    'charset'  => 'utf8mb4',
];
\App\Core\Database::init($dbConfig);

// Logger writes go to /storage/logs but tests rarely care about them.
\App\Core\Logger::init(BASE_PATH . '/storage/logs', false);

// Quick smoke check: tables we expect to TRUNCATE on every setUp() must exist.
try {
    \App\Core\Database::getInstance()->query('SELECT 1 FROM users LIMIT 1');
} catch (\Throwable $e) {
    fwrite(STDERR, "\n[tests/bootstrap.php] Cannot reach slate_test schema.\n");
    fwrite(STDERR, "   Create the test database and import the schema first:\n\n");
    fwrite(STDERR, "       CREATE DATABASE slate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
    fwrite(STDERR, "       mysql -u root slate_test < database/schema.sql\n\n");
    fwrite(STDERR, '   Underlying error: ' . $e->getMessage() . "\n\n");
    exit(1);
}
