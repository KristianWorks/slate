<?php
/**
 * app/helpers/functions.php
 *
 * Slate — global helpers. Autoloaded via composer's `files` entry.
 * All output helpers assume sessions have been started in the bootstrap.
 */

declare(strict_types=1);

use Carbon\Carbon;

/* ============================================================
 * Output / escaping
 * ============================================================ */

if (!function_exists('e')) {
    /**
     * Escape a string for safe HTML output.
     * EVERY value rendered to HTML goes through this — it is the project's
     * single defence against XSS.
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* ============================================================
 * Environment / paths
 * ============================================================ */

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value !== false && $value !== null ? $value : $default;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $append = ''): string
    {
        return BASE_PATH . ($append !== '' ? DIRECTORY_SEPARATOR . ltrim($append, '/\\') : '');
    }
}

if (!function_exists('redirect')) {
    /**
     * Send a 302 redirect and stop execution.
     *
     * Under APP_TESTING the function throws TestRedirect instead of calling
     * exit() — so test runners can catch the target URL. Production
     * behaviour is unchanged.
     */
    function redirect(string $url): never
    {
        header("Location: {$url}", true, 302);
        if (defined('APP_TESTING') && APP_TESTING) {
            throw new \App\Core\TestRedirect($url);
        }
        exit;
    }
}

if (!function_exists('now')) {
    /** Carbon "now" in the configured app timezone. */
    function now(): Carbon
    {
        return Carbon::now((string) ($_ENV['APP_TIMEZONE'] ?? 'UTC'));
    }
}

/* ============================================================
 * CSRF
 * ============================================================ */

if (!function_exists('csrf_token')) {
    /** Generate (or return) the per-session CSRF token. */
    function csrf_token(): string
    {
        $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    /** Hidden input markup — drop into every POST form. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_check')) {
    /**
     * Validate the submitted CSRF token. On failure, abort with 419.
     * Accepts the token from either the `_csrf` POST field (forms) or the
     * `X-CSRF-Token` request header (HTMX / fetch). Constant-time comparison.
     */
    function csrf_check(): void
    {
        $submitted = (string) (
            $_POST['_csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? ''
        );
        $expected = (string) ($_SESSION['_csrf'] ?? '');
        if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
            try {
                logger()->warning('CSRF token mismatch', [
                    'url'    => (string) ($_SERVER['REQUEST_URI']    ?? ''),
                    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                    'ip'     => (string) ($_SERVER['REMOTE_ADDR']    ?? ''),
                    'has_session_token' => $expected !== '',
                    'has_submitted_token' => $submitted !== '',
                ]);
            } catch (\Throwable) {}
            http_response_code(419);
            if (defined('APP_TESTING') && APP_TESTING) {
                throw new \App\Core\TestExit('CSRF token mismatch');
            }
            echo '419 — CSRF token mismatch.';
            exit;
        }
    }
}

/* ============================================================
 * Form re-population + validation errors
 * ============================================================ */

if (!function_exists('old')) {
    /**
     * Retrieve a previously-submitted form value (e.g. after redirect-with-errors).
     * Output is HTML-escaped — safe to drop directly into `value="<?= old('email') ?>"`.
     */
    function old(string $key, ?string $default = ''): string
    {
        return e((string) ($_SESSION['_old'][$key] ?? $default ?? ''));
    }
}

if (!function_exists('old_set')) {
    /** Persist form input for the next request (used by controllers on validation failure). */
    function old_set(array $data): void
    {
        $_SESSION['_old'] = $data;
    }
}

if (!function_exists('errors_set')) {
    /** @param array<string,string> $errors */
    function errors_set(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }
}

if (!function_exists('errors')) {
    /** Return the first error for a field, or null. */
    function errors(string $key): ?string
    {
        $msg = $_SESSION['_errors'][$key] ?? null;
        return $msg !== null ? (string) $msg : null;
    }
}

/* ============================================================
 * Flash messages
 * ============================================================ */

if (!function_exists('flash')) {
    /**
     * Dual-mode flash helper:
     *   flash('success', 'Saved!')  — push a message onto the queue
     *   flash()                      — pull and clear ALL queued messages
     *
     * @return list<array{type:string,message:string}>|null
     */
    function flash(?string $type = null, ?string $message = null): ?array
    {
        if ($type !== null && $message !== null) {
            $_SESSION['_flash']   ??= [];
            $_SESSION['_flash'][]   = ['type' => $type, 'message' => $message];
            return null;
        }
        $queue = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        /** @var list<array{type:string,message:string}> $queue */
        return $queue;
    }
}

/* ============================================================
 * View rendering
 * ============================================================ */

if (!function_exists('view')) {
    /**
     * Render a view, optionally wrapping it in a layout.
     *
     * Usage:
     *   view('auth/login', ['title' => 'Sign in']);                     // wraps in /app/views/layouts/main.php
     *   view('admin/dashboard', ['active' => 'dashboard'], 'admin');    // wraps in /app/views/layouts/admin.php
     *   view('admin/services/_row', ['service' => $s], null);           // raw — used for HTMX partials
     *
     * View files live in /app/views and may use either `/` or `.` as separators.
     * They receive everything in $data plus all global helpers in scope.
     *
     * @param array<string,mixed> $data
     */
    function view(string $name, array $data = [], ?string $layout = 'main'): void
    {
        $relative = str_replace(['.', '\\'], '/', $name);
        $viewPath = base_path('app/views/' . $relative . '.php');
        if (!is_file($viewPath)) {
            throw new RuntimeException("View not found: {$name}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            unset($_SESSION['_errors'], $_SESSION['_old']);
            return;
        }

        // Drain flashes once, after rendering the inner view, so the layout
        // has the up-to-date queue (the inner view may have pushed more).
        $flashes = flash();

        $layoutPath = base_path("app/views/layouts/{$layout}.php");
        if (!is_file($layoutPath)) {
            throw new RuntimeException("Layout not found: {$layout}");
        }
        require $layoutPath;

        unset($_SESSION['_errors'], $_SESSION['_old']);
    }
}

/* ============================================================
 * Auth / session helpers
 * ============================================================ */

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(): ?string
    {
        return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : null;
    }
}

if (!function_exists('current_user_name')) {
    function current_user_name(): ?string
    {
        return isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : null;
    }
}

if (!function_exists('current_user_email')) {
    function current_user_email(): ?string
    {
        return isset($_SESSION['user_email']) ? (string) $_SESSION['user_email'] : null;
    }
}

/* ============================================================
 * Demo mode
 * ============================================================ */

if (!function_exists('is_demo_mode')) {
    /** Read-only check on the DEMO_MODE flag. */
    function is_demo_mode(): bool
    {
        return filter_var($_ENV['DEMO_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('is_demo_admin')) {
    /** True only when demo mode is on AND the signed-in user is the demo admin. */
    function is_demo_admin(): bool
    {
        return is_demo_mode() && current_user_email() === 'demo-admin@slate.test';
    }
}

if (!function_exists('demo_block')) {
    /**
     * Guard a destructive endpoint. If the demo admin is calling it, abort
     * with a friendly toast (HTMX-aware) and never return.
     *
     * Drop this at the top of methods that wipe / cancel / delete things.
     */
    function demo_block(string $message = 'This action is disabled in demo mode.'): void
    {
        if (!is_demo_admin()) {
            return;
        }

        if (($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {
            // HTMX swaps an empty response — but we want a toast on the page,
            // so we use the HX-Trigger header to fire a client event the
            // layout's toast component already listens for.
            header('HX-Trigger: ' . json_encode([
                'flash' => ['type' => 'error', 'message' => $message],
            ]));
            http_response_code(204);
            exit;
        }

        flash('error', $message);
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '/admin');
        // Only follow same-host referers to avoid open-redirect surprises.
        $host = parse_url($referer, PHP_URL_HOST);
        if ($host !== null && $host !== ($_SERVER['HTTP_HOST'] ?? '')) {
            $referer = '/admin';
        }
        redirect($referer);
    }
}

if (!function_exists('require_auth')) {
    /** Abort the current request with a redirect to /login unless authenticated. */
    function require_auth(): void
    {
        if (current_user_id() === null) {
            flash('error', 'Please sign in to continue.');
            redirect('/login');
        }
    }
}

if (!function_exists('require_admin')) {
    /** Require an authenticated admin. Sends 403 for signed-in non-admins. */
    function require_admin(): void
    {
        require_auth();
        if (current_user_role() !== 'admin') {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }
}

if (!function_exists('is_super_admin')) {
    /** True only for users with users.is_super = 1. */
    function is_super_admin(): bool
    {
        if (current_user_role() !== 'admin') return false;
        $uid = current_user_id();
        if ($uid === null) return false;
        $user = \App\Models\User::findById($uid);
        return $user !== null && (int) ($user['is_super'] ?? 0) === 1;
    }
}

if (!function_exists('can_view_logs')) {
    /** Logs are visible in dev to any admin; in production only to super-admins. */
    function can_view_logs(): bool
    {
        if (current_user_role() !== 'admin') return false;
        $env = (string) ($_ENV['APP_ENV'] ?? 'production');
        return $env !== 'production' || is_super_admin();
    }
}

if (!function_exists('require_super_or_dev')) {
    /** Gate for admin routes that expose internals (logs, future debug tools). */
    function require_super_or_dev(): void
    {
        require_admin();
        if (!can_view_logs()) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }
}

/* ============================================================
 * Logger
 * ============================================================ */

if (!function_exists('logger')) {
    /**
     * Global accessor. Lazy-initialises against /storage/logs if init() was
     * never called — so calls early in bootstrap (or in CLI scripts that
     * forgot to init) never throw.
     */
    function logger(): \App\Core\Logger
    {
        return \App\Core\Logger::getInstance();
    }
}

/* ============================================================
 * Internationalization
 * ============================================================ */

if (!function_exists('__')) {
    /**
     * Look up a translation by dot-notated key, e.g. `auth.login_title`.
     * Replacements use `:placeholder` syntax: __('foo.bar', ['name' => 'X']).
     *
     * - Loads `/lang/<APP_LOCALE>.php` once per request, falls back to `en.php`.
     * - Missing keys return the key itself, so the gap is visible during dev.
     * - Output is intentionally NOT HTML-escaped — escape at the call site
     *   with `e()` so views opt in explicitly.
     *
     * @param array<string,string|int|float> $replacements
     */
    function __(string $key, array $replacements = []): string
    {
        static $strings = null;
        static $loadedLocale = null;

        if ($strings === null || $loadedLocale === null) {
            $locale = (string) ($_ENV['APP_LOCALE'] ?? 'en');
            $path   = base_path("lang/{$locale}.php");
            if (!is_file($path)) {
                $path   = base_path('lang/en.php');
                $locale = 'en';
            }
            $strings = is_file($path) ? (require $path) : [];
            $loadedLocale = $locale;
        }

        // Resolve dot-notation against the nested array.
        $value = $strings;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $key;
            }
        }

        if (!is_string($value)) {
            return $key;
        }

        if ($replacements !== []) {
            $find    = [];
            $replace = [];
            foreach ($replacements as $k => $v) {
                $find[]    = ':' . $k;
                $replace[] = (string) $v;
            }
            $value = str_replace($find, $replace, $value);
        }

        return $value;
    }
}
