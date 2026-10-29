<?php
// app/jobs/ExpandRecurringSeries.php
//
// Materializes upcoming `appointments` rows for every active
// `appointment_series`, out to `advance_booking_days` (the same setting
// the booking calendar uses). Idempotent: a (series_id, date) pair that
// already has an appointments row is skipped, so it's safe to run this as
// often as you like — including right after a slot conflict, which just
// leaves a gap for that one occurrence rather than retrying forever.
//
// =============================================================
//  SETUP — cron
// =============================================================
//
//  Linux / macOS (preferred — install crontab once):
//
//    crontab -e
//
//    # Run once an hour — recurring bookings don't need 5-minute freshness:
//    0 * * * * /usr/bin/php /var/www/slate/app/jobs/ExpandRecurringSeries.php >> /var/log/slate-recurring.log 2>&1
//
//  Windows:
//
//    Task Scheduler → "Create Basic Task" → trigger hourly →
//    Action:  php.exe  C:\xampp\htdocs\slate\app\jobs\ExpandRecurringSeries.php
//
//  Shared hosting WITHOUT shell cron:
//
//    1. Add  JOBS_KEY=somesecret123  to your .env file.
//    2. Drop this route into public/index.php:
//
//         $router->get('/jobs/expand-recurring', function () {
//             if (!hash_equals((string) env('JOBS_KEY', ''), (string) ($_GET['key'] ?? ''))) {
//                 http_response_code(403); echo 'forbidden'; return;
//             }
//             require base_path('app/jobs/ExpandRecurringSeries.php');
//         });
//
//    3. Sign up at https://cron-job.org (free) and have it GET
//         https://yourdomain.tld/jobs/expand-recurring?key=somesecret123
//       every hour.
//
// Known limitation: monthly recurrence steps by calendar month
// (Carbon::addMonthNoOverflow()), so a series starting on the 31st lands on
// the last day of shorter months rather than skipping or rolling over.

declare(strict_types=1);

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

use App\Core\Database;
use App\Models\AppointmentSeries;
use App\Models\Setting;
use App\Services\AppointmentBooker;
use App\Services\Mailer;
use App\Services\RecurrenceExpander;
use Carbon\CarbonImmutable;

$tz          = new \DateTimeZone((string) (Setting::get('timezone', 'Asia/Manila') ?? 'Asia/Manila'));
$advanceDays = (int) (Setting::get('advance_booking_days', '60') ?? 60);
$today       = CarbonImmutable::now($tz)->startOfDay();
$horizon     = CarbonImmutable::now($tz)->addDays($advanceDays);

$series = AppointmentSeries::active();

$cliMode = PHP_SAPI === 'cli';
$out     = static function (string $line) use ($cliMode): void {
    echo $cliMode ? $line . "\n" : '<div>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
};

$out(sprintf('[%s] ExpandRecurringSeries: %d active series', date('Y-m-d H:i:s'), count($series)));

$created = 0;
$skipped = 0;
$failed  = 0;

foreach ($series as $s) {
    $seriesId = (int) $s['id'];
    try {
        $dueDates = RecurrenceExpander::datesFor($s, $tz, $today, $horizon);

        foreach ($dueDates as $dateStr) {
            $exists = (int) Database::getInstance()->fetchColumn(
                'SELECT COUNT(*) FROM appointments WHERE series_id = :sid AND DATE(starts_at) = :d',
                ['sid' => $seriesId, 'd' => $dateStr],
            );
            if ($exists > 0) {
                $skipped++;
                continue;
            }

            $startsAt = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $s['start_time'], $tz);
            $endsAt   = $startsAt->addMinutes((int) $s['duration_minutes']);

            $result = AppointmentBooker::book([
                'service_id'     => (int) $s['service_id'],
                'customer_name'  => (string) $s['customer_name'],
                'customer_email' => (string) $s['customer_email'],
                'customer_phone' => $s['customer_phone'],
                'starts_at'      => $startsAt->format('Y-m-d H:i:s'),
                'ends_at'        => $endsAt->format('Y-m-d H:i:s'),
                'notes'          => $s['notes'],
                'series_id'      => $seriesId,
            ]);

            if ($result['ok']) {
                $created++;
                Mailer::queueConfirmation((int) $result['id']);
                $out("  [OK]   series #{$seriesId} -> appointment #{$result['id']} on {$dateStr}");
            } else {
                $skipped++;
                $out("  [SKIP] series #{$seriesId} on {$dateStr} — slot conflict, leaving a gap this cycle");
            }
        }
    } catch (\Throwable $e) {
        $failed++;
        $out("  [FAIL] series #{$seriesId}: " . $e->getMessage());
    }
}

$out(sprintf('Done — created=%d skipped=%d failed=%d', $created, $skipped, $failed));
