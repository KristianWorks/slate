<?php
/**
 * app/views/errors/429.php
 *
 * Rendered by RateLimit::guard() when an IP exceeds an endpoint's limit.
 */

/** @var int $windowMinutes */
?>
<section class="error-shell">
    <div class="error-card">
        <h1 class="error-h1">Too many attempts.</h1>
        <p class="error-sub">
            For your protection we&rsquo;re briefly pausing this action.
            Please try again in
            <strong><?= (int) $windowMinutes ?> <?= $windowMinutes === 1 ? 'minute' : 'minutes' ?></strong>.
        </p>
        <div class="error-actions">
            <a href="/" class="btn btn-outline">Back to home</a>
        </div>
    </div>
</section>
