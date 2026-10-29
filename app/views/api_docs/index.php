<?php
/**
 * app/views/api_docs/index.php
 *
 * Single-page API reference. Sticky sidebar with scroll-spy + Prism.js
 * highlighting on every code block. Brand-consistent — no Swagger UI.
 */

/** @var string $baseUrl */

// Each section: id, label, http method (or null for prose), path.
$sections = [
    ['id' => 'intro',      'label' => 'Getting started',   'method' => null,    'path' => null],
    ['id' => 'auth',       'label' => 'Authentication',    'method' => null,    'path' => null],
    ['id' => 'rate-limits','label' => 'Rate limits',       'method' => null,    'path' => null],
    ['id' => 'errors',     'label' => 'Errors',            'method' => null,    'path' => null],

    ['id' => 'public',     'label' => 'Public endpoints',  'method' => null,    'path' => null, 'heading' => true],
    ['id' => 'services',   'label' => 'List services',     'method' => 'GET',   'path' => '/api/v1/services'],
    ['id' => 'availability','label'=> 'Availability',      'method' => 'GET',   'path' => '/api/v1/availability'],
    ['id' => 'hours',      'label' => 'Business hours',    'method' => 'GET',   'path' => '/api/v1/business-hours'],
    ['id' => 'create-booking','label'=>'Create booking',   'method' => 'POST',  'path' => '/api/v1/bookings'],
    ['id' => 'get-booking','label' => 'Get booking',       'method' => 'GET',   'path' => '/api/v1/bookings/{token}'],
    ['id' => 'cancel-booking','label'=>'Cancel booking',   'method' => 'POST',  'path' => '/api/v1/bookings/{token}/cancel'],

    ['id' => 'admin',      'label' => 'Admin endpoints',   'method' => null,    'path' => null, 'heading' => true],
    ['id' => 'list-appts', 'label' => 'List appointments', 'method' => 'GET',   'path' => '/api/v1/admin/appointments'],
    ['id' => 'update-appt','label' => 'Update appointment','method' => 'PATCH', 'path' => '/api/v1/admin/appointments/{id}'],
    ['id' => 'create-svc', 'label' => 'Create service',    'method' => 'POST',  'path' => '/api/v1/admin/services'],
    ['id' => 'block-date', 'label' => 'Block a date',      'method' => 'POST',  'path' => '/api/v1/admin/blocked-dates'],

    ['id' => 'webhooks',   'label' => 'Webhooks',          'method' => null,    'path' => null, 'heading' => true],
];

/**
 * Render one endpoint section header.
 */
$endpointHead = static function (string $method, string $path): string {
    $cls = strtolower($method);
    return '<div class="api-endpoint-head">'
         . '<span class="api-method api-method-' . htmlspecialchars($cls, ENT_QUOTES) . '">'
         . htmlspecialchars($method, ENT_QUOTES) . '</span>'
         . '<code class="api-path">' . htmlspecialchars($path, ENT_QUOTES) . '</code>'
         . '</div>';
};
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css">
<style>
    /* Prism theme overrides — keep it on-brand */
    pre[class*="language-"] {
        background-color: #1a1a1a !important;
        color: #eee !important;
        border-radius: var(--radius-lg);
        font-size: 0.82rem;
        padding: 16px 18px;
        margin: 0;
        line-height: 1.6;
    }
    code[class*="language-"] { color: #eee !important; }
    .token.property, .token.string  { color: #ffb59c !important; }
    .token.number, .token.boolean   { color: #c8a8ff !important; }
    .token.keyword, .token.operator { color: #80d0ff !important; }
    .token.comment                  { color: #888 !important; }
</style>

<div class="api-docs">
    <aside class="api-sidebar" aria-label="API endpoints">
        <div class="api-sidebar-inner">
            <a href="/api/docs" class="api-sidebar-title">API <span class="muted">v1</span></a>
            <nav>
                <?php foreach ($sections as $s):
                    if (!empty($s['heading'])): ?>
                        <div class="api-sidebar-group"><?= e($s['label']) ?></div>
                    <?php else: ?>
                        <a href="#<?= e($s['id']) ?>" class="api-sidebar-link" data-section="<?= e($s['id']) ?>">
                            <?php if ($s['method'] !== null): ?>
                                <span class="api-method-mini api-method-<?= e(strtolower($s['method'])) ?>"><?= e($s['method']) ?></span>
                            <?php endif ?>
                            <span><?= e($s['label']) ?></span>
                        </a>
                    <?php endif;
                endforeach ?>
            </nav>
        </div>
    </aside>

    <main class="api-content" id="api-content">

        <!-- ============================================================
             GETTING STARTED
             ============================================================ -->
        <section id="intro" class="api-section">
            <h1 class="api-h1">Slate API <span class="muted">v1</span></h1>
            <p class="api-lede">
                A small, JSON-first REST API for booking, querying, and managing
                Slate appointments. Public endpoints don't need authentication;
                admin endpoints use bearer tokens.
            </p>

            <h3 class="api-h3">Base URL</h3>
            <p>All endpoints live under:</p>
            <?php require __DIR__ . '/_code_block.php'; ?>
            <?php $codeLang = 'bash'; $codeBody = $baseUrl . '/api/v1'; include __DIR__ . '/_code_block.php'; ?>

            <h3 class="api-h3">Response envelope</h3>
            <p>Every response is JSON with a consistent shape:</p>
            <?php $codeLang = 'json'; $codeBody = <<<JSON
{
  "data":   { "...": "..." },
  "errors": [],
  "meta":   { "page": 1, "per_page": 25, "total": 87 }
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>
            <ul class="api-list">
                <li><code>data</code> — the resource (object or array). Null on errors.</li>
                <li><code>errors</code> — array of error objects. Empty on success.</li>
                <li><code>meta</code> — pagination + counts. Only present where relevant.</li>
            </ul>
        </section>

        <!-- ============================================================
             AUTHENTICATION
             ============================================================ -->
        <section id="auth" class="api-section">
            <h2 class="api-h2">Authentication</h2>
            <p>
                Generate a token in the admin panel at
                <a href="/admin/settings/api-tokens">Settings → API tokens</a>.
                Send it as a <code>Bearer</code> header on every admin request:
            </p>
            <?php $codeLang = 'bash'; $codeBody = 'Authorization: Bearer slate_a1b2c3d4e5f6...'; include __DIR__ . '/_code_block.php'; ?>
            <p>
                Tokens grant the issuing user's role — currently admin only.
                Tokens are shown <strong>once</strong> on creation; only the
                SHA-256 hash is stored. Revoke a leaked token from the same page.
            </p>
        </section>

        <!-- ============================================================
             RATE LIMITS
             ============================================================ -->
        <section id="rate-limits" class="api-section">
            <h2 class="api-h2">Rate limits</h2>
            <p>Per-IP for public endpoints, per (IP × token) for admin endpoints.</p>
            <table class="api-table">
                <thead><tr><th>Endpoint class</th><th>Limit</th><th>Window</th></tr></thead>
                <tbody>
                    <tr><td>Public</td><td>60 requests</td><td>1 minute</td></tr>
                    <tr><td>Admin</td><td>600 requests</td><td>1 minute</td></tr>
                </tbody>
            </table>
            <p>Over-limit requests get <code>429 Too Many Requests</code> with a <code>Retry-After</code> header in seconds.</p>
        </section>

        <!-- ============================================================
             ERROR FORMAT
             ============================================================ -->
        <section id="errors" class="api-section">
            <h2 class="api-h2">Errors</h2>
            <p>Error responses populate <code>errors</code> instead of <code>data</code>:</p>
            <?php $codeLang = 'json'; $codeBody = <<<JSON
{
  "data": null,
  "errors": [
    {
      "code": "validation_failed",
      "field": "email",
      "message": "Valid email is required."
    }
  ]
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>
            <table class="api-table">
                <thead><tr><th>Status</th><th>Code</th><th>Meaning</th></tr></thead>
                <tbody>
                    <tr><td>400</td><td><code>bad_request</code></td><td>Malformed request body / missing parameter.</td></tr>
                    <tr><td>401</td><td><code>unauthorized</code></td><td>Missing or invalid bearer token.</td></tr>
                    <tr><td>403</td><td><code>forbidden</code></td><td>Token valid but lacks permission.</td></tr>
                    <tr><td>404</td><td><code>not_found</code></td><td>Resource doesn't exist (or you don't own it).</td></tr>
                    <tr><td>409</td><td><code>slot_taken</code> / <code>already_blocked</code></td><td>Conflict — competing change.</td></tr>
                    <tr><td>422</td><td><code>validation_failed</code></td><td>Body parsed but failed validation. Errors are per-field.</td></tr>
                    <tr><td>429</td><td>—</td><td>Rate-limit exceeded. Honour <code>Retry-After</code>.</td></tr>
                    <tr><td>500</td><td>—</td><td>Server error. Reference id may appear in the body.</td></tr>
                </tbody>
            </table>
        </section>

        <!-- ============================================================
             PUBLIC ENDPOINTS
             ============================================================ -->

        <section id="services" class="api-section">
            <?= $endpointHead('GET', '/api/v1/services') ?>
            <p>List active bookable services. Public — no auth required.</p>

            <h4 class="api-h4">Request</h4>
            <?php $codeLang='bash'; $codeBody = "curl {$baseUrl}/api/v1/services"; include __DIR__ . '/_code_block.php'; ?>

            <h4 class="api-h4">JavaScript (fetch)</h4>
            <?php $codeLang='javascript'; $codeBody = "const res = await fetch('{$baseUrl}/api/v1/services');\nconst { data } = await res.json();"; include __DIR__ . '/_code_block.php'; ?>

            <h4 class="api-h4">Response (200)</h4>
            <?php $codeLang='json'; $codeBody = <<<JSON
{
  "data": [
    {
      "id": 1,
      "name": "Quick Consultation",
      "description": "Short intro / Q&A session.",
      "duration_minutes": 30,
      "price": 35.00,
      "color": "#1A56DB"
    }
  ],
  "errors": [],
  "meta": { "count": 1 }
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>
        </section>

        <section id="availability" class="api-section">
            <?= $endpointHead('GET', '/api/v1/availability') ?>
            <p>Returns the slot grid for one day + service. UTC ISO-8601 timestamps.</p>

            <h4 class="api-h4">Query parameters</h4>
            <table class="api-table">
                <thead><tr><th>Param</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>service_id</code></td><td>yes</td><td>Integer id of an active service.</td></tr>
                    <tr><td><code>date</code></td><td>yes</td><td><code>YYYY-MM-DD</code>, must be a real calendar date.</td></tr>
                </tbody>
            </table>

            <h4 class="api-h4">Request</h4>
            <?php $codeLang='bash'; $codeBody = "curl '{$baseUrl}/api/v1/availability?service_id=2&date=2026-05-20'"; include __DIR__ . '/_code_block.php'; ?>

            <h4 class="api-h4">Response (200)</h4>
            <?php $codeLang='json'; $codeBody = <<<JSON
{
  "data": {
    "date": "2026-05-20",
    "service_id": 2,
    "timezone": "America/Toronto",
    "duration_minutes": 60,
    "buffer_minutes": 15,
    "slots": [
      { "starts_at": "2026-05-20T13:00:00+00:00", "ends_at": "2026-05-20T14:00:00+00:00" },
      { "starts_at": "2026-05-20T14:15:00+00:00", "ends_at": "2026-05-20T15:15:00+00:00" }
    ]
  },
  "errors": [],
  "meta": { "count": 2 }
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>
        </section>

        <section id="hours" class="api-section">
            <?= $endpointHead('GET', '/api/v1/business-hours') ?>
            <p>Weekly schedule + timezone + booking rules.</p>

            <h4 class="api-h4">Response (200)</h4>
            <?php $codeLang='json'; $codeBody = <<<JSON
{
  "data": {
    "timezone": "America/Toronto",
    "business_name": "Slate Bookings",
    "location": "In person · Toronto studio",
    "buffer_minutes": 15,
    "min_notice_hours": 2,
    "advance_booking_days": 60,
    "week": [
      { "day_of_week": 0, "name": "sunday",   "is_closed": true,  "open_time": null,       "close_time": null       },
      { "day_of_week": 1, "name": "monday",   "is_closed": false, "open_time": "09:00:00", "close_time": "18:00:00" }
    ]
  },
  "errors": []
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>
        </section>

        <section id="create-booking" class="api-section">
            <?= $endpointHead('POST', '/api/v1/bookings') ?>
            <p>Create a booking. Same validation + race-safe insert as the web form.</p>

            <h4 class="api-h4">Body (JSON)</h4>
            <table class="api-table">
                <thead><tr><th>Field</th><th>Type</th><th>Notes</th></tr></thead>
                <tbody>
                    <tr><td><code>service_id</code></td><td>int</td><td>Active service id.</td></tr>
                    <tr><td><code>date</code></td><td>string</td><td><code>YYYY-MM-DD</code>.</td></tr>
                    <tr><td><code>time</code></td><td>string</td><td><code>HH:MM</code> or <code>HH:MM:SS</code> (24-hour, business timezone).</td></tr>
                    <tr><td><code>name</code></td><td>string</td><td>2–150 chars.</td></tr>
                    <tr><td><code>email</code></td><td>string</td><td>RFC-valid email.</td></tr>
                    <tr><td><code>phone</code></td><td>string</td><td>7–15 digits, intl-friendly punctuation allowed.</td></tr>
                    <tr><td><code>notes</code></td><td>string</td><td>Optional, ≤500 chars.</td></tr>
                </tbody>
            </table>

            <h4 class="api-h4">Request</h4>
            <?php $codeLang='bash'; $codeBody = "curl -X POST {$baseUrl}/api/v1/bookings \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"service_id\":2,\"date\":\"2026-05-20\",\"time\":\"14:15\",\"name\":\"Sarah Chen\",\"email\":\"sarah@example.com\",\"phone\":\"+1 555-0101\"}'"; include __DIR__ . '/_code_block.php'; ?>

            <h4 class="api-h4">Response (201)</h4>
            <?php $codeLang='json'; $codeBody = <<<JSON
{
  "data": {
    "id": 42,
    "reference": "A3B4C5D6",
    "cancel_token": "a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6",
    "status": "confirmed",
    "service": { "id": 2, "name": "Standard Appointment", "duration_minutes": 60 },
    "customer": { "name": "Sarah Chen", "email": "sarah@example.com", "phone": "+1 555-0101" },
    "starts_at": "2026-05-20T18:15:00+00:00",
    "ends_at":   "2026-05-20T19:15:00+00:00",
    "timezone":  "America/Toronto",
    "links": {
      "self":    "/api/v1/bookings/a1b2c3...",
      "cancel":  "/api/v1/bookings/a1b2c3.../cancel",
      "web_view":"/booking/a1b2c3..."
    }
  },
  "errors": []
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>

            <h4 class="api-h4">Errors</h4>
            <ul class="api-list">
                <li><code>409 slot_taken</code> — someone else booked it first.</li>
                <li><code>409 slot_unavailable</code> — not in the available grid (closed day, past time, etc.).</li>
                <li><code>422 validation_failed</code> — one or more fields invalid; see <code>errors[]</code>.</li>
            </ul>
        </section>

        <section id="get-booking" class="api-section">
            <?= $endpointHead('GET', '/api/v1/bookings/{token}') ?>
            <p>Retrieve a booking by its cancel token. The token acts as auth.</p>

            <h4 class="api-h4">Request</h4>
            <?php $codeLang='bash'; $codeBody = "curl {$baseUrl}/api/v1/bookings/a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6"; include __DIR__ . '/_code_block.php'; ?>
            <p>Returns the same shape as the create response. <code>404</code> if the token is malformed or unknown.</p>
        </section>

        <section id="cancel-booking" class="api-section">
            <?= $endpointHead('POST', '/api/v1/bookings/{token}/cancel') ?>
            <p>Cancel a booking. Idempotent — cancelling an already-cancelled booking returns 200 with the current state.</p>

            <h4 class="api-h4">Request</h4>
            <?php $codeLang='bash'; $codeBody = "curl -X POST {$baseUrl}/api/v1/bookings/a1b2c3.../cancel"; include __DIR__ . '/_code_block.php'; ?>
            <p>Fires the <code>booking.cancelled</code> webhook event and queues a cancellation email.</p>
        </section>

        <!-- ============================================================
             ADMIN ENDPOINTS
             ============================================================ -->

        <section id="list-appts" class="api-section">
            <?= $endpointHead('GET', '/api/v1/admin/appointments') ?>
            <p>Paginated list of appointments. Requires admin bearer token.</p>

            <h4 class="api-h4">Query parameters</h4>
            <table class="api-table">
                <thead><tr><th>Param</th><th>Notes</th></tr></thead>
                <tbody>
                    <tr><td><code>status</code></td><td><code>all</code> (default), or one of <code>pending</code>, <code>confirmed</code>, <code>cancelled</code>, <code>completed</code>, <code>no_show</code>.</td></tr>
                    <tr><td><code>from</code></td><td><code>YYYY-MM-DD</code> — inclusive lower bound on <code>starts_at</code>.</td></tr>
                    <tr><td><code>to</code></td><td><code>YYYY-MM-DD</code> — inclusive upper bound.</td></tr>
                    <tr><td><code>q</code></td><td>Substring search on name / email / reference.</td></tr>
                    <tr><td><code>page</code></td><td>1-indexed, 25 per page.</td></tr>
                </tbody>
            </table>

            <h4 class="api-h4">Request</h4>
            <?php $codeLang='bash'; $codeBody = "curl '{$baseUrl}/api/v1/admin/appointments?status=confirmed&from=2026-05-01' \\\n  -H 'Authorization: Bearer slate_...'"; include __DIR__ . '/_code_block.php'; ?>
        </section>

        <section id="update-appt" class="api-section">
            <?= $endpointHead('PATCH', '/api/v1/admin/appointments/{id}') ?>
            <p>Update status and/or notes. Triggers the appropriate webhook event.</p>

            <h4 class="api-h4">Body</h4>
            <?php $codeLang='json'; $codeBody = <<<JSON
{
  "status": "completed",
  "notes": "Showed up on time. Discussed phase 2."
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>

            <h4 class="api-h4">Request</h4>
            <?php $codeLang='bash'; $codeBody = "curl -X PATCH {$baseUrl}/api/v1/admin/appointments/42 \\\n  -H 'Authorization: Bearer slate_...' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"status\":\"completed\"}'"; include __DIR__ . '/_code_block.php'; ?>
        </section>

        <section id="create-svc" class="api-section">
            <?= $endpointHead('POST', '/api/v1/admin/services') ?>
            <p>Create a new bookable service. Also exposed as <code>PATCH</code> and <code>DELETE</code> at <code>/api/v1/admin/services/{id}</code>.</p>

            <?php $codeLang='json'; $codeBody = <<<JSON
{
  "name": "Tarot Reading",
  "description": "30-minute reading.",
  "duration_minutes": 30,
  "price": 45.00,
  "color": "#6B21A8",
  "is_active": true
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>
            <p class="muted" style="font-size: 0.82rem;">
                <code>color</code> must be one of the 8 brand presets — see the admin Services page.
            </p>
        </section>

        <section id="block-date" class="api-section">
            <?= $endpointHead('POST', '/api/v1/admin/blocked-dates') ?>
            <p>Mark a specific date as unbookable (holiday, vacation, etc.).</p>

            <?php $codeLang='json'; $codeBody = <<<JSON
{ "date": "2026-12-25", "reason": "Christmas Day" }
JSON;
            include __DIR__ . '/_code_block.php'; ?>
        </section>

        <!-- ============================================================
             WEBHOOKS
             ============================================================ -->
        <section id="webhooks" class="api-section">
            <h2 class="api-h2">Webhooks</h2>
            <p>
                Slate POSTs JSON to URLs you register at
                <a href="/admin/settings/webhooks">Settings → Webhooks</a>.
                Every delivery is signed with HMAC-SHA256 so receivers can prove
                the request really came from Slate.
            </p>

            <h3 class="api-h3">Events</h3>
            <table class="api-table">
                <thead><tr><th>Event</th><th>Fires when</th></tr></thead>
                <tbody>
                    <tr><td><code>booking.created</code></td><td>A booking is confirmed (web or API).</td></tr>
                    <tr><td><code>booking.cancelled</code></td><td>A booking is cancelled (customer or admin).</td></tr>
                    <tr><td><code>booking.rescheduled</code></td><td>An existing booking is moved.</td></tr>
                    <tr><td><code>booking.completed</code></td><td>Admin marks a booking as completed.</td></tr>
                </tbody>
            </table>

            <h3 class="api-h3">Payload shape</h3>
            <?php $codeLang='json'; $codeBody = <<<JSON
{
  "event": "booking.created",
  "occurred_at": "2026-05-15T14:32:00+00:00",
  "data": {
    "booking": {
      "id": 42,
      "reference": "A3B4C5D6",
      "status": "confirmed",
      "service": { "id": 2, "name": "Standard Appointment", "duration_minutes": 60, "price": 80 },
      "customer": { "name": "Sarah Chen", "email": "sarah@example.com", "phone": "+1 555-0101" },
      "starts_at": "2026-05-20T18:15:00+00:00",
      "ends_at":   "2026-05-20T19:15:00+00:00",
      "notes": null,
      "created_at": "2026-05-15T14:32:00+00:00"
    }
  }
}
JSON;
            include __DIR__ . '/_code_block.php'; ?>

            <h3 class="api-h3">Signature verification</h3>
            <p>
                Each request carries an <code>X-Slate-Signature</code> header of the form
                <code>sha256=&lt;hex&gt;</code>, where the hex is
                <code>HMAC-SHA256(payload, webhook_secret)</code>. Always verify
                with a constant-time comparison.
            </p>

            <h4 class="api-h4">PHP receiver</h4>
            <?php $codeLang='php'; $codeBody = <<<'PHP'
<?php
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SLATE_SIGNATURE'] ?? '';

$expected  = 'sha256=' . hash_hmac('sha256', $payload, getenv('SLATE_WEBHOOK_SECRET'));
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('bad signature');
}

$event = json_decode($payload, true);
// dispatch based on $event['event'] ...
http_response_code(204);
PHP;
            include __DIR__ . '/_code_block.php'; ?>

            <h4 class="api-h4">Node.js receiver</h4>
            <?php $codeLang='javascript'; $codeBody = <<<'JS'
import crypto from 'node:crypto';
import express from 'express';

const app = express();
app.use(express.raw({ type: 'application/json' }));

app.post('/webhooks/slate', (req, res) => {
    const signature = req.headers['x-slate-signature'] || '';
    const expected = 'sha256=' + crypto
        .createHmac('sha256', process.env.SLATE_WEBHOOK_SECRET)
        .update(req.body)
        .digest('hex');

    const a = Buffer.from(expected);
    const b = Buffer.from(signature);
    if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
        return res.status(401).send('bad signature');
    }

    const event = JSON.parse(req.body.toString('utf8'));
    // handle event.event, event.data.booking ...
    res.status(204).end();
});
JS;
            include __DIR__ . '/_code_block.php'; ?>

            <h4 class="api-h4">Python receiver</h4>
            <?php $codeLang='python'; $codeBody = <<<'PY'
import hmac
import hashlib
import os
from flask import Flask, request

app = Flask(__name__)

@app.post('/webhooks/slate')
def receive():
    raw = request.get_data()
    sig = request.headers.get('X-Slate-Signature', '')
    expected = 'sha256=' + hmac.new(
        os.environ['SLATE_WEBHOOK_SECRET'].encode(),
        raw,
        hashlib.sha256,
    ).hexdigest()

    if not hmac.compare_digest(expected, sig):
        return 'bad signature', 401

    event = request.get_json()
    # handle event['event'], event['data']['booking'] ...
    return '', 204
PY;
            include __DIR__ . '/_code_block.php'; ?>

            <h3 class="api-h3">Retry policy</h3>
            <ul class="api-list">
                <li>Slate expects a 2xx response within 10 seconds. Anything else counts as a failure.</li>
                <li>Failed deliveries retry on a <strong>1 minute → 5 minutes → 30 minutes</strong> backoff, then abandon.</li>
                <li>10 consecutive failures auto-pauses the webhook; an admin must resume it manually.</li>
                <li>Receivers <strong>must</strong> be idempotent — network blips can cause the same event to arrive twice.</li>
            </ul>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-core.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-json.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-bash.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-javascript.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-php.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-python.min.js"></script>

<script>
// ---- Copy buttons ----
document.querySelectorAll('.api-code-wrap').forEach((wrap) => {
    const btn = wrap.querySelector('.api-copy-btn');
    const code = wrap.querySelector('code');
    if (!btn || !code) return;
    btn.addEventListener('click', () => {
        navigator.clipboard.writeText(code.textContent || '').then(() => {
            btn.textContent = 'Copied';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1500);
        }).catch(() => {
            btn.textContent = 'Copy failed';
        });
    });
});

// ---- Scroll-spy: highlight the sidebar link for the section currently in view ----
(function () {
    const sections = document.querySelectorAll('.api-section[id]');
    const linksByID = {};
    document.querySelectorAll('.api-sidebar-link[data-section]').forEach((el) => {
        linksByID[el.dataset.section] = el;
    });
    if (sections.length === 0 || Object.keys(linksByID).length === 0) return;

    let activeID = null;
    const setActive = (id) => {
        if (id === activeID) return;
        if (activeID && linksByID[activeID]) linksByID[activeID].classList.remove('is-active');
        if (linksByID[id]) linksByID[id].classList.add('is-active');
        activeID = id;
    };

    const io = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((e) => e.isIntersecting)
            .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible.length > 0) setActive(visible[0].target.id);
    }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

    sections.forEach((s) => io.observe(s));
})();
</script>
