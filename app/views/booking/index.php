<?php
/**
 * app/views/booking/index.php
 *
 * Split-view booking page. Two modes:
 *
 *   $mode = 'book'        → after the user picks a slot, the right panel
 *                            slides to a booking form.
 *   $mode = 'reschedule'  → the same calendar shown over an existing booking;
 *                            "Continue" submits a hidden form that moves the
 *                            appointment instead of opening the booking form.
 *
 * If the previous POST failed validation, we re-open the form with the
 * user's prior inputs restored (selectedDate / selectedSlot pulled from
 * $_SESSION['_old']).
 */

/** @var list<array<string,mixed>>          $services */
/** @var array<string,mixed>                $service */
/** @var string                             $month */
/** @var string                             $monthLabel */
/** @var string                             $today */
/** @var list<\DateTimeImmutable>           $cells */
/** @var array<int,array<string,mixed>>     $hoursByDow */
/** @var array<string,array<string,mixed>>  $blocked */
/** @var array<string,bool>                 $availability */
/** @var string                             $businessTz */
/** @var string                             $mode               'book' | 'reschedule' */
/** @var ?string                            $rescheduleToken */
/** @var ?array<string,mixed>               $rescheduleAppt */
/** @var ?string                            $rescheduleOldLabel */

$mode               = $mode ?? 'book';
$rescheduleToken    = $rescheduleToken ?? null;
$rescheduleAppt     = $rescheduleAppt ?? null;
$rescheduleOldLabel = $rescheduleOldLabel ?? null;

// Re-open the booking form (with prior inputs) if the last POST failed.
$hasFormErrors       = !empty($_SESSION['_errors'] ?? null);
$initialView         = ($mode === 'book' && $hasFormErrors) ? 'form' : 'slots';
$initialSelectedDate = $_SESSION['_old']['date'] ?? null;
$initialSelectedSlot = $_SESSION['_old']['time'] ?? null;
$initialServiceId    = isset($_SESSION['_old']['service_id'])
                       ? (int) $_SESSION['_old']['service_id']
                       : (int) $service['id'];
?>
<div class="book-shell"
     x-data="bookingApp({
         mode: '<?= e($mode) ?>',
         defaultServiceId: <?= (int) $initialServiceId ?>,
         initialMonth: '<?= e($month) ?>',
         initialMonthLabel: '<?= e($monthLabel) ?>',
         today: '<?= e($today) ?>',
         initialView: '<?= e($initialView) ?>',
         initialSelectedDate: <?= $initialSelectedDate ? "'" . e($initialSelectedDate) . "'" : 'null' ?>,
         initialSelectedSlot: <?= $initialSelectedSlot ? "'" . e($initialSelectedSlot) . "'" : 'null' ?>
     })"
     @keydown.window.escape.prevent="onEscape()">

    <header class="book-header">
        <?php if ($mode === 'reschedule'): ?>
            <h1 class="display-h1">Reschedule your appointment</h1>
            <p class="display-sub">Pick a new date and time. We&rsquo;ll email you a fresh confirmation.</p>
        <?php else: ?>
            <h1 class="display-h1">Book an appointment</h1>
            <p class="display-sub">Pick a service, choose a date, then a time.</p>
        <?php endif ?>
    </header>

    <?php if ($mode === 'reschedule' && $rescheduleOldLabel !== null): ?>
        <div class="reschedule-banner" role="status">
            <i data-lucide="calendar-clock" aria-hidden="true"></i>
            <div>
                <strong>Currently scheduled for <?= e($rescheduleOldLabel) ?></strong>
                <div class="muted">Pick a new slot below to move this appointment.</div>
            </div>
            <a href="/booking/<?= e((string) $rescheduleToken) ?>" class="btn btn-outline btn-sm">
                Keep current
            </a>
        </div>
    <?php endif ?>

    <!-- Service pills -->
    <div class="service-pills" role="tablist" aria-label="Service">
        <?php foreach ($services as $s): ?>
            <button type="button"
                    role="tab"
                    class="pill"
                    :class="{ 'is-active': selectedServiceId === <?= (int) $s['id'] ?> }"
                    :aria-selected="selectedServiceId === <?= (int) $s['id'] ?> ? 'true' : 'false'"
                    @click="selectService(<?= (int) $s['id'] ?>)">
                <span class="pill-dot" aria-hidden="true"
                      style="background-color: <?= e((string) $s['color']) ?>;"></span>
                <span class="pill-name"><?= e((string) $s['name']) ?></span>
                <span class="pill-meta">&middot; <?= (int) $s['duration_minutes'] ?>m</span>
            </button>
        <?php endforeach ?>
    </div>

    <div class="book-split">
        <!-- ============================================================
             LEFT: Calendar (always visible)
             ============================================================ -->
        <aside class="book-cal"
               aria-label="Date picker"
               @keydown.arrow-left.prevent.stop="moveFocus(-1)"
               @keydown.arrow-right.prevent.stop="moveFocus(1)"
               @keydown.arrow-up.prevent.stop="moveFocus(-7)"
               @keydown.arrow-down.prevent.stop="moveFocus(7)">

            <header class="book-cal-header">
                <button type="button" class="cal-nav"
                        @click="navigateMonth(-1)"
                        aria-label="Previous month">
                    <i data-lucide="chevron-left"></i>
                </button>
                <h2 class="cal-title" x-text="monthLabel"></h2>
                <button type="button" class="cal-nav"
                        @click="navigateMonth(1)"
                        aria-label="Next month">
                    <i data-lucide="chevron-right"></i>
                </button>
            </header>

            <div class="cal-dow-row" aria-hidden="true">
                <?php foreach (['SUN','MON','TUE','WED','THU','FRI','SAT'] as $dn): ?>
                    <div class="cal-dow"><?= e($dn) ?></div>
                <?php endforeach ?>
            </div>

            <div id="cal-grid"
                 class="cal-grid"
                 :style="`--slide-dir: ${slideDir}`"
                 role="grid"
                 aria-label="Calendar">
                <?php require __DIR__ . '/_calendar.php'; ?>
            </div>
        </aside>

        <!-- ============================================================
             RIGHT: Slots panel OR booking form
             ============================================================ -->
        <section class="book-slots" aria-label="Available times">
            <!-- SLOTS view -->
            <div x-show="view === 'slots'"
                 x-transition:enter="panel-enter"
                 x-transition:enter-start="panel-enter-from"
                 x-transition:enter-end="panel-enter-to">
                <header class="book-slots-header">
                    <h2 class="slots-title">Available times</h2>
                    <p class="slots-sub" x-text="selectedDateLabel || 'Choose a date'"></p>
                </header>

                <div id="slots-container" aria-live="polite">
                    <div class="slots-empty">
                        <i data-lucide="calendar" aria-hidden="true"></i>
                        <p>Select a date to see available times.</p>
                    </div>
                </div>

                <footer class="book-actions"
                        x-show="selectedSlot"
                        x-cloak
                        x-transition:enter="actions-enter"
                        x-transition:enter-start="actions-enter-from"
                        x-transition:enter-end="actions-enter-to">
                    <div class="book-actions-summary">
                        <span class="muted">Selected</span>
                        <strong x-text="selectedSummary"></strong>
                    </div>
                    <button type="button" class="btn btn-accent" @click="onContinue()">
                        <span x-text="mode === 'reschedule' ? 'Move to this time' : 'Continue →'"></span>
                    </button>
                </footer>
            </div>

            <!-- FORM view (book mode only) -->
            <?php if ($mode === 'book'): ?>
                <div x-show="view === 'form'"
                     x-cloak
                     x-transition:enter="panel-enter"
                     x-transition:enter-start="panel-enter-from-right"
                     x-transition:enter-end="panel-enter-to">
                    <?php require __DIR__ . '/_form.php'; ?>
                </div>
            <?php endif ?>
        </section>
    </div>

    <!-- Hidden reschedule form (reschedule mode only) -->
    <?php if ($mode === 'reschedule' && $rescheduleToken !== null): ?>
        <form id="reschedule-form"
              action="/booking/<?= e($rescheduleToken) ?>/reschedule"
              method="post"
              style="display: none;">
            <?= csrf_field() ?>
            <input type="hidden" name="service_id" :value="selectedServiceId">
            <input type="hidden" name="date"       :value="selectedDate">
            <input type="hidden" name="time"       :value="selectedSlot">
        </form>
    <?php endif ?>
</div>

<script>
function bookingApp(init) {
    return {
        // ---- mode ----
        mode: init.mode,

        // ---- state ----
        selectedServiceId: init.defaultServiceId,
        month: init.initialMonth,
        monthLabel: init.initialMonthLabel,
        today: init.today,
        slideDir: 1,
        view: init.initialView || 'slots',
        selectedDate: init.initialSelectedDate || null,
        selectedDateLabel: '',
        selectedSlot: init.initialSelectedSlot || null,
        selectedSummary: '',

        init() {
            document.body.addEventListener('htmx:afterSwap', () => {
                window.lucide && window.lucide.createIcons();
            });
            this.$nextTick(() => window.lucide && window.lucide.createIcons());

            // Restore summary text when re-opening the form after a server error.
            if (this.selectedDate && this.selectedSlot) {
                this.selectedDateLabel = this.formatDateLabel(this.selectedDate);
                this.selectedSummary   = this.formatTimeLabel(this.selectedSlot);
                // Pre-fetch the slots so going Back from the form shows them.
                this.fetchSlots();
            }
        },

        // ---- helpers ----
        formatDateLabel(dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
        },
        formatTimeLabel(t) {
            const [h, m] = t.split(':').map(Number);
            const hour12 = ((h + 11) % 12) + 1;
            return `${hour12}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
        },

        // ---- service pills ----
        selectService(id) {
            if (id === this.selectedServiceId) return;
            this.selectedServiceId = id;
            this.selectedSlot = null;
            this.selectedSummary = '';
            this.fetchCalendar();
            if (this.selectedDate) this.fetchSlots();
        },

        // ---- month nav ----
        navigateMonth(delta) {
            this.slideDir = delta;
            const [y, m] = this.month.split('-').map(Number);
            const d = new Date(y, m - 1 + delta, 1);
            this.month      = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            this.monthLabel = d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            this.fetchCalendar();
        },

        fetchCalendar() {
            htmx.ajax('GET', '/book/calendar', {
                target: '#cal-grid',
                swap: 'innerHTML swap:160ms settle:180ms',
                values: { month: this.month, service_id: this.selectedServiceId },
            });
        },

        // ---- day selection ----
        selectDay(dateStr, label) {
            this.selectedDate      = dateStr;
            this.selectedDateLabel = label;
            this.selectedSlot      = null;
            this.selectedSummary   = '';
            // Drop in a skeleton so the panel has shape while the slots fetch.
            const container = document.getElementById('slots-container');
            if (container) {
                container.innerHTML =
                    '<div class="slot-list" aria-busy="true" aria-label="Loading times">' +
                        '<div class="skeleton-slot"><div class="skeleton skeleton-line lg"></div><div class="skeleton skeleton-line sm"></div></div>'.repeat(4) +
                    '</div>';
            }
            this.fetchSlots();
        },

        fetchSlots() {
            if (!this.selectedDate) return;
            htmx.ajax('GET', '/api/slots', {
                target: '#slots-container',
                swap: 'innerHTML',
                values: { date: this.selectedDate, service_id: this.selectedServiceId },
            });
        },

        selectSlot(time, summary) {
            this.selectedSlot    = time;
            this.selectedSummary = summary;
        },

        // ---- Continue button: branch on mode ----
        onContinue() {
            if (!this.selectedSlot) return;
            if (this.mode === 'reschedule') {
                const f = document.getElementById('reschedule-form');
                if (f) f.submit();
                return;
            }
            this.view = 'form';
            // Move focus to the first form input for keyboard users.
            this.$nextTick(() => {
                const first = document.getElementById('bf-name');
                if (first) first.focus();
            });
        },

        // ---- Esc: peel back one layer at a time ----
        onEscape() {
            if (this.view === 'form') { this.view = 'slots'; return; }
            if (this.selectedSlot) {
                this.selectedSlot = null;
                this.selectedSummary = '';
                return;
            }
            if (this.selectedDate) {
                this.selectedDate = null;
                this.selectedDateLabel = '';
                document.getElementById('slots-container').innerHTML =
                    '<div class="slots-empty">' +
                    '<i data-lucide="calendar" aria-hidden="true"></i>' +
                    '<p>Select a date to see available times.</p>' +
                    '</div>';
                window.lucide && window.lucide.createIcons();
            }
        },

        // ---- keyboard nav within the grid ----
        moveFocus(delta) {
            const cells = Array.from(document.querySelectorAll('#cal-grid .book-day'));
            if (cells.length === 0) return;
            const active = document.activeElement;
            let idx = cells.indexOf(active);
            if (idx < 0) {
                const firstEnabled = cells.find(c => !c.disabled);
                if (firstEnabled) firstEnabled.focus();
                return;
            }
            let target = idx + delta;
            const step = delta > 0 ? 1 : -1;
            while (target >= 0 && target < cells.length) {
                if (!cells[target].disabled) { cells[target].focus(); return; }
                target += step;
            }
        },
    };
}
</script>
