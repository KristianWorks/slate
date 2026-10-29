<?php
/**
 * app/views/admin/appointments/_list.php
 *
 * HTMX fragment: a list of appointment row cards + pagination.
 * Server-rendered initially inside index.php, swapped via HTMX afterwards.
 *
 * Children bind to the outer Alpine `apptsApp` scope via inline @click /
 * :class / x-show attributes — when HTMX injects fresh markup, Alpine's
 * MutationObserver picks it up and re-binds.
 */

/** @var list<array<string,mixed>> $rows */
/** @var int                       $total */
/** @var int                       $page */
/** @var int                       $totalPages */
/** @var string                    $view */
/** @var array<string,mixed>       $filters */
/** @var string                    $tzName */

$tz = new \DateTimeZone($tzName);

$statusLabels = [
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed',
    'no_show'   => 'No-show',
];

$emptyCopy = match (true) {
    ($filters['q'] ?? '') !== ''                                 => ['heading' => 'No matches', 'body' => 'No customer matched “' . $filters['q'] . '”.'],
    $view === 'today'    && ($filters['status'] ?? 'all') === 'all' => ['heading' => 'No appointments today', 'body' => 'Enjoy the quiet.'],
    $view === 'today'                                            => ['heading' => 'Nothing matches', 'body' => 'No appointments match these filters today.'],
    $view === 'upcoming'                                         => ['heading' => 'A quiet week ahead', 'body' => 'New bookings will show up here.'],
    $view === 'all'                                              => ['heading' => 'No appointments yet', 'body' => 'Once customers book, you&rsquo;ll see them here.'],
    default                                                      => ['heading' => 'Nothing to show', 'body' => 'Try a different filter.'],
};
?>

<?php if ($rows === []): ?>
    <div class="empty-state appt-empty">
        <svg class="empty-illu" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="48" cy="48" r="34" stroke="currentColor" stroke-width="2"/>
            <path d="M34 56 Q48 50 62 56" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>
            <circle cx="38" cy="42" r="2" fill="currentColor"/>
            <circle cx="58" cy="42" r="2" fill="currentColor"/>
        </svg>
        <h3><?= e($emptyCopy['heading']) ?></h3>
        <p><?= $emptyCopy['body'] /* contains intentional &rsquo; */ ?></p>
    </div>
<?php else: ?>
    <div class="appt-list">
        <?php foreach ($rows as $r):
            $id        = (int) $r['id'];
            $starts    = new \DateTimeImmutable((string) $r['starts_at'], $tz);
            $color     = (string) $r['service_color'];
            $statusKey = (string) $r['status'];
            $statusLbl = $statusLabels[$statusKey] ?? $statusKey;
        ?>
            <div class="appt-row"
                 style="--svc-color: <?= e($color) ?>;"
                 role="button"
                 tabindex="0"
                 @click="openPanel(<?= $id ?>)"
                 @keydown.enter.prevent="openPanel(<?= $id ?>)"
                 @keydown.space.prevent="openPanel(<?= $id ?>)">

                <div class="appt-when">
                    <div class="when-time"><?= e($starts->format('g:i A')) ?></div>
                    <div class="when-date"><?= e($starts->format('D · M j')) ?></div>
                </div>

                <div class="appt-customer">
                    <div class="cust-name"><?= e((string) $r['customer_name']) ?></div>
                    <div class="cust-contact"><?= e((string) $r['customer_email']) ?></div>
                    <div class="cust-contact"><?= e((string) $r['customer_phone']) ?></div>
                </div>

                <div class="appt-service">
                    <div class="svc-name"><?= e((string) $r['service_name']) ?></div>
                    <div class="svc-meta"><?= (int) $r['service_duration'] ?> min</div>
                </div>

                <div class="appt-status">
                    <span class="status-pill status-<?= e($statusKey) ?>"><?= e($statusLbl) ?></span>
                </div>

                <div class="appt-actions"
                     @click.stop
                     @keydown.stop
                     x-data>
                    <button type="button"
                            class="actions-toggle"
                            :class="{ 'is-open': openMenu === <?= $id ?> }"
                            @click="openMenu = (openMenu === <?= $id ?> ? null : <?= $id ?>)"
                            aria-label="Actions"
                            :aria-expanded="openMenu === <?= $id ?> ? 'true' : 'false'">
                        <i data-lucide="more-vertical"></i>
                    </button>
                    <div class="actions-menu"
                         x-show="openMenu === <?= $id ?>"
                         x-cloak
                         x-transition.opacity
                         @click.outside="if (openMenu === <?= $id ?>) openMenu = null">
                        <button type="button" class="menu-item" @click="openPanel(<?= $id ?>)">
                            <i data-lucide="eye"></i> View details
                        </button>
                        <?php if ($statusKey !== 'completed'): ?>
                            <button type="button" class="menu-item" @click="actStatus(<?= $id ?>, 'completed')">
                                <i data-lucide="check"></i> Mark completed
                            </button>
                        <?php endif ?>
                        <?php if ($statusKey !== 'no_show'): ?>
                            <button type="button" class="menu-item" @click="actStatus(<?= $id ?>, 'no_show')">
                                <i data-lucide="user-x"></i> Mark no-show
                            </button>
                        <?php endif ?>
                        <button type="button" class="menu-item" @click="resendConfirmation(<?= $id ?>)">
                            <i data-lucide="send"></i> Resend confirmation
                        </button>
                        <?php if ($statusKey !== 'cancelled'): ?>
                            <hr class="menu-sep">
                            <button type="button" class="menu-item is-danger" @click="actStatus(<?= $id ?>, 'cancelled')">
                                <i data-lucide="x-circle"></i> Cancel
                            </button>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <?php if ($view === 'all' && $totalPages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <button type="button" class="btn btn-outline btn-sm"
                    :disabled="page <= 1"
                    @click="movePage(-1)">
                <i data-lucide="chevron-left"></i> Previous
            </button>
            <span class="pagination-info">
                Page <strong><?= (int) $page ?></strong> of <?= (int) $totalPages ?>
                &middot; <?= (int) $total ?> total
            </span>
            <button type="button" class="btn btn-outline btn-sm"
                    :disabled="page >= <?= (int) $totalPages ?>"
                    @click="movePage(1)">
                Next <i data-lucide="chevron-right"></i>
            </button>
        </nav>
    <?php endif ?>
<?php endif ?>
