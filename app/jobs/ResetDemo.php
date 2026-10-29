<?php
// app/jobs/ResetDemo.php
//
// Wipes mutable demo state and re-seeds a believable set of services,
// hours, settings, demo users, and 8 sample appointments.
//
// Multi-layer safety: the script ABORTS if DEMO_MODE is not true. There
// is no override — this is intentional. Never run against real data.
//
// =============================================================
//  SETUP — nightly reset
// =============================================================
//
//  Linux / macOS cron:
//
//    crontab -e
//    0 3 * * * /usr/bin/php /var/www/slate/app/jobs/ResetDemo.php >> /var/log/slate-demo-reset.log 2>&1
//
//  Windows Task Scheduler — daily 3am action:
//    php.exe  C:\xampp\htdocs\slate\app\jobs\ResetDemo.php
//
//  Shared hosting (no shell cron):
//    The /cron/reset-demo route in public/index.php fires this script,
//    gated by ?token=<DEMO_RESET_TOKEN>. Schedule it on cron-job.org.

declare(strict_types=1);

// ---- Bootstrap ------------------------------------------------------------

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

// ---- Logging -------------------------------------------------------------

$cliMode = PHP_SAPI === 'cli';
$logFile = BASE_PATH . '/storage/logs/demo-reset.log';
@mkdir(dirname($logFile), 0775, true);

$log = static function (string $line) use ($cliMode, $logFile): void {
    $stamped = '[' . date('Y-m-d H:i:s') . '] ' . $line;
    @file_put_contents($logFile, $stamped . "\n", FILE_APPEND);
    if ($cliMode) {
        echo $stamped . "\n";
    } else {
        echo '<div>' . htmlspecialchars($stamped, ENT_QUOTES, 'UTF-8') . '</div>';
    }
};

// ---- Safety check --------------------------------------------------------

if (!filter_var($_ENV['DEMO_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    $log('ABORTED — DEMO_MODE is not true. Refusing to wipe data.');
    if (!$cliMode) http_response_code(409);
    exit(1);
}

$log('--- Demo reset started ---');

$pdo = \App\Core\Database::getInstance()->pdo();
$db  = \App\Core\Database::getInstance();

// ---- Wipe mutable tables -------------------------------------------------

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE email_log');
$pdo->exec('TRUNCATE TABLE appointments');
$log('Truncated: email_log, appointments');

// Delete any user that isn't one of the two demo accounts.
$deleted = $db->query(
    "DELETE FROM users WHERE email NOT IN ('demo-admin@slate.test', 'demo-user@slate.test')"
)->rowCount();
$log("Deleted {$deleted} non-demo user(s)");

// ---- Demo accounts (upsert with deterministic password) ------------------

$demoHash = password_hash('demo1234', PASSWORD_ARGON2ID);

foreach ([
    ['Demo Admin',    'demo-admin@slate.test', 'admin'],
    ['Demo Customer', 'demo-user@slate.test',  'customer'],
] as [$name, $email, $role]) {
    $db->query(
        "INSERT INTO users (name, email, password_hash, role)
         VALUES (:name, :email, :hash, :role)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)",
        ['name' => $name, 'email' => $email, 'hash' => $demoHash, 'role' => $role],
    );
}
$log('Upserted demo users (password: demo1234)');

// ---- Re-seed services ----------------------------------------------------

$pdo->exec('TRUNCATE TABLE services');
$services = [
    ['Quick Consultation',   'Short intro / Q&A session.',                30,  35.00, '#1A56DB'],
    ['Standard Appointment', 'Regular hour-long appointment.',            60,  80.00, '#2E7D32'],
    ['Follow-up Session',    'Mid-length follow-up appointment.',         45,  60.00, '#B45309'],
    ['Extended Session',     'Deep-dive / extended consultation.',        90, 140.00, '#C84B31'],
];
foreach ($services as [$name, $desc, $dur, $price, $color]) {
    $db->insert('services', [
        'name'             => $name,
        'description'      => $desc,
        'duration_minutes' => $dur,
        'price'            => $price,
        'color'            => $color,
        'is_active'        => 1,
    ]);
}
$log('Re-seeded ' . count($services) . ' services');

// ---- Re-seed business hours ----------------------------------------------

$pdo->exec('TRUNCATE TABLE business_hours');
$hours = [
    [0, null,       null,       1],   // Sunday closed
    [1, '09:00:00', '18:00:00', 0],
    [2, '09:00:00', '18:00:00', 0],
    [3, '09:00:00', '18:00:00', 0],
    [4, '09:00:00', '18:00:00', 0],
    [5, '09:00:00', '18:00:00', 0],
    [6, '10:00:00', '14:00:00', 0],
];
foreach ($hours as [$dow, $open, $close, $closed]) {
    $db->insert('business_hours', [
        'day_of_week' => $dow,
        'open_time'   => $open,
        'close_time'  => $close,
        'is_closed'   => $closed,
    ]);
}
$log('Re-seeded business hours (Mon–Fri 9–18, Sat 10–14, Sun closed)');

// ---- Re-seed settings (upsert) -------------------------------------------

$settings = [
    'business_name'        => 'Slate Bookings',
    'timezone'             => (string) $app['timezone'],
    'buffer_minutes'       => '15',
    'advance_booking_days' => '60',
    'min_notice_hours'     => '2',
    'contact_email'        => 'demo-admin@slate.test',
    'contact_phone'        => '+1-555-0100',
    'location'             => 'In person · Toronto studio',
];
foreach ($settings as $k => $v) {
    $db->query(
        "INSERT INTO `settings` (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
        ['k' => $k, 'v' => $v],
    );
}
$log('Re-seeded ' . count($settings) . ' settings');

// ---- Generate 8 realistic sample appointments ----------------------------

$tz    = new \DateTimeZone((string) $app['timezone']);
$today = (new \DateTimeImmutable('now', $tz))->setTime(0, 0);

// Map service-name → row (need id + duration for inserts).
$serviceMap = [];
foreach ($db->fetchAll('SELECT id, name, duration_minutes FROM services') as $s) {
    $serviceMap[(string) $s['name']] = $s;
}

$plan = [
    ['offset' => -7, 'time' => '10:00', 'service' => 'Standard Appointment',  'status' => 'completed', 'name' => 'Sarah Chen',       'email' => 'sarah.chen@example.com',     'phone' => '+1 555-0101', 'notes' => 'Returning customer.'],
    ['offset' => -3, 'time' => '14:00', 'service' => 'Extended Session',      'status' => 'completed', 'name' => 'Marcus Williams',  'email' => 'marcus.w@example.com',       'phone' => '+1 555-0102', 'notes' => null],
    ['offset' =>  1, 'time' => '09:00', 'service' => 'Quick Consultation',    'status' => 'confirmed', 'name' => 'Priya Patel',      'email' => 'priya.patel@example.com',    'phone' => '+1 555-0103', 'notes' => 'First visit.'],
    ['offset' =>  2, 'time' => '11:30', 'service' => 'Follow-up Session',     'status' => 'confirmed', 'name' => "James O'Brien",    'email' => 'james.obrien@example.com',   'phone' => '+1 555-0104', 'notes' => null],
    ['offset' =>  4, 'time' => '14:00', 'service' => 'Standard Appointment',  'status' => 'confirmed', 'name' => 'Aiko Tanaka',      'email' => 'aiko.tanaka@example.com',    'phone' => '+1 555-0105', 'notes' => 'Prefers email follow-up.'],
    ['offset' =>  6, 'time' => '10:00', 'service' => 'Quick Consultation',    'status' => 'confirmed', 'name' => 'David Kim',        'email' => 'david.kim@example.com',      'phone' => '+1 555-0106', 'notes' => null],
    ['offset' =>  8, 'time' => '16:00', 'service' => 'Follow-up Session',     'status' => 'pending',   'name' => 'Maria Garcia',     'email' => 'maria.garcia@example.com',   'phone' => '+1 555-0107', 'notes' => 'Awaiting payment confirmation.'],
    ['offset' => 12, 'time' => '13:00', 'service' => 'Standard Appointment',  'status' => 'cancelled', 'name' => 'Olivia Bennett',   'email' => 'olivia.bennett@example.com', 'phone' => '+1 555-0108', 'notes' => null],
];

$inserted = 0;
foreach ($plan as $entry) {
    $base = $today->modify(($entry['offset'] >= 0 ? '+' : '') . $entry['offset'] . ' days');
    // Skip Sundays — push toward whichever direction the offset points.
    while ((int) $base->format('w') === 0) {
        $base = $base->modify($entry['offset'] >= 0 ? '+1 day' : '-1 day');
    }
    $service = $serviceMap[$entry['service']] ?? null;
    if ($service === null) continue;

    $startsAt = new \DateTimeImmutable($base->format('Y-m-d') . ' ' . $entry['time'] . ':00', $tz);
    $endsAt   = $startsAt->modify('+' . (int) $service['duration_minutes'] . ' minutes');

    $db->insert('appointments', [
        'user_id'        => null,
        'service_id'     => (int) $service['id'],
        'customer_name'  => $entry['name'],
        'customer_email' => $entry['email'],
        'customer_phone' => $entry['phone'],
        'starts_at'      => $startsAt->format('Y-m-d H:i:s'),
        'ends_at'        => $endsAt->format('Y-m-d H:i:s'),
        'status'         => $entry['status'],
        'notes'          => $entry['notes'],
        'cancel_token'   => bin2hex(random_bytes(16)),
    ]);
    $inserted++;
}
$log("Generated {$inserted} sample appointments");

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$log('--- Demo reset completed ---');
