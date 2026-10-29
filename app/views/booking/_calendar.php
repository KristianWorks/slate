<?php
/**
 * app/views/booking/_calendar.php
 *
 * The 42-cell month grid. Rendered both on initial page load (inside the
 * main view) and again as an HTMX fragment when the user navigates months
 * or switches services.
 */

/** @var string                              $month */
/** @var string                              $today */
/** @var list<\DateTimeImmutable>            $cells */
/** @var array<int,array<string,mixed>>      $hoursByDow */
/** @var array<string,array<string,mixed>>   $blocked */
/** @var array<string,bool>                  $availability */
?>
<?php foreach ($cells as $cell):
    $d         = $cell->format('Y-m-d');
    $dow       = (int) $cell->format('w');
    $inMonth   = $cell->format('Y-m') === $month;
    $isPast    = $d < $today;
    $hours     = $hoursByDow[$dow] ?? null;
    $isClosed  = $hours === null
                || (int) $hours['is_closed'] === 1
                || $hours['open_time']  === null
                || $hours['close_time'] === null;
    $isBlocked = isset($blocked[$d]);
    $hasAvail  = !empty($availability[$d]);
    $isToday   = $d === $today;
    $clickable = $inMonth && !$isPast && !$isClosed && !$isBlocked && $hasAvail;

    $classes = array_filter([
        'book-day',
        !$inMonth   ? 'is-other'   : '',
        $isPast     ? 'is-past'    : '',
        $isClosed   ? 'is-closed'  : '',
        $isBlocked  ? 'is-blocked' : '',
        $isToday    ? 'is-today'   : '',
        $hasAvail   ? 'has-avail'  : '',
    ]);

    $longLabel  = $cell->format('l, F j Y');
    $shortLabel = $cell->format('l, F j');
    $stateSuffix = match (true) {
        $isBlocked            => ', blocked',
        $isClosed             => ', closed',
        $isPast               => ', past',
        $inMonth && !$hasAvail=> ', fully booked',
        default               => '',
    };
?>
    <button type="button"
            class="<?= e(implode(' ', $classes)) ?>"
            :class="{ 'is-selected': selectedDate === '<?= e($d) ?>' }"
            data-date="<?= e($d) ?>"
            role="gridcell"
            <?php if ($clickable): ?>
                @click="selectDay('<?= e($d) ?>', '<?= e($shortLabel) ?>')"
                aria-label="<?= e($longLabel) ?>, available"
                tabindex="0"
            <?php else: ?>
                disabled
                aria-label="<?= e($longLabel . $stateSuffix) ?>"
                tabindex="-1"
            <?php endif ?>>
        <span class="d-num"><?= (int) $cell->format('j') ?></span>
        <?php if ($hasAvail && $inMonth && !$isPast): ?>
            <span class="d-dot" aria-hidden="true"></span>
        <?php endif ?>
        <?php if ($isBlocked && $inMonth && !$isPast): ?>
            <svg class="d-x" viewBox="0 0 12 12" aria-hidden="true">
                <path d="M3 3 L9 9 M9 3 L3 9"
                      stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        <?php endif ?>
    </button>
<?php endforeach ?>
