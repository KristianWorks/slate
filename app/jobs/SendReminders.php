<?php
// app/jobs/SendReminders.php
//
// Sends a "tomorrow" reminder for every confirmed appointment that starts
// roughly 24 hours from now. Eligible rows:
//
//   - status = 'confirmed'
//   - starts_at BETWEEN now+23h AND now+25h
//   - no email_log row of type='reminder' for that appointment yet
//     (so a successful send is the idempotency key — failed sends will
//     simply be retried on the next cron tick)
//
// =============================================================
//  SETUP — cron
// =============================================================
//
//  Linux / macOS (preferred — install crontab once):
//
//    crontab -e
//
//    # Run every 5 minutes:
//    */5 * * * * /usr/bin/php /var/www/slate/app/jobs/SendReminders.php >> /var/log/slate-reminders.log 2>&1
//
//  Windows:
//
//    Use Task Scheduler → "Create Basic Task" → trigger every 5 minutes →
//    Action:  php.exe  C:\xampp\htdocs\slate\app\jobs\SendReminders.php
//
//  Shared hosting WITHOUT shell cron:
//
//    1. Add  JOBS_KEY=somesecret123  to your .env file.
//    2. Drop this route into public/index.php:
//
//         $router->get('/jobs/reminders', function () {
//             if (!hash_equals((string) env('JOBS_KEY', ''), (string) ($_GET['key'] ?? ''))) {
//                 http_response_code(403); echo 'forbidden'; return;
//             }
//             require base_path('app/jobs/SendReminders.php');
//         });
//
//    3. Sign up at https://cron-job.org (free) and have it GET
//         https://yourdomain.tld/jobs/reminders?key=somesecret123
//       every 5 minutes.
//
// The job is safe to run more often than necessary — the email_log
// de-duplication guarantees a customer is only reminded once.

declare(strict_types=1);

// ---- Bootstrap (the bits public/index.php does, minus session + routing) ----

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require BASE_PATH . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

/** @var array<string,mixed> $app */
$app = require BASE_PATH . '/config/app.php';
/** @var array<string,mixed> $db  */
$db  = require BASE_PATH . '/config/db.php';

date_default_timezone_set((string) $app['timezone']);

\App\Core\Database::init($db);

// ---- Find eligible reminders ----

$rows = \App\Core\Database::getInstance()->fetchAll(
    "SELECT a.id
       FROM appointments a
  LEFT JOIN email_log el
         ON el.appointment_id = a.id
        AND el.type = 'reminder'
      WHERE a.status = 'confirmed'
        AND a.starts_at >= DATE_ADD(NOW(), INTERVAL 23 HOUR)
        AND a.starts_at <  DATE_ADD(NOW(), INTERVAL 25 HOUR)
        AND el.id IS NULL"
);

$cliMode = PHP_SAPI === 'cli';
$out     = static function (string $line) use ($cliMode): void {
    echo $cliMode ? $line . "\n" : '<div>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
};

$out(sprintf('[%s] SendReminders: %d candidate(s)', date('Y-m-d H:i:s'), count($rows)));

$sent   = 0;
$failed = 0;
foreach ($rows as $row) {
    $apptId = (int) $row['id'];
    try {
        $ok = \App\Services\Mailer::queueReminder($apptId);
        if ($ok) {
            $sent++;
            $out("  [OK]   reminder sent for appt #{$apptId}");
        } else {
            $failed++;
            $out("  [FAIL] mailer returned false for appt #{$apptId} — will retry next tick");
        }
    } catch (\Throwable $e) {
        $failed++;
        $out("  [FAIL] appt #{$apptId}: " . $e->getMessage());
    }
}

$out(sprintf('Done — sent=%d failed=%d total=%d', $sent, $failed, count($rows)));
