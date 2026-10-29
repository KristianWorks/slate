<?php
/**
 * app/controllers/admin/LogController.php
 *
 * Admin log viewer.
 *
 *   GET /admin/logs           page chrome + initial tail of the latest log
 *   GET /admin/logs/tail      HTMX fragment, polled every 5s by the page
 *
 * Access:
 *   - In non-production environments → any admin.
 *   - In production → super-admins only (users.is_super = 1).
 *
 * Security:
 *   - Filename is whitelisted against `^app-\d{4}-\d{2}-\d{2}\.log$` so
 *     no directory traversal or arbitrary file reads.
 *   - The whole route is gated by require_super_or_dev().
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Logger;

final class LogController
{
    private const TAIL_LINES = 200;

    public function __construct()
    {
        require_super_or_dev();
    }

    /** GET /admin/logs */
    public function index(): void
    {
        $files = $this->listLogFiles();
        $current = (string) ($_GET['file'] ?? ($files[0] ?? ''));
        if (!$this->isValidFilename($current)) {
            $current = $files[0] ?? '';
        }

        view('admin/logs/index', [
            'title'   => 'Logs — Slate Admin',
            'active'  => 'logs',
            'files'   => $files,
            'current' => $current,
            'levels'  => ['ALL', 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'],
        ], 'admin');
    }

    /** GET /admin/logs/tail  (HTMX fragment) */
    public function tail(): void
    {
        $file  = (string) ($_GET['file']  ?? '');
        $level = strtoupper((string) ($_GET['level'] ?? 'ALL'));
        $q     = trim((string) ($_GET['q'] ?? ''));

        if (!$this->isValidFilename($file)) {
            view('admin/logs/_tail', ['lines' => [], 'error' => 'Invalid file.'], null);
            return;
        }

        $path = $this->logDir() . DIRECTORY_SEPARATOR . $file;
        $lines = $this->tailLines($path, self::TAIL_LINES);

        // Filter by level + search. Substring matches are fine — log lines
        // are well-formed and the search box is for finding a ref ID anyway.
        if ($level !== 'ALL') {
            $needle = '[' . $level . ']';
            $lines = array_values(array_filter($lines, static fn(string $l) => str_contains($l, $needle)));
        }
        if ($q !== '') {
            $needle = $q; // case-sensitive — refs are uppercase hex, level tags are uppercase
            $lines = array_values(array_filter($lines, static fn(string $l) => str_contains($l, $needle)));
        }

        view('admin/logs/_tail', [
            'lines' => $lines,
            'error' => null,
        ], null);
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    private function logDir(): string
    {
        return rtrim(base_path('storage/logs'), '/\\');
    }

    /** @return list<string> filenames sorted newest → oldest */
    private function listLogFiles(): array
    {
        $files = glob($this->logDir() . DIRECTORY_SEPARATOR . 'app-*.log') ?: [];
        $names = [];
        foreach ($files as $f) {
            $name = basename($f);
            if ($this->isValidFilename($name)) {
                $names[] = $name;
            }
        }
        rsort($names); // YYYY-MM-DD sorts correctly as a string
        return $names;
    }

    private function isValidFilename(string $name): bool
    {
        return $name !== '' && preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $name) === 1;
    }

    /**
     * Read the last N non-empty lines from a file efficiently.
     * @return list<string>
     */
    private function tailLines(string $path, int $n): array
    {
        if (!is_file($path) || filesize($path) === 0) {
            return [];
        }

        try {
            $f = new \SplFileObject($path, 'r');
            $f->setFlags(\SplFileObject::DROP_NEW_LINE);
            $f->seek(PHP_INT_MAX);
            $totalLines = (int) $f->key();
            $start = max(0, $totalLines - $n);
            $f->seek($start);

            $out = [];
            while (!$f->eof()) {
                $line = $f->current();
                if ($line !== false && $line !== null) {
                    $line = (string) $line;
                    if (trim($line) !== '') {
                        $out[] = $line;
                    }
                }
                $f->next();
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
