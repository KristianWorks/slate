<?php
/**
 * app/views/errors/500.php
 *
 * Rendered by the global try/catch in public/index.php when a request
 * throws and APP_DEBUG is false. The ref id is also written to the PHP
 * error log so support can correlate.
 */

/** @var string $refId */
?>
<section class="error-shell">
    <div class="error-card">
        <h1 class="error-h1">Something broke.</h1>
        <p class="error-sub">We&rsquo;re on it. Please try again in a moment.</p>
        <?php if (!empty($refId)): ?>
            <p class="error-ref">
                Reference&nbsp;<span class="ref-code"><?= e($refId) ?></span>
            </p>
        <?php endif ?>
        <div class="error-actions">
            <a href="/" class="btn btn-outline">Back to home</a>
        </div>
    </div>
</section>
