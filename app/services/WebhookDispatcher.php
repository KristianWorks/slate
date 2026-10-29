<?php
/**
 * app/services/WebhookDispatcher.php
 *
 * Fires Slate events to registered webhook URLs.
 *
 *   WebhookDispatcher::dispatch('booking.created', $appointmentId);
 *
 * Behaviour:
 *   - Looks up active webhooks subscribed to the event.
 *   - Builds a JSON payload `{ event, occurred_at, data }`.
 *   - Inserts a row per webhook into `webhook_queue` (synchronous, cheap).
 *   - Delivery is performed by /app/jobs/ProcessWebhooks.php (cron).
 *     This keeps the booking response fast — the customer doesn't wait
 *     on third-party HTTP.
 *
 * Signature: every delivery POSTs with
 *     X-Slate-Signature: sha256=<hex>
 * where the hex is HMAC-SHA256(payload, webhook.secret). Receivers MUST
 * verify using `hash_equals` against this header.
 *
 * Retry policy (in ProcessWebhooks):
 *   attempt 1 fails → wait 1 minute
 *   attempt 2 fails → wait 5 minutes
 *   attempt 3 fails → wait 30 minutes
 *   attempt 4+      → abandon, mark delivered_at with [abandoned] error
 *
 * Per-webhook failure tracking:
 *   - Each non-2xx response increments webhooks.failure_count.
 *   - Successful delivery resets the counter to 0.
 *   - At 10 consecutive failures the webhook is auto-disabled
 *     (Webhook::DISABLE_AFTER_FAILURES).
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\Webhook;

final class WebhookDispatcher
{
    /** Backoff schedule (seconds) by attempt number. */
    public const BACKOFF = [
        1 => 60,        // 1 minute
        2 => 300,       // 5 minutes
        3 => 1_800,     // 30 minutes
    ];

    public const MAX_ATTEMPTS = 3;
    public const TIMEOUT_SECONDS = 10;

    /**
     * Enqueue an event for delivery to every subscribed webhook.
     * Never throws — logging and silent skip keep the caller's flow safe.
     */
    public static function dispatch(string $event, int $appointmentId): void
    {
        try {
            if (!in_array($event, Webhook::EVENTS, true)) {
                logger()->warning('Unknown webhook event ignored', ['event' => $event]);
                return;
            }

            $subscribers = Webhook::activeForEvent($event);
            if ($subscribers === []) {
                return;
            }

            $appt = Appointment::findById($appointmentId);
            if ($appt === null) {
                logger()->warning('Webhook dispatch skipped — appointment not found', [
                    'event' => $event, 'appointment_id' => $appointmentId,
                ]);
                return;
            }

            $payload = (string) json_encode([
                'event'       => $event,
                'occurred_at' => gmdate('c'),
                'data'        => [
                    'booking' => self::presentBooking($appt),
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            foreach ($subscribers as $w) {
                Webhook::enqueue((int) $w['id'], $event, $payload);
            }
        } catch (\Throwable $e) {
            logger()->error('Webhook dispatch failed to enqueue', [
                'event' => $event,
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deliver a single queued row. Returns [ok, statusCode, errorMessage].
     *
     * @return array{0:bool, 1:int, 2:string|null}
     */
    public static function deliver(string $url, string $secret, string $payload): array
    {
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $ch = curl_init($url);
        if ($ch === false) {
            return [false, 0, 'curl_init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'User-Agent: Slate-Webhook/1.0',
                'X-Slate-Signature: ' . $signature,
            ],
        ]);

        $body   = curl_exec($ch);
        $errNo  = curl_errno($ch);
        $errMsg = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0) {
            return [false, $status, "curl error #{$errNo}: {$errMsg}"];
        }
        if ($status < 200 || $status >= 300) {
            $snippet = is_string($body) ? substr($body, 0, 200) : '';
            return [false, $status, "HTTP {$status}: " . $snippet];
        }
        return [true, $status, null];
    }

    /**
     * Public payload shape — what receivers actually see in `data.booking`.
     *
     * @param  array<string,mixed> $a
     * @return array<string,mixed>
     */
    public static function presentBooking(array $a): array
    {
        $utc = new \DateTimeZone('UTC');
        return [
            'id'          => (int)    $a['id'],
            'reference'   => strtoupper(substr((string) $a['cancel_token'], -8)),
            'status'      => (string) $a['status'],
            'service' => [
                'id'               => (int)    $a['service_id'],
                'name'             => (string) ($a['service_name']     ?? ''),
                'duration_minutes' => (int)    ($a['service_duration'] ?? 0),
                'price'            => (float)  ($a['service_price']    ?? 0),
            ],
            'customer' => [
                'name'  => (string) $a['customer_name'],
                'email' => (string) $a['customer_email'],
                'phone' => (string) $a['customer_phone'],
            ],
            'starts_at' => (new \DateTimeImmutable((string) $a['starts_at']))->setTimezone($utc)->format('c'),
            'ends_at'   => (new \DateTimeImmutable((string) $a['ends_at']))->setTimezone($utc)->format('c'),
            'notes'     => $a['notes'] !== null ? (string) $a['notes'] : null,
            'created_at'=> (new \DateTimeImmutable((string) $a['created_at']))->setTimezone($utc)->format('c'),
        ];
    }
}
