<?php
/**
 * app/views/admin/blocked_dates.php
 *
 * Mini server-rendered month calendar. Clicking a day toggles a block.
 */

/** @var string                              $month        e.g. "2026-05" */
/** @var string                              $monthLabel   e.g. "May 2026" */
/** @var string                              $prevMonth */
/** @var string                              $nextMonth */
/** @var string                              $today */
/** @var list<\DateTimeImmutable>            $cells */
/** @var array<string,array<string,mixed>>   $blocked */
/** @var list<array<string,mixed>>           $blockedList */

$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>
<h1 class="page-title">Blocked dates</h1>
<p class="page-sub">Holidays, vacations, days off. Click a date to block or unblock it.</p>

<div class="cal-wrap">
    <header class="cal-header">
        <a href="?month=<?= e($prevMonth) ?>" class="btn btn-outline btn-sm" aria-label="Previous month">
            <i data-lucide="chevron-left"></i>
        </a>
        <h2 class="serif" style="margin: 0; font-size: 1.4rem;"><?= e($monthLabel) ?></h2>
        <a href="?month=<?= e($nextMonth) ?>" class="btn btn-outline btn-sm" aria-label="Next month">
            <i data-lucide="chevron-right"></i>
        </a>
    </header>

    <!-- Hidden form: cells submit through this one form (one fewer form-element per cell). -->
    <form id="cal-form" action="/admin/blocked-dates/toggle" method="post" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="date" id="cal-date">
        <input type="hidden" name="reason" id="cal-reason">
    </form>

    <div class="cal" role="grid" aria-label="<?= e($monthLabel) ?>">
        <?php foreach ($dayNames as $name): ?>
            <div class="cal-day-name" role="columnheader"><?= e($name) ?></div>
        <?php endforeach ?>

        <?php foreach ($cells as $cell):
            $d        = $cell->format('Y-m-d');
            $inMonth  = $cell->format('Y-m') === $month;
            $isBlocked= isset($blocked[$d]);
            $isToday  = $d === $today;
        ?>
            <?php if (!$inMonth): ?>
                <span class="cal-cell is-other-month"><?= (int) $cell->format('j') ?></span>
            <?php else: ?>
                <button type="button"
                        class="cal-cell <?= $isBlocked ? 'is-blocked' : '' ?> <?= $isToday ? 'is-today' : '' ?>"
                        title="<?= $isBlocked ? 'Unblock' : 'Block' ?> <?= e($d) ?>"
                        aria-pressed="<?= $isBlocked ? 'true' : 'false' ?>"
                        onclick="document.getElementById('cal-date').value='<?= e($d) ?>'; document.getElementById('cal-form').submit();">
                    <?= (int) $cell->format('j') ?>
                </button>
            <?php endif ?>
        <?php endforeach ?>
    </div>
</div>

<section class="card" style="padding: 24px; margin-top: 32px;">
    <h2 class="serif" style="margin: 0 0 8px; font-size: 1.2rem;">Add with a reason</h2>
    <p class="muted" style="margin: 0 0 16px;">Block a specific date and note why (helpful for the team).</p>
    <form action="/admin/blocked-dates/toggle" method="post" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <div class="col-md-4">
            <div class="field" style="margin: 0;">
                <label for="bd-date">Date</label>
                <input type="date" id="bd-date" name="date" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="field" style="margin: 0;">
                <label for="bd-reason">Reason (optional)</label>
                <input type="text" id="bd-reason" name="reason" maxlength="255" placeholder="e.g. Public holiday">
            </div>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-accent">Block</button>
        </div>
    </form>
</section>

<section style="margin-top: 32px;">
    <h2 class="serif" style="margin: 0 0 12px; font-size: 1.2rem;">All blocked dates</h2>

    <?php if ($blockedList === []): ?>
        <div class="empty-state" style="padding: 32px;">
            <p class="muted" style="margin: 0;">No blocked dates yet.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Date</th><th>Reason</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($blockedList as $row): ?>
                        <tr>
                            <td><strong><?= e((string) $row['date']) ?></strong></td>
                            <td><?= e((string) ($row['reason'] ?? '')) ?: '<span class="muted">—</span>' ?></td>
                            <td class="text-end">
                                <form action="/admin/blocked-dates/<?= (int) $row['id'] ?>/delete" method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-danger-outline"
                                            onclick="return confirm('Unblock <?= e((string) $row['date']) ?>?')">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
