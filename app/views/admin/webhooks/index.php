<?php
/**
 * app/views/admin/webhooks/index.php
 */

/** @var list<array<string,mixed>> $webhooks */
/** @var list<string>              $eventTypes */
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Webhooks</h1>
        <p class="page-sub">
            Subscribe external systems to Slate events. Every delivery is signed
            with HMAC-SHA256 &mdash; <a href="/api/docs#webhooks">verify the signature</a>
            on your receiver.
        </p>
    </div>
</header>

<section class="card" style="padding: 22px; margin-bottom: 22px;">
    <h2 class="serif" style="margin: 0 0 14px; font-size: 1.05rem;">Add a webhook</h2>

    <form action="/admin/settings/webhooks" method="post">
        <?= csrf_field() ?>

        <div class="field">
            <label for="wh-url">Endpoint URL</label>
            <input id="wh-url" type="url" name="url" maxlength="500" required
                   value="<?= old('url') ?>"
                   placeholder="https://your-app.example.com/webhooks/slate"
                   class="<?= errors('url') !== null ? 'is-invalid' : '' ?>">
            <?php if ($msg = errors('url')): ?>
                <p class="field-error"><?= e($msg) ?></p>
            <?php endif ?>
        </div>

        <div class="field">
            <label>Events to subscribe to</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px;">
                <?php foreach ($eventTypes as $ev):
                    $checked = in_array($ev, (array) (old('events') ?: []), true);
                ?>
                    <label style="display: flex; align-items: center; gap: 8px; padding: 8px 12px;
                                  background-color: var(--color-bg); border: 1px solid var(--color-line);
                                  border-radius: 8px; cursor: pointer; font-size: 0.85rem;">
                        <input type="checkbox" name="events[]" value="<?= e($ev) ?>" <?= $checked ? 'checked' : '' ?>
                               style="accent-color: var(--color-accent);">
                        <code style="font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.78rem;"><?= e($ev) ?></code>
                    </label>
                <?php endforeach ?>
            </div>
            <?php if ($msg = errors('events')): ?>
                <p class="field-error"><?= e($msg) ?></p>
            <?php endif ?>
        </div>

        <button type="submit" class="btn btn-accent">Save webhook</button>
    </form>
</section>

<?php if ($webhooks === []): ?>
    <section class="empty-state">
        <h3>No webhooks registered yet</h3>
        <p>Add one above to start receiving events.</p>
    </section>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ($webhooks as $w):
            $isActive = ((int) $w['is_active']) === 1;
            $fails    = (int) $w['failure_count'];
        ?>
            <article class="card" style="padding: 20px 22px;">
                <header style="display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div style="min-width: 0; flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                            <code style="font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.86rem; font-weight: 500; word-break: break-all;">
                                <?= e((string) $w['url']) ?>
                            </code>
                            <?php if ($isActive): ?>
                                <span class="status-pill status-confirmed">Active</span>
                            <?php else: ?>
                                <span class="status-pill status-cancelled">Paused</span>
                            <?php endif ?>
                            <?php if ($fails > 0): ?>
                                <span class="status-pill status-pending" title="Consecutive failures">
                                    <?= $fails ?> failure<?= $fails === 1 ? '' : 's' ?>
                                </span>
                            <?php endif ?>
                        </div>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px;">
                            <?php foreach ((array) $w['events'] as $ev): ?>
                                <code style="background-color: var(--color-bg); border: 1px solid var(--color-line);
                                            border-radius: 999px; padding: 2px 10px; font-size: 0.74rem;
                                            font-family: ui-monospace, Menlo, Consolas, monospace;">
                                    <?= e((string) $ev) ?>
                                </code>
                            <?php endforeach ?>
                        </div>
                        <p class="muted" style="margin: 0; font-size: 0.78rem;">
                            Secret:
                            <code style="font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.74rem;">
                                <?= e(substr((string) $w['secret'], 0, 12)) ?>&hellip;
                            </code>
                            <?php if ($w['last_triggered_at']): ?>
                                &middot; Last triggered <?= e((string) $w['last_triggered_at']) ?>
                            <?php else: ?>
                                &middot; Never triggered
                            <?php endif ?>
                        </p>
                    </div>

                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <form action="/admin/settings/webhooks/<?= (int) $w['id'] ?>/test" method="post" style="margin: 0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">Send test</button>
                        </form>
                        <form action="/admin/settings/webhooks/<?= (int) $w['id'] ?>/toggle" method="post" style="margin: 0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">
                                <?= $isActive ? 'Pause' : 'Resume' ?>
                            </button>
                        </form>
                        <form action="/admin/settings/webhooks/<?= (int) $w['id'] ?>/delete" method="post" style="margin: 0;"
                              onsubmit="return confirm('Remove this webhook? Pending deliveries will be dropped.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm btn-danger-outline">Remove</button>
                        </form>
                    </div>
                </header>
            </article>
        <?php endforeach ?>
    </div>

    <p class="muted" style="margin-top: 24px; font-size: 0.82rem;">
        Auto-disable: after <?= \App\Models\Webhook::DISABLE_AFTER_FAILURES ?> consecutive
        failures a webhook is paused. Resuming it resets the counter.
    </p>
<?php endif ?>

<section class="card" style="padding: 22px; margin-top: 24px; background-color: var(--color-bg);">
    <h2 class="serif" style="margin: 0 0 8px; font-size: 1rem;">Delivery cadence</h2>
    <p class="muted" style="margin: 0; font-size: 0.85rem; line-height: 1.6;">
        Webhooks are queued synchronously and delivered by
        <code>php app/jobs/ProcessWebhooks.php</code> via cron (default: every 1 minute).
        Failed deliveries retry on a 1m → 5m → 30m schedule, then are abandoned.
        See <a href="/api/docs#webhooks">the API docs</a> for the payload + signature format.
    </p>
</section>
