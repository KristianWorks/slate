<?php
/**
 * app/views/auth/login.php
 *
 * Sign-in card. Centered, generous whitespace, serif heading.
 * Strings flow through __() — see lang/en.php.
 */
?>
<section class="auth-shell">
    <div class="auth-card">
        <h1 class="display-h1"><?= e(__('auth.login_title')) ?></h1>
        <p class="display-sub"><?= e(__('auth.login_sub')) ?></p>

        <form action="/login" method="post" class="auth-form" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email"><?= e(__('auth.email_label')) ?></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= old('email') ?>"
                    autocomplete="email"
                    autofocus
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
                    autocomplete="current-password"
                    required
                    class="<?= errors('password') !== null ? 'is-invalid' : '' ?>"
                >
                <?php if ($msg = errors('password')): ?>
                    <p class="field-error"><?= e($msg) ?></p>
                <?php endif ?>
            </div>

            <button type="submit" class="btn btn-accent btn-block"><?= e(__('auth.sign_in_button')) ?></button>
        </form>

        <p class="auth-footer">
            <?= e(__('auth.no_account_prompt')) ?>
            <a href="/register"><?= e(__('auth.no_account_link')) ?></a>
        </p>
    </div>

    <?php if (is_demo_mode()): ?>
        <div style="width: 100%; max-width: 420px; margin-top: 24px;">
            <?php require base_path('app/views/partials/demo_banner.php'); ?>
        </div>
    <?php endif ?>
</section>
