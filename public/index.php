<?php
/**
 * public/index.php
 *
 * Front controller for the Slate booking system.
 * Order of operations:
 *   1. Composer autoload
 *   2. Load .env
 *   3. Read config files
 *   4. Configure runtime (timezone, error display)
 *   5. Start session BEFORE any output
 *   6. Initialise DB singleton
 *   7. Register routes and dispatch
 */

declare(strict_types=1);

use App\Controllers\ApiDocsController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\Admin\AppointmentController;
use App\Controllers\Admin\ApiTokenController;
use App\Controllers\Admin\BlockedDateController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EmailTestController;
use App\Controllers\Admin\LogController;
use App\Controllers\Admin\ScheduleController;
use App\Controllers\Admin\ServiceController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\WebhookController;
use App\Controllers\Api\AvailabilityController     as ApiAvailability;
use App\Controllers\Api\BookingsController         as ApiBookings;
use App\Controllers\Api\BusinessHoursController    as ApiBusinessHours;
use App\Controllers\Api\ServicesController         as ApiServices;
use App\Controllers\Api\Admin\AppointmentsController as ApiAdminAppointments;
use App\Controllers\Api\Admin\BlockedDatesController as ApiAdminBlockedDates;
use App\Controllers\Api\Admin\ServicesController     as ApiAdminServices;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Router;
use Dotenv\Dotenv;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH',  BASE_PATH . DIRECTORY_SEPARATOR . 'app');

require BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

/** @var array<string,mixed> $app */
$app = require BASE_PATH . '/config/app.php';
/** @var array<string,mixed> $db */
$db  = require BASE_PATH . '/config/db.php';

date_default_timezone_set((string) $app['timezone']);

if ($app['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
} else {
    // Production: hide errors from users, but log them all to disk.
    error_reporting(E_ALL);
    ini_set('display_errors',          '0');
    ini_set('display_startup_errors',  '0');
    ini_set('log_errors',              '1');
    @mkdir(BASE_PATH . '/storage/logs', 0775, true);
    ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');
}

/* ---- Application logger -------------------------------------------------
 * Initialised before the session and DB so any failure in those stages can
 * be persisted to the rotating app log.
 * --------------------------------------------------------------------------*/
$isProduction = ((string) ($app['env'] ?? 'production')) === 'production';
Logger::init(BASE_PATH . '/storage/logs', $isProduction);

/* ---- Centralised error/exception handlers -------------------------------
 * All uncaught throwables route through handleThrowable(). Non-fatal PHP
 * errors get downgraded to log entries (warning) so a stray notice doesn't
 * white-page a customer. register_shutdown_function() picks up parse /
 * memory-limit / segfault style fatals that the other two miss.
 * --------------------------------------------------------------------------*/
$debugMode = (bool) ($app['debug'] ?? false);

$handleThrowable = static function (\Throwable $e) use ($debugMode): void {
    $refId = strtoupper(bin2hex(random_bytes(4)));

    try {
        Logger::getInstance()->critical(
            sprintf('Uncaught %s: %s', get_class($e), $e->getMessage()),
            [
                'ref'    => $refId,
                'file'   => $e->getFile(),
                'line'   => $e->getLine(),
                'trace'  => $e->getTraceAsString(),
                'url'    => (string) ($_SERVER['REQUEST_URI']    ?? ''),
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                'ip'     => (string) ($_SERVER['REMOTE_ADDR']    ?? ''),
            ],
        );
    } catch (\Throwable) {
        // Never let logger failure mask the original error.
    }

    if (headers_sent()) {
        return;
    }

    http_response_code(500);

    if ($debugMode) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<pre style="padding:24px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;line-height:1.5;background:#fff;color:#1a1a1a;border:1px solid #e8e4dd;margin:24px;border-radius:12px;white-space:pre-wrap;">';
        echo 'Reference: ' . htmlspecialchars($refId, ENT_QUOTES) . "\n\n";
        echo htmlspecialchars((string) $e, ENT_QUOTES);
        echo '</pre>';
        return;
    }

    try {
        view('errors/500', ['title' => 'Something broke — Slate', 'refId' => $refId]);
    } catch (\Throwable) {
        // Last-ditch fallback if even the view layer is broken.
        header('Content-Type: text/plain; charset=utf-8');
        echo "500 Server Error\nReference: {$refId}\n";
    }
};

set_exception_handler($handleThrowable);

set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Respect @-suppression and the configured error_reporting level.
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $label = match ($errno) {
        E_WARNING, E_USER_WARNING               => 'PHP Warning',
        E_NOTICE, E_USER_NOTICE                 => 'PHP Notice',
        E_DEPRECATED, E_USER_DEPRECATED         => 'PHP Deprecated',
        E_STRICT                                => 'PHP Strict',
        E_RECOVERABLE_ERROR                     => 'PHP Recoverable Error',
        default                                 => "PHP Error[{$errno}]",
    };

    try {
        Logger::getInstance()->warning(
            sprintf('%s: %s', $label, $errstr),
            ['file' => $errfile, 'line' => $errline, 'errno' => $errno],
        );
    } catch (\Throwable) {
        // ignore — never let the logger crash the request
    }
    return true; // suppress PHP's own error message
});

register_shutdown_function(static function () use ($handleThrowable): void {
    $err = error_get_last();
    if ($err === null) return;
    $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) return;

    $handleThrowable(new \ErrorException(
        $err['message'], 0, $err['type'], $err['file'], $err['line'],
    ));
});

/* ---- Session bootstrap ---------------------------------------------------
 * Must happen before any output. Cookie flags follow OWASP defaults:
 *   - httponly: JS cannot read the session id
 *   - samesite=Lax: blocks most CSRF + still allows top-level GETs
 *   - use_strict_mode: server refuses uninitialised IDs (fixation defence)
 * --------------------------------------------------------------------------*/
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'name'             => 'slate_session',
        'cookie_httponly'  => true,
        'cookie_samesite'  => 'Lax',
        'cookie_secure'    => ($app['env'] === 'production'),
        'use_strict_mode'  => true,
    ]);
}

// ---- Idle-session timeout (logged-in users only) -------------------------
// Anonymous sessions stick around so the CSRF token + flash queue survive
// across forms. Once a user is authenticated, two hours of inactivity logs
// them out — even if their cookie is still valid.
$IDLE_TIMEOUT_SECONDS = 7200; // 2 hours
if (isset($_SESSION['user_id'], $_SESSION['last_activity'])
    && (time() - (int) $_SESSION['last_activity']) > $IDLE_TIMEOUT_SECONDS
) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $p['path'] ?? '/', $p['domain'] ?? '',
            (bool) ($p['secure'] ?? false), (bool) ($p['httponly'] ?? true),
        );
    }
    session_destroy();
    session_start([
        'name'             => 'slate_session',
        'cookie_httponly'  => true,
        'cookie_samesite'  => 'Lax',
        'cookie_secure'    => ($app['env'] === 'production'),
        'use_strict_mode'  => true,
    ]);
    flash('info', 'Your session expired. Please sign in again.');
}
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

Database::init($db);

/* ---- Routing ----------------------------------------------------------- */
$router = new Router();

// --- Public pages ---
$router->get('/', function (): void {
    view('home', ['title' => ($_ENV['APP_NAME'] ?? 'Slate') . ' — Book your appointment']);
});

// --- Public booking ---
$router->get ('/book',                            [BookingController::class, 'index']);
$router->post('/book',                            [BookingController::class, 'store']);
$router->get ('/book/calendar',                   [BookingController::class, 'calendar']);
$router->get ('/api/slots',                       [BookingController::class, 'slots']);

$router->get ('/booking/{token}',                 [BookingController::class, 'confirmation']);
$router->get ('/booking/{token}/calendar.ics',    [BookingController::class, 'calendarFile']);
$router->get ('/booking/{token}/cancel',          [BookingController::class, 'cancelConfirm']);
$router->post('/booking/{token}/cancel',          [BookingController::class, 'cancelDo']);
$router->get ('/booking/{token}/reschedule',      [BookingController::class, 'rescheduleShow']);
$router->post('/booking/{token}/reschedule',      [BookingController::class, 'rescheduleDo']);

// --- Auth ---
$router->get ('/login',    [AuthController::class, 'showLogin']);
$router->post('/login',    [AuthController::class, 'login']);
$router->get ('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout',   [AuthController::class, 'logout']);

// --- Admin ---
$router->get ('/admin',                              [DashboardController::class, 'index']);

// Appointments — literal paths registered before {id}/* so they win the regex race.
$router->get ('/admin/appointments',                          [AppointmentController::class, 'index']);
$router->get ('/admin/appointments/list',                     [AppointmentController::class, 'list']);
$router->get ('/admin/appointments/calendar',                 [AppointmentController::class, 'calendarView']);
$router->get ('/admin/appointments/export',                   [AppointmentController::class, 'export']);
$router->get ('/admin/appointments/day/{date}',               [AppointmentController::class, 'dayPanel']);
$router->get ('/admin/appointments/{id}/panel',               [AppointmentController::class, 'panel']);
$router->post('/admin/appointments/{id}/status',              [AppointmentController::class, 'changeStatus']);
$router->post('/admin/appointments/{id}/notes',               [AppointmentController::class, 'saveNotes']);
$router->post('/admin/appointments/{id}/resend-confirmation', [AppointmentController::class, 'resendConfirmation']);

$router->get ('/admin/services',                     [ServiceController::class, 'index']);
$router->post('/admin/services',                     [ServiceController::class, 'store']);
$router->post('/admin/services/{id}',                [ServiceController::class, 'update']);
$router->post('/admin/services/{id}/toggle',         [ServiceController::class, 'toggle']);
$router->post('/admin/services/{id}/delete',         [ServiceController::class, 'destroy']);

$router->get ('/admin/schedule',                     [ScheduleController::class, 'index']);
$router->post('/admin/schedule',                     [ScheduleController::class, 'save']);

$router->get ('/admin/blocked-dates',                [BlockedDateController::class, 'index']);
$router->post('/admin/blocked-dates/toggle',         [BlockedDateController::class, 'toggle']);
$router->post('/admin/blocked-dates/{id}/delete',    [BlockedDateController::class, 'destroy']);

$router->get ('/admin/settings',                     [SettingController::class, 'index']);
$router->post('/admin/settings',                     [SettingController::class, 'save']);

$router->get ('/admin/email-test',                   [EmailTestController::class, 'index']);
$router->post('/admin/email-test/send',              [EmailTestController::class, 'send']);

$router->get ('/admin/logs',                         [LogController::class, 'index']);
$router->get ('/admin/logs/tail',                    [LogController::class, 'tail']);

// --- Admin: API tokens + Webhooks (under Settings) ---
$router->get ('/admin/settings/api-tokens',                  [ApiTokenController::class, 'index']);
$router->post('/admin/settings/api-tokens',                  [ApiTokenController::class, 'create']);
$router->post('/admin/settings/api-tokens/{id}/revoke',      [ApiTokenController::class, 'revoke']);

$router->get ('/admin/settings/webhooks',                    [WebhookController::class, 'index']);
$router->post('/admin/settings/webhooks',                    [WebhookController::class, 'store']);
$router->post('/admin/settings/webhooks/{id}/toggle',        [WebhookController::class, 'toggle']);
$router->post('/admin/settings/webhooks/{id}/delete',        [WebhookController::class, 'destroy']);
$router->post('/admin/settings/webhooks/{id}/test',          [WebhookController::class, 'test']);

// --- Public API documentation ---
$router->get ('/api/docs',                                   [ApiDocsController::class, 'index']);

// --- REST API v1 ---
// Public
$router->get ('/api/v1/services',                            [ApiServices::class,      'index']);
$router->get ('/api/v1/availability',                        [ApiAvailability::class,  'index']);
$router->get ('/api/v1/business-hours',                      [ApiBusinessHours::class, 'index']);
$router->post('/api/v1/bookings',                            [ApiBookings::class,      'store']);
$router->get ('/api/v1/bookings/{token}',                    [ApiBookings::class,      'show']);
$router->post('/api/v1/bookings/{token}/cancel',             [ApiBookings::class,      'cancel']);

// Admin (Bearer token)
$router->get   ('/api/v1/admin/appointments',                [ApiAdminAppointments::class, 'index']);
$router->patch ('/api/v1/admin/appointments/{id}',           [ApiAdminAppointments::class, 'update']);
$router->post  ('/api/v1/admin/services',                    [ApiAdminServices::class,     'store']);
$router->patch ('/api/v1/admin/services/{id}',               [ApiAdminServices::class,     'update']);
$router->delete('/api/v1/admin/services/{id}',               [ApiAdminServices::class,     'destroy']);
$router->post  ('/api/v1/admin/blocked-dates',               [ApiAdminBlockedDates::class, 'store']);

// --- Demo reset (HTTP trigger for hosts without shell cron) ---
$router->get('/cron/reset-demo', function (): void {
    if (!is_demo_mode()) {
        http_response_code(404);
        echo 'not found';
        return;
    }
    $expected = (string) (env('DEMO_RESET_TOKEN', '') ?: '');
    $given    = (string) ($_GET['token'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        echo 'forbidden';
        return;
    }
    require base_path('app/jobs/ResetDemo.php');
});

// --- 404 ---
$router->setNotFoundHandler(function (): void {
    http_response_code(404);
    view('errors/404', ['title' => 'Not found — Slate']);
});

// Any throwable from dispatch flows through the centralised handler above.
$router->dispatch(
    method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
    uri:    $_SERVER['REQUEST_URI']    ?? '/',
);
