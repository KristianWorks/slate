<?php
/**
 * app/views/admin/appointments/_panel.php
 *
 * Slide-in detail panel for a single appointment. Notes textarea auto-saves
 * to /admin/appointments/{id}/notes with HTMX after a 600ms debounce.
 */

/** @var array<string,mixed>     $appt */
/** @var \DateTimeImmutable      $starts */
/** @var \DateTimeImmutable      $ends */
/** @var string                  $reference */
/** @var string                  $tzName */

$id        = (int) $appt['id'];
$statusKey = (string) $appt['status'];
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
            <h2 class="panel-title"><?= e((string) $appt['customer_name']) ?></h2>
            <p class="panel-subtitle">
                Booked <?= e((new \DateTimeImmutable((string) $appt['created_at']))->format('M j, Y')) ?>
                &middot; Ref <span class="panel-ref"><?= e($reference) ?></span>
            </p>
        </div>
        <span class="status-pill status-<?= e($statusKey) ?>">
            <?= e($statusLabels[$statusKey] ?? $statusKey) ?>
        </span>
    </header>

    <section class="panel-section">
        <div class="panel-row">
            <span class="panel-label">When</span>
            <span class="panel-value">
                <strong><?= e($starts->format('l, F j, Y')) ?></strong><br>
                <?= e($starts->format('g:i A')) ?> &mdash; <?= e($ends->format('g:i A')) ?>
                <span class="muted">(<?= e($tzName) ?>)</span>
            </span>
        </div>
        <div class="panel-row">
            <span class="panel-label">Service</span>
            <span class="panel-value">
                <span class="color-dot" style="background-color: <?= e((string) $appt['service_color']) ?>;" aria-hidden="true"></span>
                <?= e((string) $appt['service_name']) ?>
                <span class="muted">&middot; <?= (int) $appt['service_duration'] ?> min &middot; &#8369;<?= e(number_format((float) $appt['service_price'], 2)) ?></span>
            </span>
        </div>
    </section>

    <section class="panel-section">
        <h3 class="panel-h3">Customer</h3>
        <div class="panel-row">
            <span class="panel-label">Email</span>
            <span class="panel-value">
                <a href="mailto:<?= e((string) $appt['customer_email']) ?>"><?= e((string) $appt['customer_email']) ?></a>
            </span>
        </div>
        <div class="panel-row">
            <span class="panel-label">Phone</span>
            <span class="panel-value">
                <a href="tel:<?= e((string) $appt['customer_phone']) ?>"><?= e((string) $appt['customer_phone']) ?></a>
            </span>
        </div>
    </section>

    <section class="panel-section">
        <div class="notes-block" id="notes-block-<?= $id ?>">
            <div class="notes-head">
                <label for="appt-notes-<?= $id ?>">Internal notes</label>
                <span class="notes-status" id="notes-status-<?= $id ?>" aria-live="polite"></span>
            </div>
            <textarea id="appt-notes-<?= $id ?>"
                      name="notes"
                      class="notes-textarea"
                      rows="5"
                      maxlength="5000"
                      placeholder="Anything the team should know about this booking…"
                      hx-post="/admin/appointments/<?= $id ?>/notes"
                      hx-trigger="input changed delay:600ms"
                      hx-include="this"
                      hx-swap="none"><?= e((string) ($appt['notes'] ?? '')) ?></textarea>
        </div>
    </section>

    <section class="panel-actions">
        <a class="btn btn-outline btn-sm"
           href="/booking/<?= e((string) $appt['cancel_token']) ?>"
           target="_blank" rel="noopener noreferrer">
            <i data-lucide="external-link"></i> Open customer view
        </a>
        <button type="button" class="btn btn-outline btn-sm"
                @click="resendConfirmation(<?= $id ?>)">
            <i data-lucide="send"></i> Resend confirmation
        </button>

        <div class="panel-status-row">
            <span class="muted">Change status:</span>
            <?php foreach (['confirmed', 'completed', 'no_show', 'cancelled'] as $opt):
                if ($opt === $statusKey) continue;
                $label = $statusLabels[$opt];
            ?>
                <button type="button"
                        class="btn btn-outline btn-sm <?= $opt === 'cancelled' ? 'btn-danger-outline' : '' ?>"
                        @click="actStatus(<?= $id ?>, '<?= e($opt) ?>')">
                    <?= e($label) ?>
                </button>
            <?php endforeach ?>
        </div>
    </section>
</div>

<script>
(function () {
    const t = document.getElementById('appt-notes-<?= $id ?>');
    const s = document.getElementById('notes-status-<?= $id ?>');
    if (!t || !s) return;
    let clearTimer = null;
    t.addEventListener('htmx:beforeRequest', () => {
        if (clearTimer) clearTimeout(clearTimer);
        s.textContent = 'Saving…';
        s.className   = 'notes-status is-saving';
    });
    t.addEventListener('htmx:afterRequest', (e) => {
        const ok = e.detail && e.detail.successful;
        s.textContent = ok ? 'Saved' : 'Save failed';
        s.className   = 'notes-status ' + (ok ? 'is-saved' : 'is-failed');
        if (ok) clearTimer = setTimeout(() => { s.textContent = ''; s.className = 'notes-status'; }, 1500);
    });
})();
</script>
