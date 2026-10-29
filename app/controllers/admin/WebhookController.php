<?php
/**
 * app/controllers/admin/WebhookController.php
 *
 *   GET  /admin/settings/webhooks               list + add form
 *   POST /admin/settings/webhooks               create
 *   POST /admin/settings/webhooks/{id}/toggle   pause / resume
 *   POST /admin/settings/webhooks/{id}/delete   remove
 *   POST /admin/settings/webhooks/{id}/test     queue a synthetic event for testing
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Webhook;

final class WebhookController
{
    public function __construct()
    {
        require_admin();
    }

    public function index(): void
    {
        view('admin/webhooks/index', [
            'title'      => 'Webhooks — Slate Admin',
            'active'     => 'webhooks',
            'webhooks'   => Webhook::all(),
            'eventTypes' => Webhook::EVENTS,
        ], 'admin');
    }

    public function store(): void
    {
        csrf_check();
        demo_block('Creating webhooks is disabled in demo mode.');

        $url    = trim((string) ($_POST['url'] ?? ''));
        $events = (array) ($_POST['events'] ?? []);

        $errors = [];
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            $errors['url'] = 'Please enter a valid http(s) URL.';
        }
        if (mb_strlen($url) > 500) {
            $errors['url'] = 'URL must be 500 characters or fewer.';
        }
        $events = array_values(array_filter(
            $events,
            static fn($e) => in_array((string) $e, Webhook::EVENTS, true),
        ));
        if ($events === []) {
            $errors['events'] = 'Pick at least one event.';
        }

        if ($errors !== []) {
            errors_set($errors);
            old_set(['url' => $url, 'events' => $events]);
            flash('error', 'Please fix the errors below.');
            redirect('/admin/settings/webhooks');
        }

        Webhook::create($url, $events);
        flash('success', 'Webhook saved.');
        redirect('/admin/settings/webhooks');
    }

    public function toggle(string $id): void
    {
        csrf_check();
        demo_block('Toggling webhooks is disabled in demo mode.');

        $w = Webhook::find((int) $id);
        if ($w === null) {
            flash('error', 'Webhook not found.');
            redirect('/admin/settings/webhooks');
        }

        Webhook::update((int) $id, [
            'is_active'     => $w['is_active'] ? 0 : 1,
            // Resuming after disable resets the failure counter — operator
            // chose to retry. Otherwise the auto-disable would re-fire.
            'failure_count' => $w['is_active'] ? (int) $w['failure_count'] : 0,
        ]);
        flash('success', $w['is_active'] ? 'Webhook paused.' : 'Webhook resumed.');
        redirect('/admin/settings/webhooks');
    }

    public function destroy(string $id): void
    {
        csrf_check();
        demo_block('Deleting webhooks is disabled in demo mode.');
        Webhook::delete((int) $id);
        flash('success', 'Webhook removed.');
        redirect('/admin/settings/webhooks');
    }

    public function test(string $id): void
    {
        csrf_check();
        demo_block('Test deliveries are disabled in demo mode.');

        $w = Webhook::find((int) $id);
        if ($w === null) {
            flash('error', 'Webhook not found.');
            redirect('/admin/settings/webhooks');
        }

        // Synthetic payload so receivers can verify their handler without
        // needing a real booking.
        $payload = (string) json_encode([
            'event'       => 'webhook.test',
            'occurred_at' => gmdate('c'),
            'data'        => ['message' => 'Test event from Slate admin.'],
        ], JSON_UNESCAPED_SLASHES);

        Webhook::enqueue((int) $id, 'webhook.test', $payload);
        flash('success', 'Test event queued. ProcessWebhooks will deliver it on the next cron tick.');
        redirect('/admin/settings/webhooks');
    }
}
