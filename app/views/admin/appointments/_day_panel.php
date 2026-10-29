<?php
/**
 * app/views/admin/appointments/_day_panel.php
 *
 * Slide-panel content: all appointments on a given day. Each entry opens
 * the single-appointment panel when clicked.
 */

/** @var string                       $date */
/** @var string                       $dateLabel */
/** @var list<array<string,mixed>>    $rows */
/** @var string                       $tzName */

$tz = new \DateTimeZone($tzName);
$statusLabels = [
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed',
    'no_show'   => 'No-show',
];
?>
<div class="panel-content">
    <header class="panel-head">
        <div>
            <h2 class="panel-title"><?= e($dateLabel) ?></h2>
            <p class="panel-subtitle">
                <?= count($rows) ?> appointment<?= count($rows) === 1 ? '' : 's' ?>
            </p>
        </div>
    </header>

    <?php if ($rows === []): ?>
        <div class="panel-empty">No appointments on this day.</div>
    <?php else: ?>
        <ul class="day-list" role="list">
            <?php foreach ($rows as $r):
                $apptId    = (int) $r['id'];
                $starts    = new \DateTimeImmutable((string) $r['starts_at'], $tz);
                $statusKey = (string) $r['status'];
            ?>
                <li>
                    <button type="button"
                            class="day-item"
                            style="--svc-color: <?= e((string) $r['service_color']) ?>;"
                            @click="openPanel(<?= $apptId ?>)">
                        <div class="day-time"><?= e($starts->format('g:i A')) ?></div>
                        <div class="day-main">
                            <div class="day-name"><?= e((string) $r['customer_name']) ?></div>
                            <div class="day-meta">
                                <?= e((string) $r['service_name']) ?> &middot;
                                <?= (int) $r['service_duration'] ?> min
                            </div>
                        </div>
                        <span class="status-pill status-<?= e($statusKey) ?>">
                            <?= e($statusLabels[$statusKey] ?? $statusKey) ?>
                        </span>
                    </button>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</div>
