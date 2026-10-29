<?php
// app/jobs/ProcessWebhooks.php
//
// Drains the webhook_queue: picks up rows due for delivery, POSTs them
// with an HMAC signature, retries with exponential backoff on failure.
//
// =============================================================
//  SETUP — cron
// =============================================================
//
//  Linux / macOS — run every minute:
//
//    * * * * * /usr/bin/php /var/www/slate/app/jobs/ProcessWebhooks.php >> /var/log/slate-webhooks.log 2>&1
//
//  Windows Task Scheduler:
//
//    Trigger: Daily, repeat every 1 minute
//    Action:  php.exe  C:\xampp\htdocs\slate\app\jobs\ProcessWebhooks.php
//
//  Shared hosting via HTTP — see DEPLOYMENT.md for the cron-job.org pattern.

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require BASE_PATH . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

$app = require BASE_PATH . '/config/app.php';
$db  = require BASE_PATH . '/config/db.php';
date_default_timezone_set((string) $app['timezone']);

\App\Core\Database::init($db);
\App\Core\Logger::init(BASE_PATH . '/storage/logs', ((string) $app['env']) === 'production');

use App\Models\Webhook;
use App\Services\WebhookDispatcher;

$cli = PHP_SAPI === 'cli';
$out = static function (string $line) use ($cli): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $line;
    echo $cli ? $line . "\n" : '<div>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
};

$pending = Webhook::dueQueueRows(50);
$out(sprintf('ProcessWebhooks: %d pending', count($pending)));

$sent = $failed = $abandoned = 0;

foreach ($pending as $row) {
    $queueId   = (int) $row['id'];
    $webhookId = (int) $row['webhook_id'];
    $event     = (string) $row['event'];
    $url       = (string) $row['url'];
    $secret    = (string) $row['secret'];
    $payload   = (string) $row['payload'];
    $attempt   = ((int) $row['attempt_count']) + 1;

    [$ok, $status, $errMsg] = WebhookDispatcher::deliver($url, $secret, $payload);

    if ($ok) {
        Webhook::markQueueDelivered($queueId);
        Webhook::recordTriggered($webhookId, success: true);
        $sent++;
        $out(sprintf('  [OK]   #%d → %s (event=%s, http=%d)', $queueId, $url, $event, $status));
        continue;
    }

    Webhook::recordTriggered($webhookId, success: false, error: $errMsg);

    if ($attempt >= WebhookDispatcher::MAX_ATTEMPTS) {
        Webhook::markQueueAbandoned($queueId, (string) $errMsg);
        $abandoned++;
        $out(sprintf('  [GIVE] #%d → %s abandoned after %d attempts: %s',
            $queueId, $url, $attempt, (string) $errMsg));
        continue;
    }

    $delay = WebhookDispatcher::BACKOFF[$attempt] ?? 1800;
    Webhook::markQueueFailed($queueId, $attempt, $delay, (string) $errMsg);
    $failed++;
    $out(sprintf('  [RETRY] #%d → %s attempt %d failed, next in %ds: %s',
        $queueId, $url, $attempt, $delay, (string) $errMsg));
}

$out(sprintf('Done — sent=%d retry=%d abandoned=%d', $sent, $failed, $abandoned));
