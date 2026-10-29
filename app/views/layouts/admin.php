<?php
/**
 * app/views/layouts/admin.php
 *
 * Sidebar + content shell used by every /admin/* page.
 * The inner view sets $active to highlight the current sidebar link.
 */

/** @var string      $content */
/** @var array       $flashes */
/** @var string|null $title   */
/** @var string|null $active  */

$active = $active ?? '';
$navItems = [
    ['key' => 'dashboard',    'href' => '/admin',                'label' => 'Dashboard',     'icon' => 'layout-dashboard'],
    ['key' => 'appointments', 'href' => '/admin/appointments',   'label' => 'Appointments',  'icon' => 'calendar-clock'],
    ['key' => 'services',     'href' => '/admin/services',       'label' => 'Services',      'icon' => 'briefcase'],
    ['key' => 'schedule',     'href' => '/admin/schedule',       'label' => 'Schedule',      'icon' => 'clock'],
    ['key' => 'blocked',      'href' => '/admin/blocked-dates',  'label' => 'Blocked dates', 'icon' => 'calendar-x'],
    ['key' => 'settings',     'href' => '/admin/settings',                 'label' => 'Settings',      'icon' => 'settings'],
    ['key' => 'api_tokens',   'href' => '/admin/settings/api-tokens',      'label' => 'API tokens',    'icon' => 'key-round'],
    ['key' => 'webhooks',     'href' => '/admin/settings/webhooks',        'label' => 'Webhooks',      'icon' => 'webhook'],
    ['key' => 'email_test',   'href' => '/admin/email-test',               'label' => 'Email test',    'icon' => 'mail'],
];
if (can_view_logs()) {
    $navItems[] = ['key' => 'logs', 'href' => '/admin/logs', 'label' => 'Logs', 'icon' => 'scroll-text'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#FAF8F5">
    <title><?= e($title ?? 'Slate Admin') ?></title>

    <!-- Inline critical CSS -->
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

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Fraunces:opsz,wght@9..144,400;9..144,500&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">

    <script defer src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/htmx.org@1.9.12"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/dayjs@1.11.10/dayjs.min.js"></script>
</head>
<body x-data="{ sidebarOpen: false }">
<!-- Skip link: visible only on focus -->
<a class="skip-link" href="#main-content">Skip to content</a>

<!-- Global HTMX top progress bar -->
<div class="htmx-top-bar" aria-hidden="true"></div>

<div class="admin-shell">
    <aside class="admin-sidebar" :class="{ 'is-open': sidebarOpen }" @click.outside="sidebarOpen && (sidebarOpen = false)">
        <div class="admin-brand-row">
            <a href="/admin" class="admin-brand">Slate</a>
            <?php if (is_demo_mode()): ?>
                <span class="demo-pill demo-pill-sidebar" aria-label="Demo mode active">DEMO</span>
            <?php endif ?>
        </div>

        <nav aria-label="Admin">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= e($item['href']) ?>"
                   class="admin-nav-link <?= $active === $item['key'] ? 'is-active' : '' ?>">
                    <i data-lucide="<?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach ?>
        </nav>

        <div style="margin-top: auto; padding: 12px;">
            <form action="/logout" method="post" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-block" style="padding: 0.5rem 0.9rem; font-size: 0.85rem;">
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    <main id="main-content" class="admin-content">
        <header class="admin-mobile-bar">
            <button type="button"
                    class="admin-burger"
                    aria-label="Open menu"
                    @click.stop="sidebarOpen = !sidebarOpen">
                <i data-lucide="menu"></i>
            </button>
            <span class="admin-mobile-title">Slate</span>
        </header>

        <?= $content ?>
    </main>
</div>

<!-- Toast / flash stack -->
<div
    class="toast-stack"
    aria-live="polite"
    aria-atomic="true"
    x-data='{
        messages: <?= htmlspecialchars(json_encode($flashes ?? [], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>,
        dismiss(i) { this.messages.splice(i, 1); },
        init() {
            // Lets any page push a toast: window.dispatchEvent(new CustomEvent("flash", { detail: { type, message }}))
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
            :class="{ 'is-success': msg.type === 'success', 'is-error': msg.type === 'error', 'is-info': msg.type !== 'success' && msg.type !== 'error' }"
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
    // HTMX: attach the CSRF token to every request so the server can verify it.
    document.body.addEventListener('htmx:configRequest', (e) => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) e.detail.headers['X-CSRF-Token'] = meta.content;
    });

    // Render Lucide icons (once at load, again after any HTMX swap).
    window.addEventListener('DOMContentLoaded', () => window.lucide && window.lucide.createIcons());
    document.body.addEventListener('htmx:afterSwap', () => window.lucide && window.lucide.createIcons());

    // Linear-style top progress bar — track concurrent HTMX requests.
    (function () {
        let pending = 0;
        const start = () => { pending++; document.body.classList.add('htmx-loading'); };
        const stop  = () => { pending = Math.max(0, pending - 1); if (pending === 0) document.body.classList.remove('htmx-loading'); };
        document.body.addEventListener('htmx:beforeRequest', start);
        document.body.addEventListener('htmx:afterRequest',  stop);
        document.body.addEventListener('htmx:sendError', () => {
            window.dispatchEvent(new CustomEvent('flash', { detail: { type: 'error', message: 'Connection problem. Please retry.' }}));
        });
        document.body.addEventListener('htmx:responseError', (e) => {
            if (e.detail && e.detail.xhr && e.detail.xhr.status >= 500) {
                window.dispatchEvent(new CustomEvent('flash', { detail: { type: 'error', message: 'Something went wrong. Please retry.' }}));
            }
        });
    })();
</script>
</body>
</html>
