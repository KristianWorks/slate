<?php
/**
 * app/views/booking/confirmation.php
 *
 * Confirmation page. Renders one of two states based on appointment status:
 *   - confirmed  → "You're booked" + action buttons + GCal link
 *   - cancelled  → "Booking cancelled" + small note
 */

/** @var array<string,mixed>     $appt */
/** @var \DateTimeImmutable      $startsAt */
/** @var \DateTimeImmutable      $endsAt */
/** @var string                  $token */
/** @var string                  $reference */
/** @var string                  $gcalUrl */
/** @var string                  $outlookUrl */
/** @var string                  $icsUrl */
/** @var string                  $location */
/** @var string                  $businessName */
/** @var string                  $tzName */

$isCancelled = $appt['status'] === 'cancelled';
?>
<section class="confirm-shell">
    <?php if ($isCancelled): ?>
        <h1 class="confirm-h1">Booking cancelled</h1>
        <p class="confirm-sub">This appointment has been cancelled.</p>
    <?php else: ?>
        <h1 class="confirm-h1">You&rsquo;re booked</h1>
        <p class="confirm-sub">We&rsquo;ve sent the details to your email.</p>
    <?php endif ?>

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
        <?php if ($location !== ''): ?>
            <div class="confirm-row">
                <span class="label">Location</span>
                <span class="value"><?= e($location) ?></span>
            </div>
        <?php endif ?>
        <div class="confirm-row">
            <span class="label">Reference</span>
            <span class="value confirm-ref"><?= e($reference) ?></span>
        </div>
        <?php if (!empty($appt['notes'])): ?>
            <div class="confirm-row" style="flex-direction: column; align-items: flex-start; gap: 6px;">
                <span class="label">Notes</span>
                <span class="value" style="text-align: left; white-space: pre-wrap;"><?= e((string) $appt['notes']) ?></span>
            </div>
        <?php endif ?>
    </article>

    <?php if (!$isCancelled): ?>
        <div class="confirm-actions">
            <!-- Add-to-calendar dropdown: Google / Outlook / Apple-or-other (.ics) -->
            <div class="cal-dropdown"
                 x-data="{ open: false }"
                 @click.outside="open = false"
                 @keydown.escape.window="open = false">
                <button type="button"
                        class="btn btn-outline"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-haspopup="menu">
                    <i data-lucide="calendar-plus" aria-hidden="true"></i>
                    Add to calendar
                    <span class="cal-dropdown-caret" :class="{ 'is-open': open }" aria-hidden="true">&#9662;</span>
                </button>
                <div x-show="open"
                     x-cloak
                     x-transition:enter="cal-menu-enter"
                     x-transition:enter-start="cal-menu-from"
                     x-transition:enter-end="cal-menu-to"
                     class="cal-dropdown-menu"
                     role="menu">
                    <a href="<?= e($gcalUrl) ?>" target="_blank" rel="noopener noreferrer" role="menuitem">
                        <span class="cal-dropdown-name">Google Calendar</span>
                        <span class="cal-dropdown-meta">Opens in a new tab</span>
                    </a>
                    <a href="<?= e($outlookUrl) ?>" target="_blank" rel="noopener noreferrer" role="menuitem">
                        <span class="cal-dropdown-name">Outlook</span>
                        <span class="cal-dropdown-meta">Opens in a new tab</span>
                    </a>
                    <a href="<?= e($icsUrl) ?>" role="menuitem">
                        <span class="cal-dropdown-name">Apple Calendar &middot; Other</span>
                        <span class="cal-dropdown-meta">Downloads <code>booking.ics</code></span>
                    </a>
                </div>
            </div>

            <a class="btn btn-danger" href="/booking/<?= e($token) ?>/cancel">
                Cancel or reschedule
            </a>

            <button type="button"
                    class="btn btn-outline print-hide"
                    onclick="window.print()"
                    aria-label="Print this booking">
                <i data-lucide="printer" aria-hidden="true"></i>
                Print
            </button>
        </div>

        <!-- Visible on paper only — clicks don't work on print, so we
             render the cancel URL as plain text for the reader. -->
        <div class="print-url print-only" style="display: none;">
            <strong>Cancel or reschedule online</strong>
            <?php
                $base = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
                if ($base === '') {
                    $base = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://';
                    $base .= (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                }
            ?>
            <?= e($base . '/booking/' . $token . '/cancel') ?>
        </div>

        <p class="confirm-footnote">
            Need to make a change? Click cancel or reschedule above, or reply to your confirmation email.
        </p>
    <?php else: ?>
        <div class="confirm-actions">
            <a class="btn btn-accent" href="/book">Book again</a>
        </div>
    <?php endif ?>
</section>
