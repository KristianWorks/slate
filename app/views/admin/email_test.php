<?php
/**
 * app/views/admin/email_test.php
 */

/** @var list<array<string,mixed>> $recent */
/** @var string $defaultTo */
/** @var string $mailHost */
/** @var string $mailUser */
/** @var string $mailFromAddr */
?>
<h1 class="page-title">Email test</h1>
<p class="page-sub">Send a test of any email template to a chosen address &mdash; useful when iterating on copy or design.</p>

<section class="card" style="padding: 20px 24px; margin-bottom: 24px;">
    <h2 class="serif" style="margin: 0 0 8px; font-size: 1rem;">SMTP config</h2>
    <div class="muted" style="font-size: 0.85rem; line-height: 1.7;">
        Host: <code><?= e($mailHost !== '' ? $mailHost : '(unset)') ?></code> &middot;
        User: <code><?= e($mailUser !== '' ? $mailUser : '(unset)') ?></code> &middot;
        From: <code><?= e($mailFromAddr !== '' ? $mailFromAddr : '(unset)') ?></code>
    </div>
    <?php if ($mailHost === '' || $mailUser === ''): ?>
        <p class="muted" style="margin-top: 10px; font-size: 0.85rem;">
            &#9888; SMTP isn&rsquo;t fully configured in <code>.env</code>. Sends will fail until you set
            <code>MAIL_HOST</code>, <code>MAIL_USERNAME</code>, <code>MAIL_PASSWORD</code>.
        </p>
    <?php endif ?>
</section>

<?php if ($recent === []): ?>
    <section class="empty-state">
        <svg class="empty-illu" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="14" y="22" width="68" height="52" rx="8" stroke="currentColor" stroke-width="2"/>
            <path d="M14 30 L48 56 L82 30" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        </svg>
        <h3>No appointments yet</h3>
        <p>Create one through <code>/book</code> first &mdash; then come back here to send test emails against it.</p>
        <a href="/book" class="btn btn-accent">Open booking page</a>
    </section>
<?php else: ?>
    <form action="/admin/email-test/send" method="post" class="card" style="padding: 24px;">
        <?= csrf_field() ?>

        <div class="grid-2" style="margin-bottom: 16px;">
            <div class="field" style="margin: 0;">
                <label for="et-to">Send to</label>
                <input id="et-to" type="email" name="to"
                       value="<?= e($defaultTo) ?>"
                       placeholder="leave blank to use the appointment&rsquo;s email">
                <p class="muted" style="font-size: 0.78rem; margin: 4px 0 0;">
                    Override the recipient so tests don&rsquo;t spam real customers.
                </p>
            </div>

            <div class="field" style="margin: 0;">
                <label for="et-appt">Appointment</label>
                <select id="et-appt" name="appointment_id" required>
                    <?php foreach ($recent as $a): ?>
                        <option value="<?= (int) $a['id'] ?>">
                            #<?= (int) $a['id'] ?>
                            &middot; <?= e((string) $a['customer_name']) ?>
                            &middot; <?= e((string) $a['service_name']) ?>
                            &middot; <?= e((string) $a['starts_at']) ?>
                            &middot; <?= e((string) $a['status']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="submit" name="type" value="confirmation"        class="btn btn-outline">
                Send confirmation
            </button>
            <button type="submit" name="type" value="reschedule"          class="btn btn-outline">
                Send reschedule
            </button>
            <button type="submit" name="type" value="cancellation"        class="btn btn-outline">
                Send cancellation
            </button>
            <button type="submit" name="type" value="reminder"            class="btn btn-outline">
                Send reminder
            </button>
            <button type="submit" name="type" value="admin_notification"  class="btn btn-outline">
                Send admin notification
            </button>
        </div>

        <p class="muted" style="margin: 16px 0 0; font-size: 0.8rem;">
            Successful sends are logged to <code>email_log</code>. Failed sends are written to the PHP error log.
        </p>
    </form>
<?php endif ?>
