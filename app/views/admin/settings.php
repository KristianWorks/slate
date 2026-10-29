<?php
/**
 * app/views/admin/settings.php
 *
 * Business-wide configuration form.
 */

/** @var array<string,string> $settings */
/** @var list<string>         $timezones */
/** @var list<int>            $buffers */

$old = $_SESSION['_old'] ?? [];
$val = function (string $key, string $default = '') use ($old, $settings): string {
    return (string) ($old[$key] ?? $settings[$key] ?? $default);
};
?>
<h1 class="page-title">Settings</h1>
<p class="page-sub">Business name, timezone, and how customers are allowed to book.</p>

<form action="/admin/settings" method="post" novalidate style="max-width: 720px;">
    <?= csrf_field() ?>

    <section class="card" style="padding: 28px; margin-bottom: 20px;">
        <h2 class="serif" style="margin: 0 0 16px; font-size: 1.1rem;">Business</h2>

        <div class="field">
            <label for="set-name">Business name</label>
            <input id="set-name" type="text" name="business_name" maxlength="120" required
                   value="<?= e($val('business_name', 'Slate Bookings')) ?>"
                   class="<?= errors('business_name') !== null ? 'is-invalid' : '' ?>">
            <?php if ($msg = errors('business_name')): ?>
                <p class="field-error"><?= e($msg) ?></p>
            <?php endif ?>
        </div>

        <div class="field">
            <label for="set-tz">Timezone</label>
            <select id="set-tz" name="timezone" class="<?= errors('timezone') !== null ? 'is-invalid' : '' ?>">
                <?php $current = $val('timezone', 'Asia/Manila'); ?>
                <?php foreach ($timezones as $tz): ?>
                    <option value="<?= e($tz) ?>" <?= $tz === $current ? 'selected' : '' ?>>
                        <?= e($tz) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <?php if ($msg = errors('timezone')): ?>
                <p class="field-error"><?= e($msg) ?></p>
            <?php endif ?>
        </div>

        <div class="grid-2">
            <div class="field">
                <label for="set-email">Contact email</label>
                <input id="set-email" type="email" name="contact_email"
                       value="<?= e($val('contact_email')) ?>"
                       class="<?= errors('contact_email') !== null ? 'is-invalid' : '' ?>">
                <?php if ($msg = errors('contact_email')): ?>
                    <p class="field-error"><?= e($msg) ?></p>
                <?php endif ?>
            </div>

            <div class="field">
                <label for="set-phone">Contact phone</label>
                <input id="set-phone" type="tel" name="contact_phone" maxlength="40"
                       value="<?= e($val('contact_phone')) ?>">
            </div>
        </div>

        <div class="field">
            <label for="set-location">Location <span class="muted">(shown on the booking confirmation)</span></label>
            <input id="set-location" type="text" name="location" maxlength="255"
                   value="<?= e($val('location')) ?>"
                   placeholder="e.g. 123 Queen St W · Studio 4"
                   class="<?= errors('location') !== null ? 'is-invalid' : '' ?>">
            <?php if ($msg = errors('location')): ?>
                <p class="field-error"><?= e($msg) ?></p>
            <?php endif ?>
        </div>
    </section>

    <section class="card" style="padding: 28px; margin-bottom: 20px;">
        <h2 class="serif" style="margin: 0 0 16px; font-size: 1.1rem;">Booking rules</h2>

        <div class="grid-2">
            <div class="field">
                <label for="set-buffer">Buffer between appointments</label>
                <select id="set-buffer" name="buffer_minutes"
                        class="<?= errors('buffer_minutes') !== null ? 'is-invalid' : '' ?>">
                    <?php $currentBuffer = (int) $val('buffer_minutes', '15'); ?>
                    <?php foreach ($buffers as $b): ?>
                        <option value="<?= (int) $b ?>" <?= $b === $currentBuffer ? 'selected' : '' ?>>
                            <?= $b === 0 ? 'No buffer' : "{$b} minutes" ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <?php if ($msg = errors('buffer_minutes')): ?>
                    <p class="field-error"><?= e($msg) ?></p>
                <?php endif ?>
            </div>

            <div class="field">
                <label for="set-advance">Advance booking window (days)</label>
                <input id="set-advance" type="number" name="advance_booking_days" min="1" max="365"
                       value="<?= e($val('advance_booking_days', '60')) ?>"
                       class="<?= errors('advance_booking_days') !== null ? 'is-invalid' : '' ?>">
                <?php if ($msg = errors('advance_booking_days')): ?>
                    <p class="field-error"><?= e($msg) ?></p>
                <?php else: ?>
                    <p class="muted" style="font-size: 0.8rem; margin: 0;">How far ahead customers may book.</p>
                <?php endif ?>
            </div>
        </div>

        <div class="field">
            <label for="set-notice">Minimum notice (hours)</label>
            <input id="set-notice" type="number" name="min_notice_hours" min="0" max="168"
                   value="<?= e($val('min_notice_hours', '2')) ?>"
                   class="<?= errors('min_notice_hours') !== null ? 'is-invalid' : '' ?>">
            <?php if ($msg = errors('min_notice_hours')): ?>
                <p class="field-error"><?= e($msg) ?></p>
            <?php else: ?>
                <p class="muted" style="font-size: 0.8rem; margin: 0;">Earliest a customer can book from "now".</p>
            <?php endif ?>
        </div>
    </section>

    <div style="display: flex; gap: 12px;">
        <button type="submit" class="btn btn-accent">Save settings</button>
        <a href="/admin" class="btn btn-outline">Cancel</a>
    </div>
</form>
