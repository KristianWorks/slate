<?php
/**
 * app/views/layouts/main.php
 *
 * Base layout. Receives via view():
 *   - string $content   pre-rendered HTML of the inner view
 *   - array  $flashes   list of ['type' => string, 'message' => string]
 *   - string $title     (optional) page title
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? ($_ENV['APP_NAME'] ?? 'Slate')) ?></title>

    <!-- Inline critical CSS: prevents FOUC before app.css loads -->
    <style>
        :root {
            --color-bg: #FAF8F5;
            --color-surface: #FFFFFF;
            --color-ink: #1A1A1A;
            --color-line: #E8E4DD;
            --color-accent: #C84B31;
            --font-sans: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-display: 'Fraunces', Georgia, serif;
        }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: var(--font-sans);
            background-color: var(--color-bg);
            color: var(--color-ink);
        }
        [x-cloak] { display: none !important; }
    </style>

    <meta name="description" content="<?= e($metaDescription ?? 'Book online in seconds. Simple, fast appointment booking for service businesses.') ?>">
    <meta name="theme-color" content="#FAF8F5">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($title ?? ($_ENV['APP_NAME'] ?? 'Slate')) ?>">
    <meta property="og:description" content="<?= e($metaDescription ?? 'Book online in seconds.') ?>">
    <?php if (!empty($_ENV['APP_URL'])): ?>
        <meta property="og:url" content="<?= e(rtrim((string) $_ENV['APP_URL'], '/') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <?php endif ?>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- PWA: installable on phones, offline-fallback page -->
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icon.svg">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Slate">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <!-- Google Fonts: Inter (body) + Fraunces (display) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Fraunces:opsz,wght@9..144,400;9..144,500&display=swap">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Slate design system -->
    <link rel="stylesheet" href="/assets/css/app.css">

    <!-- Libraries (defer to keep them off the critical path) -->
    <script defer src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/htmx.org@1.9.12"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/dayjs@1.11.10/dayjs.min.js"></script>
</head>
<body>

<!-- Skip link: visible only when focused. -->
<a class="skip-link" href="#main-content">Skip to content</a>

<!-- Global HTMX top progress bar — populated by the listeners at page bottom. -->
<div class="htmx-top-bar" aria-hidden="true"></div>

<nav class="app-nav">
    <a href="/" class="brand">Slate</a>
    <div class="nav-links">
        <a href="/">Home</a>
        <a href="/book">Book</a>
        <?php if (current_user_id() !== null): ?>
            <?php if (current_user_role() === 'admin'): ?>
                <a href="/admin">Admin</a>
            <?php else: ?>
                <a href="/appointments">My appointments</a>
            <?php endif ?>
            <form action="/logout" method="post" class="d-inline" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;">Sign out</button>
            </form>
        <?php else: ?>
            <a href="/login">Sign in</a>
            <a href="/register" class="nav-cta">Create account</a>
        <?php endif ?>
    </div>
</nav>

<main id="main-content">
    <?= $content ?>
</main>

<!-- Toast / flash stack — Alpine-powered, auto-dismiss after 4.5s -->
<div
    class="toast-stack"
    aria-live="polite"
    aria-atomic="true"
    x-data='{
        messages: <?= htmlspecialchars(json_encode($flashes ?? [], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>,
        dismiss(i) { this.messages.splice(i, 1); },
        init() {
            window.addEventListener("flash", (e) => {
                if (e.detail && e.detail.message) this.messages.push(e.detail);
            });
        }
    }'
    x-cloak
>
    <template x-for="(msg, i) in messages" :key="i">
        <div
            class="toast-item"
            :class="{
                'is-success': msg.type === 'success',
                'is-error':   msg.type === 'error',
                'is-info':    msg.type !== 'success' && msg.type !== 'error'
            }"
            x-init="setTimeout(() => dismiss(i), 4500)"
            x-transition:enter="toast-enter"
            x-transition:enter-start="toast-enter-from"
            x-transition:enter-end="toast-enter-to"
            x-transition:leave="toast-leave"
            x-transition:leave-start="toast-leave-from"
            x-transition:leave-end="toast-leave-to"
        >
            <span x-text="msg.message"></span>
            <button type="button" class="toast-close" @click="dismiss(i)" aria-label="Dismiss">&times;</button>
        </div>
    </template>
</div>

<script>
    // Initialise Lucide icons once the deferred script lands.
    window.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) window.lucide.createIcons();
    });
    document.body.addEventListener('htmx:afterSwap', () => {
        if (window.lucide) window.lucide.createIcons();
    });

    // Linear-style top progress bar — track concurrent HTMX requests.
    (function () {
        let pending = 0;
        const start = () => { pending++; document.body.classList.add('htmx-loading'); };
        const stop  = () => { pending = Math.max(0, pending - 1); if (pending === 0) document.body.classList.remove('htmx-loading'); };
        document.body.addEventListener('htmx:beforeRequest', start);
        document.body.addEventListener('htmx:afterRequest',  stop);
        document.body.addEventListener('htmx:sendError',     () => {
            window.dispatchEvent(new CustomEvent('flash', { detail: { type: 'error', message: 'Connection problem. Please retry.' }}));
        });
        document.body.addEventListener('htmx:responseError', (e) => {
            if (!e.detail || !e.detail.xhr) return;
            // 4xx/5xx — show a toast only for server errors; 4xx may be expected (e.g. 422 validation).
            if (e.detail.xhr.status >= 500) {
                window.dispatchEvent(new CustomEvent('flash', { detail: { type: 'error', message: 'Something went wrong. Please retry.' }}));
            }
        });
    })();

    // Service worker registration — gives us offline fallback + PWA installability.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch((err) => {
                console.warn('[Slate] Service worker registration failed:', err);
            });
        });
    }
</script>
</body>
</html>
