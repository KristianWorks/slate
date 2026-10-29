<?php
/**
 * app/middleware/RateLimit.php
 *
 * Tiny, dependency-free rate limiter backed by JSON files in
 * /storage/cache/ratelimit/. Keyed by client IP + route label.
 *
 * Usage at the top of a controller method:
 *
 *     RateLimit::guard('login', maxAttempts: 5, windowSeconds: 900);
 *
 * Behaviour:
 *   - First call in a fresh window: count = 1, allow.
 *   - Each subsequent call within the window: count++, allow until == max.
 *   - count > max: respond 429 (HTML page or HTMX-friendly fragment) and
 *     halt — caller never returns from guard().
 *
 * Concurrency:
 *   - All read-update-writes happen under flock(LOCK_EX), so two
 *     simultaneous requests against the same key cannot both win.
 *
 * Failure mode:
 *   - If the cache directory is unwritable, guard() fails OPEN (i.e. lets
 *     the request through) and logs to error_log. The alternative —
 *     locking out every visitor because of a disk error — is worse.
 */

declare(strict_types=1);

namespace App\Middleware;

final class RateLimit
{
    private const CACHE_DIR_REL = 'storage/cache/ratelimit';

    /** Reject the request with 429 if `tryHit` would exceed the limit. */
    public static function guard(string $key, int $maxAttempts, int $windowSeconds): void
    {
        if (!self::tryHit($key, $maxAttempts, $windowSeconds)) {
            self::reject($windowSeconds);
        }
    }

    /**
     * Increment the counter for (ip, key) and return whether the request
     * is allowed. Always increments — callers SHOULD NOT call this and
     * then ignore the result.
     */
    public static function tryHit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $dir = base_path(self::CACHE_DIR_REL);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('[RateLimit] cache dir unwritable: ' . $dir . ' — failing open');
            return true;
        }

        $file = $dir . DIRECTORY_SEPARATOR . sha1(self::ip() . '|' . $key) . '.json';

        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            error_log('[RateLimit] cannot open ' . $file . ' — failing open');
            return true;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return true; // can't lock — fail open
            }

            rewind($fp);
            $raw  = stream_get_contents($fp) ?: '';
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['window_start'], $data['count'])) {
                $data = ['window_start' => time(), 'count' => 0];
            }

            $now = time();
            if (($now - (int) $data['window_start']) >= $windowSeconds) {
                $data = ['window_start' => $now, 'count' => 0];
            }
            $data['count']++;

            $allowed = $data['count'] <= $maxAttempts;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($data));
            fflush($fp);

            if (!$allowed) {
                $remaining = max(1, $windowSeconds - ($now - (int) $data['window_start']));
                if (!headers_sent()) {
                    header('Retry-After: ' . $remaining);
                }
            }

            // Probabilistic GC — clean up entries older than 24h on ~1% of calls.
            if (random_int(1, 100) === 1) {
                self::gc($dir);
            }

            return $allowed;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** Reset the counter for the current IP + key (e.g. on successful login). */
    public static function clear(string $key): void
    {
        $file = base_path(self::CACHE_DIR_REL . '/' . sha1(self::ip() . '|' . $key) . '.json');
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Resolve the client IP. Trust REMOTE_ADDR only — proxies must be configured upstream. */
    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    /** @internal */
    private static function gc(string $dir): void
    {
        $cutoff = time() - 86_400;
        $files  = glob($dir . '/*.json') ?: [];
        foreach ($files as $f) {
            if (@filemtime($f) < $cutoff) {
                @unlink($f);
            }
        }
    }

    private static function reject(int $windowSeconds): void
    {
        try {
            logger()->warning('Rate limit triggered', [
                'ip'        => self::ip(),
                'url'       => (string) ($_SERVER['REQUEST_URI']    ?? ''),
                'method'    => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                'window_s'  => $windowSeconds,
            ]);
        } catch (\Throwable) {}

        $minutes = (int) max(1, ceil($windowSeconds / 60));
        http_response_code(429);
        if (!headers_sent()) {
            header('Retry-After: ' . $windowSeconds);
        }

        // Tests catch TestExit to assert the limit fired without the test
        // process itself terminating.
        if (defined('APP_TESTING') && APP_TESTING) {
            throw new \App\Core\TestExit("Rate limit exceeded (window={$windowSeconds}s)");
        }

        // HTMX clients get a tiny fragment they can swap into their target;
        // browsers get the full page.
        if (($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {
            echo '<div class="slots-empty"><p>Too many requests. Please slow down.</p></div>';
            exit;
        }

        try {
            view('errors/429', [
                'title'         => 'Too many requests — Slate',
                'windowMinutes' => $minutes,
            ]);
        } catch (\Throwable) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "429 Too Many Requests\nTry again in {$minutes} minute(s).\n";
        }
        exit;
    }
}
