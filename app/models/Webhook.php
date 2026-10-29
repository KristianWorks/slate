<?php
/**
 * app/models/Webhook.php
 *
 * Webhook subscriptions + the delivery queue. Each subscription picks
 * one or more event names to listen for. Deliveries are queued into
 * `webhook_queue` and processed by /app/jobs/ProcessWebhooks.php.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Webhook
{
    public const EVENTS = [
        'booking.created',
        'booking.cancelled',
        'booking.rescheduled',
        'booking.completed',
    ];

    public const DISABLE_AFTER_FAILURES = 10;

    /* ============================================================
     * Subscriptions
     * ============================================================ */

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $rows = Database::getInstance()->fetchAll(
            'SELECT * FROM webhooks ORDER BY created_at DESC',
        );
        foreach ($rows as &$r) {
            $r['events'] = json_decode((string) $r['events'], true) ?: [];
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $row = Database::getInstance()->fetch(
            'SELECT * FROM webhooks WHERE id = :id LIMIT 1',
            ['id' => $id],
        );
        if ($row !== null) {
            $row['events'] = json_decode((string) $row['events'], true) ?: [];
        }
        return $row;
    }

    /** @return list<array<string,mixed>> only active webhooks listening for $event. */
    public static function activeForEvent(string $event): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT * FROM webhooks
              WHERE is_active = 1
                AND JSON_CONTAINS(events, JSON_QUOTE(:event))",
            ['event' => $event],
        );
        foreach ($rows as &$r) {
            $r['events'] = json_decode((string) $r['events'], true) ?: [];
        }
        return $rows;
    }

    /** @param list<string> $events */
    public static function create(string $url, array $events): int
    {
        return (int) Database::getInstance()->insert('webhooks', [
            'url'    => $url,
            'secret' => bin2hex(random_bytes(32)),
            'events' => (string) json_encode(array_values(array_unique($events))),
        ]);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        if (isset($data['events']) && is_array($data['events'])) {
            $data['events'] = (string) json_encode(array_values($data['events']));
        }
        Database::getInstance()->update('webhooks', $data, ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query('DELETE FROM webhooks WHERE id = :id', ['id' => $id]);
    }

    public static function disable(int $id): void
    {
        Database::getInstance()->update('webhooks', ['is_active' => 0], ['id' => $id]);
    }

    public static function recordTriggered(int $id, bool $success, ?string $error = null): void
    {
        $db = Database::getInstance();
        if ($success) {
            $db->query(
                'UPDATE webhooks SET last_triggered_at = NOW(), failure_count = 0 WHERE id = :id',
                ['id' => $id],
            );
            return;
        }
        $db->query(
            "UPDATE webhooks
                SET last_triggered_at = NOW(),
                    failure_count = failure_count + 1
              WHERE id = :id",
            ['id' => $id],
        );
        // Auto-disable runaway endpoints.
        $row = $db->fetch('SELECT failure_count FROM webhooks WHERE id = :id', ['id' => $id]);
        if ($row !== null && (int) $row['failure_count'] >= self::DISABLE_AFTER_FAILURES) {
            self::disable($id);
        }
    }

    /* ============================================================
     * Queue
     * ============================================================ */

    public static function enqueue(int $webhookId, string $event, string $payloadJson): int
    {
        return (int) Database::getInstance()->insert('webhook_queue', [
            'webhook_id'      => $webhookId,
            'event'           => $event,
            'payload'         => $payloadJson,
            'next_attempt_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> Pending rows ready for delivery. */
    public static function dueQueueRows(int $limit = 50): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT q.*, w.url, w.secret, w.is_active
               FROM webhook_queue q
               JOIN webhooks w ON w.id = q.webhook_id
              WHERE q.delivered_at IS NULL
                AND q.next_attempt_at <= NOW()
                AND w.is_active = 1
           ORDER BY q.next_attempt_at ASC
              LIMIT " . (int) $limit,
        );
    }

    public static function markQueueDelivered(int $queueId): void
    {
        Database::getInstance()->query(
            'UPDATE webhook_queue SET delivered_at = NOW(), last_error = NULL WHERE id = :id',
            ['id' => $queueId],
        );
    }

    public static function markQueueFailed(int $queueId, int $attempt, int $delaySeconds, string $error): void
    {
        Database::getInstance()->query(
            "UPDATE webhook_queue
                SET attempt_count = :attempt,
                    next_attempt_at = DATE_ADD(NOW(), INTERVAL :delay SECOND),
                    last_error = :err
              WHERE id = :id",
            ['attempt' => $attempt, 'delay' => $delaySeconds, 'err' => $error, 'id' => $queueId],
        );
    }

    /** Give up — mark as delivered with last_error preserved (so failed rows aren't infinitely retried). */
    public static function markQueueAbandoned(int $queueId, string $error): void
    {
        Database::getInstance()->query(
            "UPDATE webhook_queue
                SET delivered_at = NOW(),
                    last_error = :err
              WHERE id = :id",
            ['err' => '[abandoned] ' . $error, 'id' => $queueId],
        );
    }
}
