<?php
/**
 * config/mail.php
 *
 * SMTP settings for PHPMailer (confirmation + reminder emails).
 */

declare(strict_types=1);

return [
    'host'         => $_ENV['MAIL_HOST']         ?? 'localhost',
    'port'         => (int) ($_ENV['MAIL_PORT'] ?? 587),
    'username'     => $_ENV['MAIL_USERNAME']     ?? '',
    'password'     => $_ENV['MAIL_PASSWORD']     ?? '',
    'encryption'   => $_ENV['MAIL_ENCRYPTION']   ?? 'tls',  // 'tls' | 'ssl' | ''
    'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com',
    'from_name'    => $_ENV['MAIL_FROM_NAME']    ?? 'Slate',
];
