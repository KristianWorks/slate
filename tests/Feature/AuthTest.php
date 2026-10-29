<?php
/**
 * tests/Feature/AuthTest.php
 *
 * Auth lifecycle — register, login, rate-limit, CSRF guards.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\AuthController;
use App\Core\Database;
use App\Core\TestExit;
use App\Models\User;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    public function test_registration_with_valid_data_creates_user(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf([
            'name'                  => 'New Customer',
            'email'                 => 'new@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $url = $this->captureRedirect(fn () => (new AuthController())->register());

        $this->assertSame('/', $url);

        $user = User::findByEmail('new@example.com');
        $this->assertNotNull($user);
        $this->assertSame('customer', (string) $user['role']);
        $this->assertTrue(password_verify('password123', (string) $user['password_hash']));
    }

    public function test_registration_with_existing_email_fails(): void
    {
        $this->createUser(['email' => 'taken@example.com']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf([
            'name'                  => 'Imposter',
            'email'                 => 'taken@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $url = $this->captureRedirect(fn () => (new AuthController())->register());

        $this->assertSame('/register', $url);

        // Only the original user exists.
        $count = (int) Database::getInstance()->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE email = :e',
            ['e' => 'taken@example.com'],
        );
        $this->assertSame(1, $count);
    }

    public function test_registration_rejects_password_without_letter_and_number(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf([
            'name'                  => 'Alice',
            'email'                 => 'alice@example.com',
            'password'              => 'allletters',           // no digit
            'password_confirmation' => 'allletters',
        ]);

        $url = $this->captureRedirect(fn () => (new AuthController())->register());
        $this->assertSame('/register', $url);
        $this->assertNull(User::findByEmail('alice@example.com'));
    }

    public function test_login_with_correct_credentials_succeeds(): void
    {
        $user = $this->createUser([
            'email'         => 'me@example.com',
            'password_hash' => password_hash('correct-horse', PASSWORD_ARGON2ID),
            'role'          => 'customer',
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf([
            'email'    => 'me@example.com',
            'password' => 'correct-horse',
        ]);

        $url = $this->captureRedirect(fn () => (new AuthController())->login());

        $this->assertSame('/', $url);
        $this->assertSame((int) $user['id'], $_SESSION['user_id'] ?? null);
        $this->assertSame('customer',         $_SESSION['user_role'] ?? null);
    }

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $this->createUser([
            'email'         => 'admin@example.com',
            'password_hash' => password_hash('admin-pass-1', PASSWORD_ARGON2ID),
            'role'          => 'admin',
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf(['email' => 'admin@example.com', 'password' => 'admin-pass-1']);

        $url = $this->captureRedirect(fn () => (new AuthController())->login());
        $this->assertSame('/admin', $url);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->createUser([
            'email'         => 'me@example.com',
            'password_hash' => password_hash('correct-horse', PASSWORD_ARGON2ID),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->withCsrf([
            'email'    => 'me@example.com',
            'password' => 'wrong',
        ]);

        $url = $this->captureRedirect(fn () => (new AuthController())->login());

        $this->assertSame('/login', $url);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_rate_limit_kicks_in_after_5_failed_logins(): void
    {
        $this->createUser([
            'email'         => 'rate-target@example.com',
            'password_hash' => password_hash('correct', PASSWORD_ARGON2ID),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';

        // First 5 attempts each redirect (back to /login with a flash).
        for ($i = 1; $i <= 5; $i++) {
            $_POST = $this->withCsrf([
                'email'    => 'rate-target@example.com',
                'password' => 'wrong',
            ]);
            $url = $this->captureRedirect(fn () => (new AuthController())->login());
            $this->assertSame('/login', $url, "Attempt {$i} should redirect, not 429");
        }

        // 6th attempt: rate limit triggers BEFORE csrf_check / password verify.
        $_POST = $this->withCsrf([
            'email'    => 'rate-target@example.com',
            'password' => 'wrong',
        ]);

        $this->expectException(TestExit::class);
        (new AuthController())->login();
    }

    public function test_successful_login_resets_the_rate_limit_counter(): void
    {
        $this->createUser([
            'email'         => 'comeback@example.com',
            'password_hash' => password_hash('correct', PASSWORD_ARGON2ID),
        ]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';

        // 3 misses
        for ($i = 0; $i < 3; $i++) {
            $_POST = $this->withCsrf(['email' => 'comeback@example.com', 'password' => 'wrong']);
            $this->captureRedirect(fn () => (new AuthController())->login());
        }

        // Then a correct login — should clear the counter.
        $_POST = $this->withCsrf(['email' => 'comeback@example.com', 'password' => 'correct']);
        $this->captureRedirect(fn () => (new AuthController())->login());

        // The /storage/cache/ratelimit/<sha1>.json should no longer exist.
        $key  = sha1('127.0.0.1|login');
        $file = BASE_PATH . '/storage/cache/ratelimit/' . $key . '.json';
        $this->assertFileDoesNotExist($file);
    }

    public function test_csrf_required_on_post_routes(): void
    {
        // Valid session token, tampered POST token — csrf_check() throws.
        $_SESSION['_csrf'] = 'real-token';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf'    => 'tampered',
            'email'    => 'foo@example.com',
            'password' => 'bar',
        ];

        try {
            csrf_check();
            $this->fail('csrf_check should have refused the request');
        } catch (TestExit) {
            $this->assertSame(419, http_response_code());
            $this->assertArrayNotHasKey('user_id', $_SESSION);
            return;
        }
    }

    public function test_csrf_missing_token_is_refused(): void
    {
        $_SESSION['_csrf'] = 'real-token';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['email' => 'foo@example.com']; // no _csrf at all

        $this->expectException(TestExit::class);
        csrf_check();
    }
}
