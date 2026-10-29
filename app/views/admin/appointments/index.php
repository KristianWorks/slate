<?php
/**
 * app/views/admin/appointments/index.php
 *
 * Admin Appointments management. One Alpine component owns the entire
 * filter/search/view state and orchestrates HTMX swaps into #appts-main
 * (the list or calendar fragment) and #appt-panel-content (the slide-in).
 */

/** @var list<array<string,mixed>>           $services */
/** @var array<string,mixed>                 $filters */
/** @var string                              $currentMonth */
/** @var array<string,mixed>                 $main */
?>
<div class="appt-shell"
     x-data="apptsApp({
         view: '<?= e($filters['view']) ?>',
         status: '<?= e($filters['status']) ?>',
         serviceId: <?= (int) $filters['service_id'] ?>,
         q: '<?= e($filters['q']) ?>',
         page: <?= (int) $filters['page'] ?>,
         month: '<?= e($currentMonth) ?>'
     })"
     @keydown.window.escape="closePanel()">

    <!-- Top bar -->
    <header class="page-header" style="margin-bottom: 18px;">
        <div>
            <h1 class="page-title" style="margin-bottom: 4px;">Appointments</h1>
            <p class="page-sub" style="margin: 0;">Everything coming up, and everything that&rsquo;s been booked so far.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-outline" onclick="window.print()">
                <i data-lucide="printer" aria-hidden="true"></i>
                Print
            </button>
            <button type="button" class="btn btn-outline" @click="exportCsv()">
                <i data-lucide="download" aria-hidden="true"></i>
                Export CSV
            </button>
        </div>
    </header>

    <!-- Tabs -->
    <nav class="appt-tabs" role="tablist" aria-label="View">
        <?php
        $tabs = [
            'today'    => 'Today',
            'upcoming' => 'Upcoming',
            'all'      => 'All',
            'calendar' => 'Calendar',
        ];
        foreach ($tabs as $key => $label): ?>
            <button type="button" role="tab"
                    class="appt-tab"
                    :class="{ 'is-active': view === '<?= e($key) ?>' }"
                    :aria-selected="view === '<?= e($key) ?>' ? 'true' : 'false'"
                    @click="setView('<?= e($key) ?>')">
                <?= e($label) ?>
            </button>
        <?php endforeach ?>
    </nav>

    <!-- Filters + search -->
    <div class="appt-filters">
        <div class="filter-chips" role="group" aria-label="Status filter">
            <?php
            $statusChips = [
                'all'       => 'All',
                'confirmed' => 'Confirmed',
                'pending'   => 'Pending',
                'completed' => 'Completed',
                'no_show'   => 'No-show',
                'cancelled' => 'Cancelled',
            ];
            foreach ($statusChips as $key => $label): ?>
                <button type="button"
                        class="chip"
                        :class="{ 'is-active': status === '<?= e($key) ?>' }"
                        @click="setStatus('<?= e($key) ?>')">
                    <?= e($label) ?>
                </button>
            <?php endforeach ?>
        </div>

        <div class="filter-right">
            <select x-model.number="serviceId" @change="onFilterChange()" aria-label="Filter by service">
                <option value="0">All services</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $filters['service_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= e((string) $s['name']) ?>
                        <?= ((int) $s['is_active'] === 0) ? ' (inactive)' : '' ?>
                    </option>
                <?php endforeach ?>
            </select>

            <div class="search-wrap">
                <i data-lucide="search" aria-hidden="true"></i>
                <input type="search"
                       placeholder="Search name, email, or reference…"
                       x-model="q"
                       @input.debounce.300ms="onSearchChange()"
                       value="<?= e($filters['q']) ?>"
                       aria-label="Search">
            </div>
        </div>
    </div>

    <!-- Main content (list OR calendar) — initial render, then HTMX swaps -->
    <div id="appts-main" class="appt-main">
        <?php
        extract($main, EXTR_SKIP);
        require base_path('app/views/' . (string) $main['__partial'] . '.php');
        ?>
    </div>

    <!-- Slide-in panel -->
    <aside class="appt-panel"
           x-show="panelOpen"
           x-cloak
           x-transition:enter="panel-slide"
           x-transition:enter-start="panel-slide-from"
           x-transition:enter-end="panel-slide-to"
           x-transition:leave="panel-slide"
           x-transition:leave-start="panel-slide-to"
           x-transition:leave-end="panel-slide-from"
           aria-label="Appointment details">
        <button type="button" class="panel-close" @click="closePanel()" aria-label="Close">
            <i data-lucide="x"></i>
        </button>
        <div id="appt-panel-content">
            <div class="panel-empty">Loading&hellip;</div>
        </div>
    </aside>
</div>

<script>
function apptsApp(init) {
    return {
        view: init.view,
        status: init.status,
        serviceId: init.serviceId,
        q: init.q,
        page: init.page,
        month: init.month,
        openMenu: null,           // id of the row whose actions menu is open
        panelOpen: false,

        init() {
            document.body.addEventListener('htmx:afterSwap', () => {
                window.lucide && window.lucide.createIcons();
            });
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },

        get queryValues() {
            return {
                view:       this.view,
                status:     this.status,
                service_id: this.serviceId,
                q:          this.q,
                page:       this.page,
                month:      this.month,
            };
        },

        // ---- view + filter changes ----
        setView(v)   { if (this.view === v) return; this.view = v; this.page = 1; this.refreshMain(); },
        setStatus(s) { this.status = s; this.page = 1; this.refreshMain(); },
        onFilterChange() { this.page = 1; this.refreshMain(); },
        onSearchChange() { this.page = 1; this.refreshMain(); },
        movePage(delta)  { this.page = Math.max(1, this.page + delta); this.refreshMain(); },
        setMonth(m)      { this.month = m; this.refreshMain(); },

        refreshMain() {
            const url = this.view === 'calendar'
                ? '/admin/appointments/calendar'
                : '/admin/appointments/list';
            // Skeleton in immediately so the page feels responsive on slow networks.
            const main = document.getElementById('appts-main');
            if (main) {
                main.innerHTML = this.view === 'calendar'
                    ? '<div class="appt-calendar" aria-busy="true">'
                        + '<div class="skeleton skeleton-line lg" style="width:30%; margin-bottom:18px;"></div>'
                        + '<div class="skeleton" style="height:240px;"></div>'
                      + '</div>'
                    : '<div class="appt-list" aria-busy="true">'
                        + '<div class="skeleton-row"><div class="skeleton skeleton-line lg"></div><div class="skeleton skeleton-line sm"></div></div>'.repeat(5)
                      + '</div>';
            }
            htmx.ajax('GET', url, {
                target: '#appts-main',
                swap: 'innerHTML',
                values: this.queryValues,
            });
        },

        // ---- slide panel ----
        openPanel(id) {
            this.panelOpen = true;
            htmx.ajax('GET', '/admin/appointments/' + id + '/panel', {
                target: '#appt-panel-content',
                swap: 'innerHTML',
            });
        },
        openDay(date) {
            this.panelOpen = true;
            htmx.ajax('GET', '/admin/appointments/day/' + date, {
                target: '#appt-panel-content',
                swap: 'innerHTML',
            });
        },
        closePanel() { this.panelOpen = false; this.openMenu = null; },

        // ---- row actions ----
        actStatus(id, newStatus) {
            this.openMenu = null;
            htmx.ajax('POST', '/admin/appointments/' + id + '/status', {
                swap: 'none',
                values: { status: newStatus },
            }).then(() => {
                this.refreshMain();
                if (this.panelOpen) {
                    htmx.ajax('GET', '/admin/appointments/' + id + '/panel', {
                        target: '#appt-panel-content', swap: 'innerHTML',
                    });
                }
                const labels = {
                    completed: 'Marked completed.',
                    no_show:   'Marked no-show.',
                    cancelled: 'Appointment cancelled. Email queued.',
                    confirmed: 'Marked confirmed.',
                    pending:   'Marked pending.',
                };
                this.flash('success', labels[newStatus] || 'Status updated.');
            });
        },

        resendConfirmation(id) {
            this.openMenu = null;
            htmx.ajax('POST', '/admin/appointments/' + id + '/resend-confirmation', { swap: 'none' })
                .then(() => this.flash('success', 'Confirmation email queued.'))
                .catch(() => this.flash('error', 'Resend failed — check SMTP config.'));
        },

        // ---- CSV ----
        exportCsv() {
            const params = new URLSearchParams(this.queryValues);
            window.location.href = '/admin/appointments/export?' + params.toString();
        },

        // ---- toast ----
        flash(type, message) {
            window.dispatchEvent(new CustomEvent('flash', { detail: { type, message } }));
        },
    };
}
</script>
