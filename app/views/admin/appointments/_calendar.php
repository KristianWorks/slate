<?php
/**
 * app/views/admin/appointments/_calendar.php
 *
 * Month grid for the Calendar view. Each day cell shows up to 3 chips
 * (service-coloured) + a "+N more" link that opens the day panel.
 */

/** @var string                                                    $month */
/** @var string                                                    $monthLabel */
/** @var string                                                    $prevMonth */
/** @var string                                                    $nextMonth */
/** @var list<\DateTimeImmutable>                                  $cells */
/** @var array<string, list<array<string,mixed>>>                  $byDate */
/** @var string                                                    $today */
/** @var string                                                    $tzName */

$tz = new \DateTimeZone($tzName);
?>
<div class="appt-calendar">
    <header class="appt-cal-header">
        <button type="button" class="cal-nav" @click="setMonth('<?= e($prevMonth) ?>')" aria-label="Previous month">
            <i data-lucide="chevron-left"></i>
        </button>
        <h2 class="cal-title"><?= e($monthLabel) ?></h2>
        <button type="button" class="cal-nav" @click="setMonth('<?= e($nextMonth) ?>')" aria-label="Next month">
            <i data-lucide="chevron-right"></i>
        </button>
    </header>

    <div class="appt-cal-dow" aria-hidden="true">
        <?php foreach (['SUN','MON','TUE','WED','THU','FRI','SAT'] as $d): ?>
            <div><?= e($d) ?></div>
        <?php endforeach ?>
    </div>

    <div class="appt-cal-grid" role="grid">
        <?php foreach ($cells as $cell):
            $d        = $cell->format('Y-m-d');
            $inMonth  = $cell->format('Y-m') === $month;
            $isToday  = $d === $today;
            $dayAppts = $byDate[$d] ?? [];
            $more     = max(0, count($dayAppts) - 3);
        ?>
            <div class="appt-cal-cell <?= $inMonth ? '' : 'is-other-month' ?> <?= $isToday ? 'is-today' : '' ?>"
                 role="gridcell">
                <div class="cell-head">
                    <span class="cell-date"><?= (int) $cell->format('j') ?></span>
                    <?php if ($inMonth && $dayAppts !== []): ?>
                        <button type="button"
                                class="cell-day-link"
                                @click="openDay('<?= e($d) ?>')"
                                title="See all <?= count($dayAppts) ?> on this day"
                                aria-label="See all appointments on <?= e($cell->format('l, F j')) ?>">
                            <?= count($dayAppts) ?>
                        </button>
                    <?php endif ?>
                </div>

                <?php if ($inMonth): ?>
                    <?php foreach (array_slice($dayAppts, 0, 3) as $appt):
                        $apptStart = new \DateTimeImmutable((string) $appt['starts_at'], $tz);
                        $firstName = preg_split('/\s+/', trim((string) $appt['customer_name']))[0] ?? '';
                        $statusKey = (string) $appt['status'];
                        $dim       = in_array($statusKey, ['cancelled', 'no_show'], true);
                    ?>
                        <button type="button"
                                class="appt-chip <?= $dim ? 'is-dim' : '' ?>"
                                style="--svc-color: <?= e((string) $appt['service_color']) ?>;"
                                @click.stop="openPanel(<?= (int) $appt['id'] ?>)"
                                title="<?= e($firstName) ?> · <?= e($apptStart->format('g:i A')) ?> · <?= e((string) $appt['service_name']) ?>">
                            <span class="chip-time"><?= e($apptStart->format('g:i')) ?></span>
                            <span class="chip-name"><?= e(mb_strimwidth($firstName, 0, 10, '…')) ?></span>
                        </button>
                    <?php endforeach ?>

                    <?php if ($more > 0): ?>
                        <button type="button"
                                class="cell-more"
                                @click.stop="openDay('<?= e($d) ?>')">
                            + <?= (int) $more ?> more
                        </button>
                    <?php endif ?>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
</div>
