<?php
/**
 * app/views/booking/_slots.php
 *
 * HTMX fragment: time slot cards for a single date, grouped by morning /
 * afternoon. Each card carries an animation-delay so they cascade in.
 */

/** @var string                                                                  $date */
/** @var array<string,mixed>                                                     $service */
/** @var list<array{starts_at: \DateTimeImmutable, ends_at: \DateTimeImmutable}> $slots */
/** @var string                                                                  $dateLabel */

$morning   = [];
$afternoon = [];
foreach ($slots as $s) {
    if ((int) $s['starts_at']->format('H') < 12) {
        $morning[] = $s;
    } else {
        $afternoon[] = $s;
    }
}

/**
 * Render one slot card.
 * Defined as a local closure so morning/afternoon loops share the markup.
 */
$renderCard = static function (array $slot, int $index, array $service): void {
    $time    = $slot['starts_at']->format('H:i:s');
    $display = $slot['starts_at']->format('g:i A');
    $summary = $display . ' · ' . (string) $service['name'];
    $delay   = $index * 30;
    ?>
    <button type="button"
            class="slot-card"
            style="animation-delay: <?= (int) $delay ?>ms;"
            :class="{ 'is-selected': selectedSlot === '<?= e($time) ?>' }"
            @click="selectSlot('<?= e($time) ?>', '<?= e($summary) ?>')"
            data-time="<?= e($time) ?>"
            aria-label="Book <?= e($display) ?> for <?= e((string) $service['name']) ?>">
        <span class="slot-time"><?= e($display) ?></span>
        <span class="slot-meta">
            <span class="slot-duration"><?= (int) $service['duration_minutes'] ?> min</span>
            <span class="slot-sep" aria-hidden="true">·</span>
            <span class="slot-name"><?= e((string) $service['name']) ?></span>
        </span>
    </button>
    <?php
};
?>
<?php if ($slots === []): ?>
    <div class="slots-empty" role="status">
        <i data-lucide="calendar-x" aria-hidden="true"></i>
        <p>Fully booked. Try another day.</p>
    </div>
<?php else: ?>
    <?php $idx = 0; ?>

    <?php if ($morning !== []): ?>
        <div class="slot-group-divider" aria-hidden="true"><span>Morning</span></div>
        <div class="slot-list" role="list">
            <?php foreach ($morning as $slot): $renderCard($slot, $idx++, $service); endforeach ?>
        </div>
    <?php endif ?>

    <?php if ($afternoon !== []): ?>
        <div class="slot-group-divider" aria-hidden="true"><span>Afternoon</span></div>
        <div class="slot-list" role="list">
            <?php foreach ($afternoon as $slot): $renderCard($slot, $idx++, $service); endforeach ?>
        </div>
    <?php endif ?>
<?php endif ?>

<script>
    // Re-render Lucide icons that came in with this fragment.
    if (window.lucide) window.lucide.createIcons();
</script>
