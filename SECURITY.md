# Slate — Security Posture

A practical inventory of what's in place, where the code lives, and the known
trade-offs you should understand before deploying.

---

## Quick reference

| Concern | Status | Implementation |
|---|---|---|
| CSRF                       | ✅ enforced  | `csrf_check()` on every POST handler |
| SQL injection              | ✅ none      | 100% prepared statements via PDO |
| XSS                        | ✅ escaped   | `e()` on every dynamic HTML output |
| Sessions                   | ✅ hardened  | httponly + samesite=Lax + strict-mode + secure-in-prod + 2h idle timeout |
| Password hashing           | ✅ modern    | `PASSWORD_ARGON2ID` |
| Password rules             | ✅ NIST-ish  | min 8 chars + ≥1 letter + ≥1 number |
| Rate limiting              | ✅ in place  | File-based, IP-keyed, 4 endpoints |
| Security headers           | ✅ in place  | Sent by `public/.htaccess` |
| Error visibility           | ✅ split     | Visible in dev, logged-only in prod |
| File access control        | ✅ in place  | `.htaccess` denies `/app /config /database /storage` |
| CSP                        | ⚠️ pragmatic | `'unsafe-inline'`+`'unsafe-eval'` on scripts (see "Tightening CSP") |

---

## 1. CSRF protection

**Where**: [app/helpers/functions.php](app/helpers/functions.php) — `csrf_token()`, `csrf_field()`, `csrf_check()`.

**How it works**:
- A 32-byte random token is generated on first read and stored in `$_SESSION['_csrf']`.
- Every POST form embeds `<?= csrf_field() ?>`.
- Every POST handler calls `csrf_check()` as the first action (after rate limiting). The check accepts the token from either the `_csrf` POST field *or* the `X-CSRF-Token` header — HTMX requests use the header (admin layout's `htmx:configRequest` listener attaches it from `<meta name="csrf-token">`).
- Comparison uses `hash_equals()` (constant-time).
- Failure → HTTP 419 + "CSRF token mismatch."

**Coverage audit** — every POST handler in the application calls `csrf_check()` on its first line. Verified by grep on 2026-05-14:

| Route | Handler | csrf_check |
|---|---|---|
| POST /login                                  | `AuthController::login`              | ✅ |
| POST /register                               | `AuthController::register`           | ✅ |
| POST /logout                                 | `AuthController::logout`             | ✅ |
| POST /book                                   | `BookingController::store`           | ✅ |
| POST /booking/{token}/cancel                 | `BookingController::cancelDo`        | ✅ |
| POST /booking/{token}/reschedule             | `BookingController::rescheduleDo`    | ✅ |
| POST /admin/services                         | `ServiceController::store`           | ✅ |
| POST /admin/services/{id}                    | `ServiceController::update`          | ✅ |
| POST /admin/services/{id}/toggle             | `ServiceController::toggle`          | ✅ |
| POST /admin/services/{id}/delete             | `ServiceController::destroy`         | ✅ |
| POST /admin/schedule                         | `ScheduleController::save`           | ✅ |
| POST /admin/blocked-dates/toggle             | `BlockedDateController::toggle`      | ✅ |
| POST /admin/blocked-dates/{id}/delete        | `BlockedDateController::destroy`     | ✅ |
| POST /admin/settings                         | `SettingController::save`            | ✅ |
| POST /admin/email-test/send                  | `EmailTestController::send`          | ✅ |
| POST /admin/appointments/{id}/status         | `AppointmentController::changeStatus`| ✅ |
| POST /admin/appointments/{id}/notes          | `AppointmentController::saveNotes`   | ✅ |
| POST /admin/appointments/{id}/resend-confirmation | `AppointmentController::resendConfirmation` | ✅ |

`SameSite=Lax` on the session cookie is a second line of defence.

---

## 2. SQL injection

**100% prepared statements.** Every query goes through `App\Core\Database`, which wraps PDO with `ATTR_EMULATE_PREPARES = false` — meaning placeholders are bound server-side by MySQL, never interpolated by PHP.

**Sweep results**:
- Variables interpolated into SQL strings: `$where` (built by `Appointment::buildWhere()` from a fixed allow-list — only field names and ordered placeholder names, no values), `$order` (literal `'ASC'`/`'DESC'`), table + column names in `Database::insert/update` (always from static model code, never user input), and explicit `(int)` casts on `LIMIT`/`OFFSET`.
- No user-controlled value is ever concatenated into a query string.
- Search filter uses `LIKE :q` with `%search%` bound as a parameter — no escape required.

---

## 3. XSS

**Every dynamic output uses `e()` or is explicitly trusted.**

- View files run dynamic values through `e()` (an `htmlspecialchars` wrapper with `ENT_QUOTES | ENT_SUBSTITUTE`).
- JSON embedded into HTML attributes (Alpine state initialisers, toast message payloads) is run through `json_encode(..., JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)` and then `htmlspecialchars()` — double-belt so it's safe inside `x-data='…'` even with awkward customer-name characters.
- Trusted HTML — the layout's `<?= $content ?>` and the email layout's `<?= $bodyHtml ?>` — only ever holds output that the framework just `ob_get_clean()`-ed from another template file. Those nested templates always use `e()`.
- Plain-text email templates (`/app/views/emails/text/*.txt.php`) **intentionally** do NOT escape — they're sent as `AltBody`, where HTML entities would render literally to the recipient.

**No `echo $var` of user input anywhere.** The grep is in `/SECURITY.md` history if you want to re-run it.

---

## 4. Session security

**Where**: [public/index.php](public/index.php)

```php
session_start([
    'name'             => 'slate_session',
    'cookie_httponly'  => true,                       // JS cannot read SID
    'cookie_samesite'  => 'Lax',                      // blocks most CSRF + lets top-level GETs through
    'cookie_secure'    => ($app['env'] === 'production'), // HTTPS-only in prod
    'use_strict_mode'  => true,                       // server refuses uninitialised IDs (fixation defence)
]);
```

**ID regeneration**:
- `session_regenerate_id(true)` on login (`AuthController::login`)
- Same on registration (`AuthController::register`)
- Same on logout (via `session_destroy()` + fresh `session_start()`)

**Idle-session timeout** (logged-in users only):
- `$_SESSION['last_activity']` is set on login/registration and refreshed on every authenticated request.
- After **2 hours** of inactivity, the session is destroyed, the cookie is cleared, a fresh session is started, and the user gets a flash: *"Your session expired. Please sign in again."*
- Anonymous sessions (just holding a CSRF token + flash queue) are not timed out — that would surprise customers mid-booking-form.

---

## 5. Authentication & passwords

**Hashing**: `password_hash($password, PASSWORD_ARGON2ID)`. Argon2id is the OWASP-recommended algorithm — memory-hard, side-channel resistant.

**Rehash on login**: `password_needs_rehash()` is checked on every successful login. If the algorithm or cost moves on, the user's stored hash is transparently upgraded.

**Rules** (NIST 800-63B style):
- Minimum length 8 characters
- At least one letter
- At least one number
- **No** maximum length, no character-class composition rules, no rotation

This deliberately stops short of the bad-old-days "must contain symbols, must rotate every 90 days" rules — which research shows hurt password quality more than they help.

**Account-enumeration**: failed login returns a single message — *"Invalid email or password"* — regardless of which field is wrong. The lookup goes through `User::findByEmail()` which is an indexed equality query (no meaningful timing oracle).

---

## 6. Rate limiting

**Where**: [app/middleware/RateLimit.php](app/middleware/RateLimit.php)

**Storage**: flat JSON files in `/storage/cache/ratelimit/`, keyed by `sha1(IP + '|' + endpoint-label)`. All read-update-writes happen under `flock(LOCK_EX)` so concurrent requests can't both win.

**Limits in place**:

| Endpoint | Limit | Window | Notes |
|---|---|---|---|
| POST /login    | 5  attempts | 15 min | Counter cleared on successful login |
| POST /register | 3  attempts | 1 hour | Defeats automated account creation |
| POST /book     | 10 attempts | 1 hour | Catches form-spammers, lets a real customer fat-finger their first attempt |
| GET /api/slots | 60 requests | 1 min  | Cheap to compute but hammer-able |

**Exceed behaviour**:
- HTTP 429 + `Retry-After` header
- Browser clients see a friendly `/app/views/errors/429.php` page
- HTMX clients (detected via `HX-Request: true`) get a tiny error fragment they can swap in

**Fail-open**: if the cache directory is unwritable, requests pass through and the failure is logged. The alternative — locking out every visitor because of a disk error — is worse.

**Garbage collection**: probabilistic (1% of calls) — files older than 24h are pruned.

---

## 7. Input validation

**Centralized helper available**: [app/core/Validator.php](app/core/Validator.php) — a tiny rule-based validator.

```php
$v = Validator::make($_POST, [
    'name'   => 'required|min:2|max:150',
    'email'  => 'required|email',
    'phone'  => 'required|phone',
    'notes'  => 'max:500',
    'status' => 'required|in:pending,confirmed,cancelled',
]);
if ($v->fails()) { errors_set($v->errors()); old_set($_POST); redirect('/back'); }
$clean = $v->validated();
```

Rules: `required`, `email`, `min:N`, `max:N`, `in:a,b,c`, `date`, `time`, `phone`, `regex:/pat/`, `integer`, `numeric`. Sanitization helper `Validator::stripTagsFrom($data, ['notes', …])` strips HTML before validation.

**Existing controllers** all validate inputs ad-hoc with explicit checks (see e.g. `BookingController::validateFormInput`, `AuthController::register`, `ServiceController::validate`). These are equivalent in coverage to the Validator — the class exists so future code can be terser and more consistent. Migrating existing controllers is a nice-to-have refactor, not a security gap.

**Universal sanitisation**: every controller calls `trim()` on string inputs as it reads them. Notes/description fields are length-clamped server-side regardless of any client-side `maxlength`.

---

## 8. File access control

Direct HTTP access to non-public folders is denied by `.htaccess`:

| Folder | File | Reason |
|---|---|---|
| `/app`      | [app/.htaccess](app/.htaccess)      | Controller / model / view source |
| `/config`   | [config/.htaccess](config/.htaccess) | Database creds, mail creds |
| `/database` | [database/.htaccess](database/.htaccess) | Schema + seed (incl. admin hash) |
| `/storage`  | [storage/.htaccess](storage/.htaccess) | Rate-limit counters + error logs |

Plus the root `/.htaccess` forwards every request into `/public`, so in a correctly-configured deployment these requests never reach those folders anyway. Defense-in-depth.

`/public/.htaccess` additionally:
- denies dotfiles (`.env`, `.git`)
- unsets the `X-Powered-By` banner

---

## 9. Error handling

**Visibility split** — see [public/index.php](public/index.php):

| Env             | `display_errors` | `log_errors` | Error log path |
|---|---|---|---|
| `APP_DEBUG=true`  | 1 | 1 | PHP default (usually stderr) |
| `APP_DEBUG=false` | 0 | 1 | `/storage/logs/php-errors.log` |

**Uncaught throwables** are caught by the global `try/catch` around `$router->dispatch()`. In production, the user sees the friendly `/app/views/errors/500.php` with a 4-byte reference code; the same code is written to the error log so support can correlate.

---

## 10. Security headers

Sent from [public/.htaccess](public/.htaccess) for every response:

```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()
Content-Security-Policy: default-src 'self';
                          script-src 'self' 'unsafe-inline' 'unsafe-eval'
                              https://cdn.jsdelivr.net https://unpkg.com;
                          style-src 'self' 'unsafe-inline'
                              https://fonts.googleapis.com https://cdn.jsdelivr.net;
                          font-src 'self' https://fonts.gstatic.com data:;
                          img-src 'self' data:;
                          connect-src 'self';
                          frame-ancestors 'none';
                          base-uri 'self';
                          form-action 'self'
Strict-Transport-Security: max-age=31536000; includeSubDomains   ← HTTPS only
```

`X-Powered-By` is unset. HSTS is conditional — Apache only emits it when `HTTPS=on`, so local-dev over HTTP doesn't lock the browser to TLS-only.

### Tightening CSP

The CSP above includes `'unsafe-inline'` and `'unsafe-eval'` on `script-src`. This is **deliberate** but **looser than ideal**:

- `'unsafe-inline'` is needed for the inline `<script>` blocks in the layouts (HTMX/CSRF wiring, Alpine app-state bootstrap).
- `'unsafe-eval'` is needed for Alpine v3's standard CDN build, which uses `new Function()` to evaluate `x-data` / `@click` expressions.

**The path to strict CSP** (a portfolio-worthy follow-up):

1. Move every inline `<script>` block from the layouts and views into files under `/public/assets/js/` (e.g. `app.js`, `booking.js`, `admin-appointments.js`).
2. Swap `alpinejs/dist/cdn.min.js` for `alpinejs/dist/csp.min.js` — the CSP-friendly build that uses `Alpine.data()` registrations instead of evaluating inline expressions.
3. Drop `'unsafe-inline'` and `'unsafe-eval'` from the CSP header.

That refactor is mechanical but invasive (the Alpine API surface changes), so it's left as a planned improvement, not a security gap — the rest of the policy still blocks the things that matter (origin lock, no framing, no plugin objects, no inline styles outside the allowlist).

---

## 11. Things deliberately out of scope

| Concern | Why |
|---|---|
| 2FA on admin accounts                    | Out of scope for a single-tenant booking system; revisit if Slate goes multi-tenant. |
| Account lockout (vs. rate limiting)      | Lockout creates a DoS vector against legitimate users. IP-based rate limiting is the lighter-touch alternative. |
| WAF / Cloudflare-style edge mitigation   | Deployment-layer concern, not application code. |
| Audit log of admin actions               | Would be a small migration + a writer in every mutation. Nice to have; not security-critical when only `role='admin'` is privileged. |
| Token rotation on cancel/reschedule      | `cancel_token` deliberately stays stable so the same URL keeps working until the appointment is over. Tokens are 128-bit random; brute-force is infeasible. |

---

## Operational checklist for production

Before flipping `APP_ENV=production` and pointing DNS:

- [ ] `.env` has a strong, unique `DB_PASSWORD` (not the dev placeholder)
- [ ] `.env` has Gmail App Password or production SMTP creds in `MAIL_*`
- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `.env`
- [ ] HTTPS configured at the web server (TLS cert installed)
- [ ] `cookie_secure: true` will now apply automatically (driven by `APP_ENV`)
- [ ] HSTS header will now activate automatically (driven by `HTTPS=on`)
- [ ] `/storage/cache/ratelimit/` and `/storage/logs/` are writable by the PHP user
- [ ] `database/seed.sql` admin hash regenerated with `php database/seeders/make_admin.php`
- [ ] Cron job for `/app/jobs/SendReminders.php` installed (see file header)
- [ ] Test 404 + 500 pages by visiting a bogus URL and triggering a deliberate exception
- [ ] Test rate limiting by hitting `/login` 6 times in a row with bad creds — 6th response should be 429
