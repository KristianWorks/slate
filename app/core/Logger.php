<?php
/**
 * app/core/Logger.php
 *
 * Small PSR-3-style logger. Writes to /storage/logs/app-YYYY-MM-DD.log
 * with line-locked appends. Cheap probabilistic rotation cleans entries
 * older than 30 days.
 *
 * Usage (via the global helper):
 *
 *   logger()->info('User signed in', ['user_id' => 42]);
 *   logger()->warning('CSRF mismatch', ['ip' => $_SERVER['REMOTE_ADDR']]);
 *   logger()->error('Email send failed', ['type' => 'confirmation', 'to' => $to]);
 *
 * In production (APP_ENV=production), debug + info are dropped before
 * touching the disk — only warning, error, critical are persisted.
 */

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public const LEVEL_DEBUG    = 'DEBUG';
    public const LEVEL_INFO     = 'INFO';
    public const LEVEL_WARNING  = 'WARNING';
    public const LEVEL_ERROR    = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';

    /** @var array<string,int> */
    private const RANK = [
        'DEBUG'    => 0,
        'INFO'     => 1,
        'WARNING'  => 2,
        'ERROR'    => 3,
        'CRITICAL' => 4,
    ];

    private const RETENTION_DAYS = 30;

    private static ?self $instance = null;

    private readonly string $logDir;
    private readonly int $minRank;

    public function __construct(string $logDir, bool $isProduction)
    {
        $this->logDir  = rtrim($logDir, "/\\");
        $this->minRank = $isProduction ? self::RANK[self::LEVEL_WARNING] : self::RANK[self::LEVEL_DEBUG];

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }
    }

    public static function init(string $logDir, bool $isProduction): void
    {
        self::$instance = new self($logDir, $isProduction);
    }

    /**
     * Lazy fallback — if init() wasn't called yet (very early in bootstrap),
     * spin up a Logger pointing at /storage/logs with sensible defaults so
     * callers don't have to guard every site.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $base = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : sys_get_temp_dir();
            self::$instance = new self($base, ($_ENV['APP_ENV'] ?? 'production') === 'production');
        }
        return self::$instance;
    }

    /** @param array<string,mixed> $context */
    public function debug(string $msg, array $context = []): void    { $this->log(self::LEVEL_DEBUG,    $msg, $context); }
    /** @param array<string,mixed> $context */
    public function info(string $msg, array $context = []): void     { $this->log(self::LEVEL_INFO,     $msg, $context); }
    /** @param array<string,mixed> $context */
    public function warning(string $msg, array $context = []): void  { $this->log(self::LEVEL_WARNING,  $msg, $context); }
    /** @param array<string,mixed> $context */
    public function error(string $msg, array $context = []): void    { $this->log(self::LEVEL_ERROR,    $msg, $context); }
    /** @param array<string,mixed> $context */
    public function critical(string $msg, array $context = []): void { $this->log(self::LEVEL_CRITICAL, $msg, $context); }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);
        if (!isset(self::RANK[$level])) {
            return;
        }
        if (self::RANK[$level] < $this->minRank) {
            return;
        }

        $line = sprintf(
            "[%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            self::sanitize($message),
            $context === [] ? '' : ' ' . (string) json_encode(
                self::sanitizeContext($context),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            ),
        );

        $file = $this->logDir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';

        // flock-locked append: prevents interleaved writes from concurrent
        // requests on multi-process FPM / Apache prefork setups.
        $fp = @fopen($file, 'a');
        if ($fp !== false) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        } else {
            // Disk full / dir unwritable — fall back to PHP error_log so we
            // never silently drop a critical message.
            error_log('[Slate-Logger] ' . trim($line));
        }

        // Cheap rotation: ~1% of writes do the cleanup. For a busy app this
        // still runs many times per day; for a quiet app it happens often
        // enough that nothing piles up unboundedly.
        try {
            if (random_int(1, 100) === 1) {
                $this->gc();
            }
        } catch (\Throwable) {
            // never let the logger itself throw
        }
    }

    private function gc(): void
    {
        $cutoff = time() - (self::RETENTION_DAYS * 86_400);
        $files = glob($this->logDir . DIRECTORY_SEPARATOR . 'app-*.log') ?: [];
        foreach ($files as $f) {
            if (@filemtime($f) < $cutoff) {
                @unlink($f);
            }
        }
    }

    /** Strip CR/LF from log messages so a multi-line input can't fake new log entries. */
    private static function sanitize(string $value): string
    {
        return str_replace(["\r", "\n"], [' ', ' '], $value);
    }

    /**
     * Recursively scrub CRLF + truncate very large values so a single bad
     * context blob can't blow up the log file.
     *
     * @param  array<int|string,mixed> $context
     * @return array<int|string,mixed>
     */
    private static function sanitizeContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if (is_string($v)) {
                $v = self::sanitize($v);
                if (strlen($v) > 4_000) {
                    $v = substr($v, 0, 4_000) . '…[truncated]';
                }
                $out[$k] = $v;
            } elseif (is_array($v)) {
                $out[$k] = self::sanitizeContext($v);
            } elseif (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            } else {
                $out[$k] = '[' . (is_object($v) ? get_class($v) : gettype($v)) . ']';
            }
        }
        return $out;
    }
}
