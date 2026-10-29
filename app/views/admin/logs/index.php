<?php
/**
 * app/views/admin/logs/index.php
 *
 * Log viewer. Filter + search are pure Alpine state; refresh runs every 5s
 * via setInterval so the tail block stays current while the user reads.
 */

/** @var list<string> $files */
/** @var string       $current */
/** @var list<string> $levels */
?>
<h1 class="page-title">Logs</h1>
<p class="page-sub">
    Tail the application log. Auto-refreshes every 5 seconds.
    Showing the last 200 lines per file.
</p>

<?php if ($files === []): ?>
    <section class="empty-state">
        <h3>No logs yet</h3>
        <p>Logs will appear once the app records its first event.</p>
    </section>
<?php else: ?>
    <section class="card logs-shell"
             style="padding: 20px 24px;"
             x-data="logsApp({
                 file:    <?= json_encode($current, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                 level:   'ALL',
                 q:       ''
             })"
             x-init="init()">

        <div class="logs-filters">
            <div class="field" style="margin: 0; flex: 1; min-width: 220px;">
                <label for="log-file">File</label>
                <select id="log-file" x-model="file" @change="refresh()">
                    <?php foreach ($files as $f): ?>
                        <option value="<?= e($f) ?>"><?= e($f) ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="field" style="margin: 0; width: 160px;">
                <label for="log-level">Level</label>
                <select id="log-level" x-model="level" @change="refresh()">
                    <?php foreach ($levels as $l): ?>
                        <option value="<?= e($l) ?>"><?= e($l === 'ALL' ? 'All levels' : ucfirst(strtolower($l))) ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="field" style="margin: 0; flex: 1; min-width: 220px;">
                <label for="log-q">Search (ref ID or substring)</label>
                <input id="log-q" type="search" x-model="q" @input.debounce.300ms="refresh()" placeholder="e.g. A3B4C5D6">
            </div>

            <div style="display: flex; align-items: flex-end; gap: 8px;">
                <span class="logs-indicator" :class="{ 'is-fresh': fresh }" aria-live="polite">
                    <span class="logs-indicator-dot"></span>
                    <span x-text="lastUpdate"></span>
                </span>
                <button type="button" class="btn btn-outline btn-sm" @click="refresh(true)">
                    <i data-lucide="rotate-cw"></i> Refresh
                </button>
            </div>
        </div>

        <div id="log-tail" class="log-tail">
            <div class="muted" style="padding: 24px; text-align: center;">Loading…</div>
        </div>
    </section>
<?php endif ?>

<script>
function logsApp(init) {
    return {
        file: init.file,
        level: init.level,
        q: init.q,
        timer: null,
        fresh: false,
        lastUpdate: 'Loading…',

        init() {
            this.refresh();
            this.timer = setInterval(() => this.refresh(), 5000);
        },

        refresh(manual = false) {
            htmx.ajax('GET', '/admin/logs/tail', {
                target: '#log-tail',
                swap:   'innerHTML',
                values: { file: this.file, level: this.level, q: this.q },
            }).then(() => {
                this.lastUpdate = 'Updated ' + new Date().toLocaleTimeString();
                this.fresh = true;
                setTimeout(() => this.fresh = false, 600);
            });
        },
    };
}
</script>
