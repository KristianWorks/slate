<?php
/**
 * app/controllers/AuthController.php
 *
 * Handles sign-in, registration, and sign-out for the Slate booking system.
 *
 * Security model:
 *   - Every POST entry-point invokes csrf_check() before doing anything else.
 *   - Passwords are hashed with PASSWORD_ARGON2ID and rehashed on login
 *     if the algorithm/cost changes.
 *   - session_regenerate_id(true) is called on every privilege change to
 *     thwart session fixation.
 *   - All DB access goes through prepared statements via the User model.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Middleware\RateLimit;
use App\Models\User;
use App\Models\UserRole;

final class AuthController
{
    /** GET /login */
    public function showLogin(): void
    {
        if (current_user_id() !== null) {
            redirect('/');
        }
        view('auth/login', ['title' => 'Sign in — Slate']);
    }

    /** POST /login */
    public function login(): void
    {
        // 5 attempts / 15 min per IP. Counter is cleared on successful login.
        RateLimit::guard('login', maxAttempts: 5, windowSeconds: 900);
        csrf_check();

        $email    = trim((string) ($_POST['email']    ?? ''));
        $password = (string)        ($_POST['password'] ?? '');

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'] = 'Please enter your password.';
        }

        if ($errors !== []) {
            old_set(['email' => $email]);
            errors_set($errors);
            flash('error', 'Please fix the errors below.');
            redirect('/login');
        }

        $user = User::findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            logger()->warning('Login failed', [
                'email_attempted' => $email,
                'reason'          => $user === null ? 'no_such_user' : 'bad_password',
                'ip'              => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ]);
            old_set(['email' => $email]);
            // Generic message — do NOT leak whether the email exists.
            flash('error', 'Invalid email or password.');
            redirect('/login');
        }

        // Transparent rehash if cost / algorithm has moved on.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
            User::updatePasswordHash(
                id:           (int) $user['id'],
                passwordHash: password_hash($password, PASSWORD_ARGON2ID),
            );
        }

        // Always regenerate on privilege change — defeats fixation.
        session_regenerate_id(true);

        $_SESSION['user_id']       = (int) $user['id'];
        $_SESSION['user_role']     = (string) $user['role'];
        $_SESSION['user_name']     = (string) $user['name'];
        $_SESSION['user_email']    = (string) $user['email'];
        $_SESSION['last_activity'] = time();

        // Reward the user — successful login clears their counter.
        RateLimit::clear('login');

        logger()->info('Login success', [
            'user_id' => (int) $user['id'],
            'role'    => (string) $user['role'],
            'ip'      => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        flash('success', "Welcome back, {$user['name']}.");
        redirect($user['role'] === 'admin' ? '/admin' : '/');
    }

    /** GET /register */
    public function showRegister(): void
    {
        if (current_user_id() !== null) {
            redirect('/');
        }
        view('auth/register', ['title' => 'Create account — Slate']);
    }

    /** POST /register */
    public function register(): void
    {
        // 3 sign-ups / hour per IP — defeats automated account creation.
        RateLimit::guard('register', maxAttempts: 3, windowSeconds: 3600);
        csrf_check();

        $name     = trim((string) ($_POST['name']     ?? ''));
        $email    = trim((string) ($_POST['email']    ?? ''));
        $password = (string)        ($_POST['password']              ?? '');
        $confirm  = (string)        ($_POST['password_confirmation'] ?? '');

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Please enter your name (max 120 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        // NIST 800-63B-style minimum: 8 chars + at least 1 letter + 1 number.
        // Deliberately not stricter (composition rules hurt more than they help).
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors['password'] = 'Password must include at least one letter and one number.';
        }
        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if ($errors === [] && User::findByEmail($email) !== null) {
            $errors['email'] = 'That email is already registered.';
        }

        if ($errors !== []) {
            old_set(['name' => $name, 'email' => $email]);
            errors_set($errors);
            flash('error', 'Please fix the errors below.');
            redirect('/register');
        }

        $userId = User::create(
            name:         $name,
            email:        $email,
            passwordHash: password_hash($password, PASSWORD_ARGON2ID),
            role:         UserRole::Customer,
        );

        session_regenerate_id(true);

        $_SESSION['user_id']       = $userId;
        $_SESSION['user_role']     = UserRole::Customer->value;
        $_SESSION['user_name']     = $name;
        $_SESSION['user_email']    = $email;
        $_SESSION['last_activity'] = time();

        flash('success', "Welcome to Slate, {$name}.");
        redirect('/');
    }

    /** POST /logout */
    public function logout(): void
    {
        csrf_check();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42_000,
                $params['path']     ?? '/',
                $params['domain']   ?? '',
                (bool) ($params['secure']   ?? false),
                (bool) ($params['httponly'] ?? true),
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Start a fresh session purely so we can flash a goodbye message.
        session_start();
        flash('success', 'You have been signed out.');
        redirect('/login');
    }
}
