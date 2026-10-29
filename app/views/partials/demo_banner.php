<?php
/**
 * app/views/partials/demo_banner.php
 *
 * Shown on /  and /login when DEMO_MODE=true. Two credential rows with
 * one-click clipboard copy. Toast confirms via the global flash event.
 *
 * Include with:  if (is_demo_mode()) require base_path('app/views/partials/demo_banner.php');
 */
?>
<aside class="demo-banner" x-data="demoBanner()" aria-label="Demo credentials">
    <header class="demo-banner-head">
        <span class="demo-pill">DEMO</span>
        <div>
            <h3 class="demo-banner-title">Demo credentials</h3>
            <p class="demo-banner-sub">Sign in as either account to explore the system.</p>
        </div>
    </header>

    <div class="demo-cred-grid">
        <div class="demo-cred">
            <div class="demo-cred-label">Admin</div>
            <button type="button" class="demo-cred-row" @click="copy('demo-admin@slate.test')" title="Copy email">
                <code>demo-admin@slate.test</code>
                <span class="demo-copy-hint">Copy</span>
            </button>
            <button type="button" class="demo-cred-row" @click="copy('demo1234')" title="Copy password">
                <code>demo1234</code>
                <span class="demo-copy-hint">Copy</span>
            </button>
        </div>

        <div class="demo-cred">
            <div class="demo-cred-label">Customer</div>
            <button type="button" class="demo-cred-row" @click="copy('demo-user@slate.test')" title="Copy email">
                <code>demo-user@slate.test</code>
                <span class="demo-copy-hint">Copy</span>
            </button>
            <button type="button" class="demo-cred-row" @click="copy('demo1234')" title="Copy password">
                <code>demo1234</code>
                <span class="demo-copy-hint">Copy</span>
            </button>
        </div>
    </div>

    <p class="demo-banner-foot">This demo resets every 24 hours.</p>
</aside>

<script>
    function demoBanner() {
        return {
            copy(text) {
                const fire = (ok) => window.dispatchEvent(new CustomEvent('flash', {
                    detail: { type: ok ? 'success' : 'error', message: ok ? 'Copied to clipboard.' : 'Copy failed — select and copy manually.' }
                }));
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(() => fire(true)).catch(() => fire(false));
                    return;
                }
                // Fallback for plain HTTP / older browsers.
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { fire(document.execCommand('copy')); }
                catch { fire(false); }
                finally { document.body.removeChild(ta); }
            }
        };
    }
</script>
