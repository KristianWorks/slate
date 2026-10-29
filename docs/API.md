# Slate REST API

> JSON over HTTP. Public endpoints don't need auth; admin endpoints use bearer tokens. Every response shares the same `{ data, errors, meta }` envelope.

**Full reference with copy-paste examples**: visit `/api/docs` on any Slate deployment.

---

## Base URL

```
https://your-domain.tld/api/v1
```

## Authentication

Generate a token in **Admin → Settings → API tokens**. Send it on every admin request:

```
Authorization: Bearer slate_<48 hex chars>
```

Tokens are shown **once**. Only the SHA-256 hash is stored. Revoke a leaked token from the same page — apps using it stop working immediately.

## Response envelope

Every response, success or failure:

```json
{
  "data":   { "...": "..." },
  "errors": [],
  "meta":   { "page": 1, "per_page": 25, "total": 87 }
}
```

| Field | Notes |
|---|---|
| `data` | Resource (object or array). `null` on errors. |
| `errors` | Array of `{ code, message, field? }`. Empty on success. |
| `meta` | Pagination + counts. Only on endpoints that need it. |

## Rate limits

| Class | Limit | Window | Key |
|---|---|---|---|
| Public | 60 requests | 1 minute | per client IP |
| Admin  | 600 requests | 1 minute | per (IP × token) |

Over-limit → `429 Too Many Requests` with `Retry-After` header (seconds).

## Status codes

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 204 | No Content |
| 400 | Bad request — malformed body / missing param |
| 401 | Unauthorized — missing / invalid bearer token |
| 403 | Forbidden — token valid but lacks permission |
| 404 | Not found |
| 409 | Conflict — slot taken, date already blocked, etc. |
| 422 | Validation failed — `errors[]` is per-field |
| 429 | Rate-limited |
| 500 | Server error — reference id in body |

---

## Endpoints

### Public

| Method | Path | Purpose |
|---|---|---|
| GET  | `/services` | List active services. |
| GET  | `/availability?service_id=&date=` | Slot grid for one day + service. UTC ISO-8601. |
| GET  | `/business-hours` | Weekly hours + timezone + booking rules. |
| POST | `/bookings` | Create a booking. Same validation + race-safe insert as the web form. |
| GET  | `/bookings/{token}` | Retrieve a booking by its cancel token. Token = auth. |
| POST | `/bookings/{token}/cancel` | Cancel. Idempotent. Fires `booking.cancelled` webhook. |

### Admin (Bearer token)

| Method | Path | Purpose |
|---|---|---|
| GET    | `/admin/appointments` | Paginated list. Filters: `status`, `from`, `to`, `q`, `page`. |
| PATCH  | `/admin/appointments/{id}` | Update `status` and/or `notes`. Fires the appropriate webhook. |
| POST   | `/admin/services` | Create a service. |
| PATCH  | `/admin/services/{id}` | Update a service (partial). |
| DELETE | `/admin/services/{id}` | Delete — falls back to deactivation if appointments reference it. |
| POST   | `/admin/blocked-dates` | Mark a date as unbookable. |

---

## Webhooks

Subscribe receivers at **Admin → Settings → Webhooks**.

### Events

| Event | Fires when |
|---|---|
| `booking.created` | Booking confirmed (web or API). |
| `booking.cancelled` | Booking cancelled (customer or admin). |
| `booking.rescheduled` | Existing booking moved. |
| `booking.completed` | Admin marks a booking as completed. |

### Payload

```json
{
  "event": "booking.created",
  "occurred_at": "2026-05-15T14:32:00+00:00",
  "data": {
    "booking": {
      "id": 42,
      "reference": "A3B4C5D6",
      "status": "confirmed",
      "service":  { "id": 2, "name": "Standard Appointment", "duration_minutes": 60, "price": 80 },
      "customer": { "name": "Sarah Chen", "email": "sarah@example.com", "phone": "+1 555-0101" },
      "starts_at": "2026-05-20T18:15:00+00:00",
      "ends_at":   "2026-05-20T19:15:00+00:00",
      "notes": null,
      "created_at": "2026-05-15T14:32:00+00:00"
    }
  }
}
```

### Signature

Every delivery carries `X-Slate-Signature: sha256=<hex>` where the hex is `HMAC-SHA256(raw_body, webhook_secret)`. Receivers **must** verify with constant-time comparison.

PHP receiver:

```php
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SLATE_SIGNATURE'] ?? '';
$expected  = 'sha256=' . hash_hmac('sha256', $payload, getenv('SLATE_WEBHOOK_SECRET'));

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('bad signature');
}
```

Node.js, Python, and verbose error-handling examples in [`/api/docs#webhooks`](../public/index.php).

### Retry policy

- Slate expects a **2xx response within 10 seconds**.
- Failed deliveries retry on **1m → 5m → 30m** backoff, then abandon.
- **10 consecutive failures** auto-pauses the webhook; an admin must resume it manually.
- Receivers **must** be idempotent — network blips can re-deliver.

### Cron requirement

Deliveries are queued synchronously into `webhook_queue` and processed by:

```bash
* * * * * php /var/www/slate/app/jobs/ProcessWebhooks.php
```

Until cron is wired up, the queue grows but never drains. See [DEPLOYMENT.md](../DEPLOYMENT.md) for the shared-host (cron-job.org) path.

---

## Quick examples

### List services

```bash
curl https://your-domain.tld/api/v1/services
```

### Create a booking

```bash
curl -X POST https://your-domain.tld/api/v1/bookings \
  -H 'Content-Type: application/json' \
  -d '{
    "service_id": 2,
    "date": "2026-05-20",
    "time": "14:15",
    "name": "Sarah Chen",
    "email": "sarah@example.com",
    "phone": "+1 555-0101"
  }'
```

Returns `201 Created` with the booking object + `links.cancel`.

### Mark an appointment completed (admin)

```bash
curl -X PATCH https://your-domain.tld/api/v1/admin/appointments/42 \
  -H "Authorization: Bearer $SLATE_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"status":"completed"}'
```

Fires the `booking.completed` webhook.

---

## Versioning

Path-versioned (`/api/v1/…`). Breaking changes ship under `/api/v2/…` and run alongside v1 for at least one minor release.

## Stability

This is a portfolio project, not a hosted service. Use at your own risk. Pull requests welcome — see the main [README](../README.md) for contribution conventions.
