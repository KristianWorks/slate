<?php
/**
 * tests/TestCase.php
 *
 * Base test case. Every test goes through a clean-slate setUp():
 *   - all tables truncated
 *   - default services / business hours / settings re-seeded
 *   - rate-limit cache cleared
 *   - $_POST / $_GET / $_SESSION / $_SERVER reset
 *   - fresh CSRF token in session
 */

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\TestRedirect;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    private const TABLES = [
        'email_log',
        'appointments',
        'blocked_dates',
        'business_hours',
        'services',
        'settings',
        'users',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabase();
        $this->seedDefaults();
        $this->resetRateLimits();
        $this->resetSuperglobals();
    }

    /* ============================================================
     * Reset / seed
     * ============================================================ */

    protected function resetDatabase(): void
    {
        $pdo = Database::getInstance()->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $t) {
            $pdo->exec("TRUNCATE TABLE `{$t}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function seedDefaults(): void
    {
        $db = Database::getInstance();

        // 4 services matching the production seed.
        $services = [
            ['Quick Consultation',   30,  35.00, '#1A56DB'],
            ['Standard Appointment', 60,  80.00, '#2E7D32'],
            ['Follow-up Session',    45,  60.00, '#B45309'],
            ['Extended Session',     90, 140.00, '#C84B31'],
        ];
        foreach ($services as [$name, $dur, $price, $color]) {
            $db->insert('services', [
                'name'             => $name,
                'duration_minutes' => $dur,
                'price'            => $price,
                'color'            => $color,
                'is_active'        => 1,
            ]);
        }

        // Mon-Fri 9-18, Sat 10-14, Sun closed.
        $hours = [
            [0, null,       null,       1],
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

        // min_notice_hours=0 so tests can book "tomorrow morning" freely.
        // advance_booking_days is generous so far-future tests still work.
        $settings = [
            'business_name'        => 'Slate Test',
            'timezone'             => 'America/Toronto',
            'buffer_minutes'       => '15',
            'advance_booking_days' => '365',
            'min_notice_hours'     => '0',
            'contact_email'        => 'admin@slate.test',
            'location'             => 'Test Location',
        ];
        foreach ($settings as $k => $v) {
            $db->query(
                "INSERT INTO `settings` (`key`, `value`) VALUES (:k, :v)",
                ['k' => $k, 'v' => $v],
            );
        }
    }

    protected function resetRateLimits(): void
    {
        $dir = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'ratelimit';
        if (!is_dir($dir)) return;
        foreach ((glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: []) as $f) {
            @unlink($f);
        }
    }

    protected function resetSuperglobals(): void
    {
        $_POST    = [];
        $_GET     = [];
        $_SESSION = [];

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_SERVER['HTTP_HOST']      = 'localhost';

        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }

    /* ============================================================
     * Factories
     * ============================================================ */

    /** @param array<string,mixed> $overrides */
    protected function createService(array $overrides = []): array
    {
        $defaults = [
            'name'             => 'Custom Service',
            'description'      => null,
            'duration_minutes' => 60,
            'price'            => 50.00,
            'color'            => '#C84B31',
            'is_active'        => 1,
        ];
        $data = array_merge($defaults, $overrides);
        $id   = Database::getInstance()->insert('services', $data);
        return Database::getInstance()->fetch(
            'SELECT * FROM services WHERE id = :id',
            ['id' => $id],
        );
    }

    /** @param array<string,mixed> $overrides */
    protected function createAppointment(array $overrides = []): array
    {
        $defaults = [
            'service_id'     => 1,
            'customer_name'  => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '+1 555-1234',
            'starts_at'      => date('Y-m-d 10:00:00', strtotime('+1 day')),
            'ends_at'        => date('Y-m-d 11:00:00', strtotime('+1 day')),
            'status'         => 'confirmed',
            'notes'          => null,
            'cancel_token'   => bin2hex(random_bytes(16)),
        ];
        $data = array_merge($defaults, $overrides);
        $id   = Database::getInstance()->insert('appointments', $data);

        // Return joined fields so callers (e.g. IcsGenerator tests) have the
        // service_name / service_duration columns they expect.
        return Database::getInstance()->fetch(
            "SELECT a.*, s.name AS service_name, s.color AS service_color,
                    s.duration_minutes AS service_duration, s.price AS service_price
               FROM appointments a
               JOIN services s ON s.id = a.service_id
              WHERE a.id = :id",
            ['id' => $id],
        );
    }

    /** @param array<string,mixed> $overrides */
    protected function createUser(array $overrides = []): array
    {
        $defaults = [
            'name'          => 'Test User',
            'email'         => 'test-' . bin2hex(random_bytes(4)) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_ARGON2ID),
            'role'          => 'customer',
            'is_super'      => 0,
        ];
        $data = array_merge($defaults, $overrides);
        $id   = Database::getInstance()->insert('users', $data);
        return Database::getInstance()->fetch(
            'SELECT * FROM users WHERE id = :id',
            ['id' => $id],
        );
    }

    /** Put a user into the current session. */
    protected function authenticate(array $user): void
    {
        $_SESSION['user_id']       = (int) $user['id'];
        $_SESSION['user_role']     = (string) $user['role'];
        $_SESSION['user_name']     = (string) $user['name'];
        $_SESSION['user_email']    = (string) $user['email'];
        $_SESSION['last_activity'] = time();
    }

    /* ============================================================
     * Request helpers
     * ============================================================ */

    /** Inject the current CSRF token into a $_POST-like array. */
    protected function withCsrf(array $data = []): array
    {
        $data['_csrf'] = $_SESSION['_csrf'] ?? '';
        return $data;
    }

    /** Run $fn and assert it called redirect(). Returns the target URL. */
    protected function captureRedirect(callable $fn): string
    {
        try {
            $fn();
        } catch (TestRedirect $e) {
            return $e->targetUrl;
        }
        $this->fail('Expected redirect, none thrown');
    }

    /* ============================================================
     * Date helpers
     * ============================================================ */

    /** YYYY-MM-DD of the next Monday-through-Friday after today. */
    protected function nextWeekday(): string
    {
        $d = new \DateTimeImmutable('tomorrow', new \DateTimeZone('America/Toronto'));
        while (in_array((int) $d->format('w'), [0, 6], true)) {
            $d = $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    }

    /** YYYY-MM-DD of the next Sunday. */
    protected function nextSunday(): string
    {
        return (new \DateTimeImmutable('next Sunday', new \DateTimeZone('America/Toronto')))
            ->format('Y-m-d');
    }

    /** YYYY-MM-DD of the next Saturday. */
    protected function nextSaturday(): string
    {
        return (new \DateTimeImmutable('next Saturday', new \DateTimeZone('America/Toronto')))
            ->format('Y-m-d');
    }
}
