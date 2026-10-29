<?php
/**
 * app/views/admin/api_tokens/index.php
 */

/** @var list<array<string,mixed>> $tokens */
/** @var ?string                   $newToken */
?>
<header class="page-header">
    <div>
        <h1 class="page-title">API tokens</h1>
        <p class="page-sub">
            Personal access tokens for the <a href="/api/docs">REST API</a>.
            Tokens grant admin permission &mdash; treat them like passwords.
        </p>
    </div>
</header>

<?php if ($newToken !== null): ?>
    <section class="card" style="padding: 22px; margin-bottom: 24px; border-left: 3px solid var(--color-accent);"
             x-data="{ copied: false }">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
            <div style="flex: 1; min-width: 0;">
                <p class="serif" style="margin: 0 0 6px; font-size: 1rem; font-weight: 500;">
                    Your new token
                </p>
                <p class="muted" style="margin: 0 0 12px; font-size: 0.82rem;">
                    Copy it now &mdash; this is the only time it will be shown in full.
                </p>
                <code style="display: block; padding: 12px 14px; background-color: var(--color-bg);
                            border: 1px solid var(--color-line); border-radius: 8px;
                            font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.84rem;
                            word-break: break-all;"
                      x-ref="tokenCode"><?= e($newToken) ?></code>
            </div>
            <button type="button" class="btn btn-accent btn-sm"
                    @click="navigator.clipboard.writeText($refs.tokenCode.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 1500); })">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak>Copied</span>
            </button>
        </div>
    </section>
<?php endif ?>

<section class="card" style="padding: 22px; margin-bottom: 22px;">
    <h2 class="serif" style="margin: 0 0 12px; font-size: 1.05rem;">Generate a new token</h2>
    <form action="/admin/settings/api-tokens" method="post" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <?= csrf_field() ?>
        <div class="field" style="margin: 0; flex: 1; min-width: 240px;">
            <label for="tk-name">Token name (for your reference)</label>
            <input id="tk-name" type="text" name="name" maxlength="120" required
                   placeholder="e.g. Zapier integration, mobile app, staging">
        </div>
        <button type="submit" class="btn btn-accent">Generate</button>
    </form>
</section>

<?php if ($tokens === []): ?>
    <section class="empty-state">
        <h3>No tokens yet</h3>
        <p>Generate your first token above to start using the API.</p>
    </section>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Prefix</th>
                    <th>Last used</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tokens as $t): ?>
                    <tr>
                        <td><strong><?= e((string) $t['name']) ?></strong></td>
                        <td><code style="font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.82rem;"><?= e((string) $t['token_prefix']) ?>&hellip;</code></td>
                        <td><?= $t['last_used_at'] ? e((string) $t['last_used_at']) : '<span class="muted">never</span>' ?></td>
                        <td><?= e((string) $t['created_at']) ?></td>
                        <td class="text-end">
                            <form action="/admin/settings/api-tokens/<?= (int) $t['id'] ?>/revoke" method="post" style="display: inline;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm btn-danger-outline"
                                        onclick="return confirm('Revoke this token? Apps using it will stop working immediately.')">
                                    Revoke
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<p class="muted" style="margin-top: 24px; font-size: 0.85rem;">
    Use a token by sending it as a Bearer header:
    <code style="font-family: ui-monospace, monospace; font-size: 0.78rem;">Authorization: Bearer slate_&hellip;</code>
    on any <code>/api/v1/admin/*</code> endpoint.
    See the <a href="/api/docs">API documentation</a> for details.
</p>
