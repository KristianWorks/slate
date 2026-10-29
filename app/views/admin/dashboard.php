<?php
/**
 * app/views/admin/dashboard.php
 *
 * Big-number stats for the admin landing page.
 */

/** @var int                       $todayCount */
/** @var int                       $weekCount */
/** @var string                    $monthRevenue */
/** @var array<string,mixed>|null  $nextUpcoming */
?>
<h1 class="page-title">Dashboard</h1>
<p class="page-sub">A quick look at what&rsquo;s happening today.</p>

<section class="stat-grid" aria-label="Key metrics">
    <article class="stat-card">
        <div class="stat-num"><?= e((string) $todayCount) ?></div>
        <div class="stat-label">Appointments today</div>
    </article>

    <article class="stat-card">
        <div class="stat-num"><?= e((string) $weekCount) ?></div>
        <div class="stat-label">This week</div>
    </article>

    <article class="stat-card">
        <div class="stat-num">$<?= e($monthRevenue) ?></div>
        <div class="stat-label">Revenue this month</div>
    </article>

    <article class="stat-card">
        <?php if ($nextUpcoming === null): ?>
            <div class="stat-num stat-num-sm">&mdash;</div>
            <div class="stat-label">No upcoming appointments</div>
        <?php else: ?>
            <?php $when = now()->parse((string) $nextUpcoming['starts_at']); ?>
            <div class="stat-num stat-num-sm"><?= e($when->format('M j')) ?></div>
            <div class="stat-label">
                Next: <?= e((string) $nextUpcoming['service_name']) ?>
                with <?= e((string) $nextUpcoming['customer_name']) ?>
                at <?= e($when->format('g:i A')) ?>
            </div>
        <?php endif ?>
    </article>
</section>

<section class="card" style="padding: 24px;">
    <h2 class="serif" style="margin: 0 0 8px; font-size: 1.2rem;">Welcome back, <?= e(current_user_name() ?? 'Admin') ?>.</h2>
    <p class="muted" style="margin: 0;">
        Use the sidebar to manage services, edit your schedule, and configure how customers book.
    </p>
</section>
