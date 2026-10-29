<?php
/**
 * app/views/booking/cancel_confirm.php
 *
 * Confirmation dialog before destroying an appointment. Two paths:
 *   - "Yes, cancel" POSTs to /booking/{token}/cancel (CSRF-protected)
 *   - "Keep my appointment" returns to /booking/{token}
 *   - "Reschedule instead" jumps to /booking/{token}/reschedule
 */

/** @var array<string,mixed>     $appt */
/** @var \DateTimeImmutable      $startsAt */
/** @var string                  $token */
?>
<section class="confirm-shell">
    <h1 class="confirm-h1">Cancel this appointment?</h1>
    <p class="confirm-sub">This can&rsquo;t be undone, but you can always book again.</p>

    <article class="confirm-card">
        <div class="confirm-row">
            <span class="label">Service</span>
            <span class="value">
                <span class="color-dot" aria-hidden="true"
                      style="background-color: <?= e((string) $appt['service_color']) ?>; margin-right: 8px;"></span>
                <?= e((string) $appt['service_name']) ?>
            </span>
        </div>
        <div class="confirm-row">
            <span class="label">Date</span>
            <span class="value"><?= e($startsAt->format('l, F j, Y')) ?></span>
        </div>
        <div class="confirm-row">
            <span class="label">Time</span>
            <span class="value">
                <?= e($startsAt->format('g:i A')) ?>
                <span class="muted"> &middot; <?= (int) $appt['service_duration'] ?> min</span>
            </span>
        </div>
    </article>

    <div class="confirm-actions">
        <form action="/booking/<?= e($token) ?>/cancel" method="post" style="margin: 0;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Yes, cancel this appointment</button>
        </form>
        <a href="/booking/<?= e($token) ?>" class="btn btn-outline">Keep my appointment</a>
    </div>

    <p class="confirm-footnote">
        Changed your mind? <a href="/booking/<?= e($token) ?>/reschedule">Reschedule instead</a>.
    </p>
</section>
