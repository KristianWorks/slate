<?php
/**
 * app/views/auth/register.php
 *
 * Create-account card. Mirrors the login layout for visual continuity.
 */
?>
<section class="auth-shell">
    <div class="auth-card">
        <h1 class="display-h1"><?= e(__('auth.register_title')) ?></h1>
        <p class="display-sub"><?= e(__('auth.register_sub')) ?></p>

        <form action="/register" method="post" class="auth-form" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="name"><?= e(__('auth.name_label')) ?></label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= old('name') ?>"
                    autocomplete="name"
                    autofocus
                    required
                    maxlength="120"
                    class="<?= errors('name') !== null ? 'is-invalid' : '' ?>"
                >
                <?php if ($msg = errors('name')): ?>
                    <p class="field-error"><?= e($msg) ?></p>
                <?php endif ?>
            </div>

            <div class="field">
                <label for="email"><?= e(__('auth.email_label')) ?></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= old('email') ?>"
                    autocomplete="email"
                    required
                    class="<?= errors('email') !== null ? 'is-invalid' : '' ?>"
                >
                <?php if ($msg = errors('email')): ?>
                    <p class="field-error"><?= e($msg) ?></p>
                <?php endif ?>
            </div>

            <div class="field">
                <label for="password"><?= e(__('auth.password_label')) ?></label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                    class="<?= errors('password') !== null ? 'is-invalid' : '' ?>"
                >
                <?php if ($msg = errors('password')): ?>
                    <p class="field-error"><?= e($msg) ?></p>
                <?php else: ?>
                    <p class="field-error muted" style="color: var(--color-ink-soft);"><?= e(__('auth.password_hint')) ?></p>
                <?php endif ?>
            </div>

            <div class="field">
                <label for="password_confirmation"><?= e(__('auth.confirm_password_label')) ?></label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required
                    class="<?= errors('password_confirmation') !== null ? 'is-invalid' : '' ?>"
                >
                <?php if ($msg = errors('password_confirmation')): ?>
                    <p class="field-error"><?= e($msg) ?></p>
                <?php endif ?>
            </div>

            <button type="submit" class="btn btn-accent btn-block"><?= e(__('auth.create_button')) ?></button>
        </form>

        <p class="auth-footer">
            <?= e(__('auth.already_account_prompt')) ?>
            <a href="/login"><?= e(__('auth.already_account_link')) ?></a>
        </p>
    </div>
</section>
