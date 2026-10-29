<?php
/**
 * app/views/admin/logs/_tail.php
 *
 * HTMX fragment: rendered log lines, color-coded by level. Newest at the
 * bottom (matches `tail -f` mental model).
 */

/** @var list<string> $lines */
/** @var ?string      $error */

if (!empty($error)) { ?>
    <div class="muted" style="padding: 24px; text-align: center;"><?= e($error) ?></div>
    <?php return;
}

if ($lines === []) { ?>
    <div class="muted" style="padding: 24px; text-align: center;">No matching log entries.</div>
    <?php return;
}
?>
<div class="log-lines" role="log" aria-label="Log entries" aria-live="polite">
    <?php foreach ($lines as $line):
        $levelClass = 'log-level-info';
        if (preg_match('/\[(DEBUG|INFO|WARNING|ERROR|CRITICAL)\]/', $line, $m)) {
            $levelClass = 'log-level-' . strtolower($m[1]);
        }
    ?>
        <div class="log-line <?= e($levelClass) ?>"><?= e($line) ?></div>
    <?php endforeach ?>
</div>
