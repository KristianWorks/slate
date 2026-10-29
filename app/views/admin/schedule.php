<?php
/**
 * app/views/admin/schedule.php
 *
 * Weekly recurring business hours. Single form, atomic save.
 */

/** @var array<int,array{day_of_week:int, open_time:?string, close_time:?string, is_closed:int}> $hours */
/** @var int $today  Carbon dayOfWeek (0=Sun..6=Sat) */

$dayNames = [
    0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
];
$displayOrder = [1, 2, 3, 4, 5, 6, 0]; // Mon → Sun
?>
<h1 class="page-title">Schedule</h1>
<p class="page-sub">Set your weekly opening hours. Toggle "Closed" for days you don&rsquo;t take bookings.</p>

<form action="/admin/schedule" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="sched-list">
        <?php foreach ($displayOrder as $d): $row = $hours[$d]; ?>
            <?php
            $isToday  = $d === $today;
            $closed   = (int) $row['is_closed'] === 1;
            $openErr  = errors("{$d}.open_time");
            $closeErr = errors("{$d}.close_time");
            ?>
            <div class="sched-row <?= $isToday ? 'is-today' : '' ?>"
                 x-data="{ closed: <?= $closed ? 'true' : 'false' ?> }">
                <div class="day-name">
                    <?= e($dayNames[$d]) ?>
                    <?php if ($isToday): ?>
                        <span class="muted" style="font-size: 0.8rem; font-family: var(--font-sans); font-weight: 400;">Today</span>
                    <?php endif ?>
                </div>

                <label class="toggle-inline">
                    <label class="toggle">
                        <input type="checkbox"
                               name="days[<?= $d ?>][is_closed]"
                               value="1"
                               x-model="closed"
                               <?= $closed ? 'checked' : '' ?>>
                        <span class="track"><span class="thumb"></span></span>
                    </label>
                    <span style="margin-left: 10px; font-size: 0.9rem;" x-text="closed ? 'Closed' : 'Open'"></span>
                </label>

                <div class="field" style="margin: 0;">
                    <input type="time"
                           name="days[<?= $d ?>][open_time]"
                           value="<?= e((string) ($row['open_time'] ?? '09:00')) ?>"
                           :disabled="closed"
                           :class="{ 'is-invalid': <?= $openErr !== null ? 'true' : 'false' ?> }">
                    <?php if ($openErr): ?>
                        <p class="field-error" x-show="!closed"><?= e($openErr) ?></p>
                    <?php endif ?>
                </div>

                <div class="field" style="margin: 0;">
                    <input type="time"
                           name="days[<?= $d ?>][close_time]"
                           value="<?= e((string) ($row['close_time'] ?? '18:00')) ?>"
                           :disabled="closed"
                           :class="{ 'is-invalid': <?= $closeErr !== null ? 'true' : 'false' ?> }">
                    <?php if ($closeErr): ?>
                        <p class="field-error" x-show="!closed"><?= e($closeErr) ?></p>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <div style="margin-top: 24px; display: flex; gap: 12px;">
        <button type="submit" class="btn btn-accent">Save schedule</button>
        <a href="/admin" class="btn btn-outline">Cancel</a>
    </div>
</form>
