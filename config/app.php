<?php
/**
 * config/app.php
 *
 * Application-level configuration, populated from `.env`.
 */

declare(strict_types=1);

return [
    'name'      => $_ENV['APP_NAME']     ?? 'Slate',
    'env'       => $_ENV['APP_ENV']      ?? 'production',
    'url'       => $_ENV['APP_URL']      ?? 'http://localhost',
    'debug'     => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'timezone'  => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    'demo_mode' => filter_var($_ENV['DEMO_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
