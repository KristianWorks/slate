# Slate

> A booking appointment system built from scratch in vanilla PHP 8.2 — no framework, no calendar library, no build step. Editorial typography on a calm cream-and-terracotta palette. Custom split-view calendar, race-safe slot booking, transactional emails, a public REST API, outbound webhooks, and a full security pass. Built to prove that a small business app can feel premium without bringing along React, Node, or Laravel.

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-3-77C1D2?style=flat&logo=alpine.js&logoColor=black)](https://alpinejs.dev/)
[![HTMX](https://img.shields.io/badge/HTMX-1.9-3D72D7?style=flat)](https://htmx.org/)
[![PHPUnit](https://img.shields.io/badge/tests-PHPUnit-3776AB?style=flat)](https://phpunit.de/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green?style=flat)](LICENSE)

**[Live demo →](#)**  ·  **[Case study →](docs/CASE_STUDY.md)**  ·  **[Security notes →](SECURITY.md)**  ·  **[Deployment guide →](DEPLOYMENT.md)**  ·  **[API docs →](#rest-api)**

---

## Screenshots

| | |
|---|---|
| ![Landing hero](docs/screenshots/01-home-hero.png) | ![Split calendar](docs/screenshots/02-booking-calendar.png) |
| **Landing page** — editorial hero with an animated mini-calendar. | **Booking calendar** — 40/60 split, slot cards stagger in. |
| ![Booking form](docs/screenshots/03-booking-form.png) | ![Admin dashboard](docs/screenshots/04-admin-dashboard.png) |
| **Booking form** — floating labels, debounced terms checkbox, loading spinner state. | **Admin dashboard** — stat cards with serif numerals, no icons inside. |
| ![Appointments + panel](docs/screenshots/05-admin-appointments.png) | ![Email template](docs/screenshots/06-email-confirmation.png) |
| **Admin appointments** — list / calendar views with slide-in detail panel. | **Confirmation email** — table-based HTML, plain-text alternate, GCal link. |

*(Replace these with your own captures — see [Suggested screenshots](#suggested-screenshots-for-your-portfolio) below.)*

---

## Features

**Booking**
- **Custom split-view booking calendar** — server-rendered grid, HTMX month-swap animation, full keyboard navigation (`role="grid"`, arrow keys), with availability dots per service.
- **Race-safe slot booking** — concurrent attempts on the same slot are serialised via `SELECT ... FOR UPDATE` inside a transaction. Second customer gets a friendly *"that slot was just taken"* and the chance to pick again.
- **Transactional email lifecycle** — confirmation, cancellation, reschedule (with strikethrough old time), 24h reminder via cron, admin notification on every new booking. HTML + plain-text alternates, table-based for Outlook safety, each with a calendar-ready `.ics` attachment.
- **Timezone-aware throughout** — business timezone read from `settings` (defaults to `Asia/Manila`), applied consistently across the slot generator, SQL queries, emails, and Google Calendar export links (which convert to UTC explicitly). Phone input accepts any country's format.

**Public REST API**
- `/api/v1/services`, `/api/v1/availability`, `/api/v1/business-hours`, `/api/v1/bookings` — key-free, rate-limited public endpoints that mirror the web booking flow for integrators (a scheduling widget, a mobile app, Zapier).
- `/api/v1/admin/*` — Bearer-token-authenticated endpoints for managing appointments, services, and blocked dates programmatically. Tokens are generated/revoked from the admin panel (**API tokens** page), stored as salted hashes, shown in plaintext exactly once.
- Self-documenting at `/api/docs` with real request/response examples.

**Outbound webhooks**
- Subscribe any `https://` URL to `booking.created` / `booking.cancelled` / `booking.rescheduled` / `booking.completed` events from the **Webhooks** admin page.
- Delivery is queued (not synchronous with the triggering request), HMAC-SHA256 signed (`X-Slate-Signature`), retried on a 60s / 5min / 30min backoff, and auto-paused after 10 consecutive failures. A "send test event" button exists for verifying a receiver without needing a real booking.

**Admin panel**
- Today / Upcoming / All / Calendar views, search by name/email/reference, debounced notes auto-save, CSV export matching current filters, three-dot action menu (mark completed / no-show / cancel / resend email).
- Structured logs (`/admin/logs`) with live-tailing, API token and webhook management, an email-test console, and a demo-mode safe for public portfolio deployments (blocks destructive actions, nightly auto-reset).

**i18n**
- Every user-facing string in the app routes through `lang/<locale>.php` + the `__()` helper. Shipping with `en`; adding a language is a copy + translate, no code changes.

**Tested**
- A real PHPUnit suite (unit + feature) covering the slot generator, validator, `.ics` generation, and the full booking/auth flows against a MySQL test database, wired into GitHub Actions on every push.

**Production-grade security**
- File-based IP rate limiting, CSRF on every POST, Argon2id passwords, prepared statements only, full security header stack (CSP, HSTS, XFO, XCTO, Referrer-Policy). [See SECURITY.md](SECURITY.md).

**Polish layer**
- Fade-on-scroll sections, slide-in slot cards, skeleton screens, top progress bar, prefers-reduced-motion respect, AA-contrast colour pairs, skip-to-content link.

**No build step** — the source tree IS the deployable artifact. `composer install` and you're done.

---

## Architecture

```mermaid
flowchart LR
    A[Browser / API client] -->|HTTPS| B[/.htaccess<br/>forwards to /public/]
    B --> C["/public/index.php<br/>(front controller)"]
    C --> D[Dotenv + Config + Session<br/>+ Error handler + Logger]
    D --> E[Router]
    E -->|web route| F[Controller]
    E -->|/api/v1/* route| F2["Api\\* Controller<br/>(JSON envelope, token auth)"]
    F -->|reads/writes| G["Models (PDO)"]
    F2 --> G
    F -->|delegates to| H["Services<br/>(SlotGenerator, Mailer,<br/>WebhookDispatcher)"]
    G <-->|prepared statements| I[(MySQL)]
    H -->|SMTP| J[(Gmail / SES)]
    H -->|signed POST| K[(Subscriber webhook URLs)]
    F -->|view name + data + layout| L["view() helper"]
    L --> M["Server-rendered HTML<br/>(or HTMX fragment)"]
    M --> A
    F2 --> A
```

A single front controller, a tiny PSR-4-autoloaded router, fat-but-honest controllers, dumb models, and a handful of domain services. The view layer is plain PHP files captured with `ob_*` and stitched into a layout. The API layer is a thin parallel set of controllers sharing the same models. No magic.

---

## Tech stack

| Layer        | Choice                                          | Why |
|---|---|---|
| Language     | PHP 8.2 (`readonly`, enums, named args)         | Modern, expressive, no compilation. |
| Database     | MySQL 8 / MariaDB 10.5+ via PDO                 | Ubiquitous on shared hosting. |
| Dependencies | `vlucas/phpdotenv`, `phpmailer/phpmailer`, `nesbot/carbon` | Three runtime deps. `phpunit/phpunit` for tests. |
| UI base      | Bootstrap 5.3 (heavily overridden)              | Reset + grid utilities; design system on top. |
| Interactivity| HTMX 1.9 + Alpine.js 3                          | Server-rendered HTML with sprinkles of state. |
| Fonts        | Inter (body), Fraunces (display)                 | Both via Google Fonts. |
| Icons        | Lucide                                          | Single SVG sprite, hand-picked per page. |

---

## Quick start

### Option 1 — Docker (one command)

```bash
git clone https://github.com/yourhandle/slate.git
cd slate
docker compose up --build
# open http://localhost:8080
```

The MySQL service auto-loads `schema.sql` and `seed.sql` on first boot. Admin login is `admin@slate.test` — regenerate the password hash first with `php database/seeders/make_admin.php "yourpass"` and paste it into `seed.sql` before the first `compose up`.

### Option 2 — Local PHP + MySQL

```bash
git clone https://github.com/yourhandle/slate.git
cd slate
composer install
cp .env.example .env
# edit .env with your DB credentials and Gmail App Password

# create the database, then:
mysql -u root -p slate < database/schema.sql
mysql -u root -p slate < database/seed.sql

# point your web server's document root at /public
# (built-in dev server works too:)
php -S localhost:8000 -t public
```

Visit `http://localhost:8000`. Sign in as `admin@slate.test` (see seed.sql for the password).

### Running the tests

```bash
composer install              # pulls in phpunit/phpunit (require-dev)
mysql -u root -e "CREATE DATABASE slate_test"
mysql -u root slate_test < database/schema.sql
composer test                 # or: vendor/bin/phpunit --testdox
```

`tests/bootstrap.php` points the app at `slate_test` and truncates/reseeds every table before each test — it never touches your dev database.

---

## REST API

Full reference with request/response bodies lives at `/api/docs` once the app is running. Shape:

| Endpoint | Auth | Purpose |
|---|---|---|
| `GET /api/v1/services` | none (rate-limited) | Active bookable services |
| `GET /api/v1/availability?service_id=&date=` | none | Slot grid for a day |
| `GET /api/v1/business-hours` | none | Weekly hours, timezone, booking rules |
| `POST /api/v1/bookings` | none | Create a booking (same race-safe path as the web form) |
| `GET /api/v1/bookings/{token}` | none | Look up a booking by its reference token |
| `POST /api/v1/bookings/{token}/cancel` | none | Cancel a booking |
| `GET/PATCH /api/v1/admin/appointments[/{id}]` | Bearer token | List/update appointments |
| `POST/PATCH/DELETE /api/v1/admin/services[/{id}]` | Bearer token | Manage services |
| `POST /api/v1/admin/blocked-dates` | Bearer token | Block a date |

Generate a token from **Admin → API tokens**. Admin endpoints are rate-limited per token; public endpoints are rate-limited per IP.

---

## Folder structure

```
slate/
├── app/
│   ├── controllers/
│   │   ├── admin/                   ← dashboard, appointments, services, schedule,
│   │   │                              blocked dates, settings, API tokens, webhooks,
│   │   │                              email test, logs — all behind require_admin()
│   │   ├── api/                      ← public REST API (services, availability,
│   │   │   └── admin/                   business-hours, bookings) + admin/ API (token-authed)
│   │   ├── AuthController.php       ← login, register, logout
│   │   ├── ApiDocsController.php    ← renders /api/docs
│   │   └── BookingController.php    ← /book + /api/slots + /booking/{token}/*
│   ├── core/
│   │   ├── Database.php             ← PDO singleton, prepared statements only
│   │   ├── Router.php               ← {param}-aware, tiny
│   │   ├── Logger.php               ← daily-rotated structured file logger
│   │   └── Validator.php            ← rule-based, optional
│   ├── helpers/functions.php        ← e(), view(), csrf_*(), flash(), redirect(), __()…
│   ├── jobs/                        ← SendReminders, ProcessWebhooks, ResetDemo —
│   │                                    CLI + cron-job.org compatible
│   ├── middleware/RateLimit.php     ← file-backed, flock-safe
│   ├── models/                      ← thin DAOs (Appointment, Service, BusinessHour,
│   │                                    BlockedDate, Setting, Webhook, ApiToken, User)
│   ├── services/
│   │   ├── Mailer.php               ← PHPMailer wrapper, 5 templates
│   │   ├── SlotGenerator.php        ← the heart of the booking engine
│   │   ├── IcsGenerator.php         ← calendar-file generation for emails
│   │   └── WebhookDispatcher.php    ← signs + enqueues outbound webhook deliveries
│   └── views/
│       ├── admin/                   ← dashboard, services, schedule, appointments,
│       │                              settings, api_tokens, webhooks, logs, email-test
│       ├── api_docs/                ← self-serve API documentation page
│       ├── auth/                    ← login, register
│       ├── booking/                 ← split-view calendar, slot panel, form,
│       │                              confirmation, cancel, reschedule
│       ├── emails/                  ← shared layout + HTML templates + text alternates
│       ├── errors/                  ← 404, 429, 500
│       ├── layouts/                 ← main (public) + admin (sidebar)
│       └── home.php                 ← marketing landing page
├── config/                          ← app.php, db.php, mail.php (all read $_ENV)
├── database/
│   ├── schema.sql                   ← canonical full DDL, used for fresh installs
│   ├── seed.sql                     ← admin + services + business hours + settings (PH defaults)
│   ├── migrations/                  ← dated, additive SQL for existing deployments
│   └── seeders/make_admin.php       ← CLI to generate Argon2id hashes
├── lang/en.php                      ← every user-facing string, keyed for i18n
├── docs/
│   ├── CASE_STUDY.md                ← portfolio narrative
│   └── screenshots/                 ← drop your captures here
├── public/                          ← web root
│   ├── assets/css/app.css
│   ├── favicon.svg
│   ├── index.php                    ← front controller + all route registrations
│   └── .htaccess                    ← pretty URLs + security headers
├── storage/
│   ├── cache/ratelimit/             ← JSON files, flock-locked
│   └── logs/                        ← app-YYYY-MM-DD.log + php-errors.log
├── tests/
│   ├── Unit/                        ← SlotGenerator, Validator, IcsGenerator
│   ├── Feature/                     ← BookingFlow, Auth
│   ├── TestCase.php                 ← shared fixtures/factories
│   └── bootstrap.php                ← points the app at slate_test
├── .env.example
├── composer.json
├── docker-compose.yml
├── Dockerfile
├── DEPLOYMENT.md
├── README.md
└── SECURITY.md
```

---

## Why these choices

### No framework

Laravel would have shaved 70% off the build time. The point of *this* project was the opposite: to **prove I understand what a framework does for me**. The Router, the Database singleton, the Validator, the Mailer, the Logger, the view rendering helper — they're all hand-rolled. Each one is 50–250 lines and I can walk an interviewer through every line.

### HTMX over React

A React + Vite + Tailwind stack would have been the trendy choice. HTMX is the contrarian one — and the right one for a server-rendered booking flow. Zero build step. No hydration mismatch. SEO-friendly by default. Degrades gracefully when JS fails. Alpine.js handles the small pieces of true client state (modal open / closed, calendar selected date) that don't make sense as round-trips.

### Custom calendar

I could have dropped FullCalendar in 20 minutes. Building the calendar from scratch — server-rendered grid, HTMX-swapped month navigation, keyboard arrow-key support, `role="grid"` + `role="gridcell"` semantics, availability dot per day per service — was 6× the work. But the source code is now legible from first principles. That's a stronger portfolio signal than "I called `<FullCalendar />` with the right props."

### A REST API and webhooks, hand-rolled too

Once the booking engine existed, exposing it over HTTP for integrators was mostly a matter of a JSON response envelope and a token-auth check — not a new framework. `ApiController` is ~80 lines; `WebhookDispatcher` (sign, enqueue, retry with backoff) is another ~100. Both reuse the exact same models and `SlotGenerator` the web UI uses, so there's one source of truth for availability math, not two.

The long-form version of all of this lives in [the case study](docs/CASE_STUDY.md).

---

## Security

A separate, more thorough document: [SECURITY.md](SECURITY.md).

Headlines:

- **CSRF** — `csrf_check()` on every browser-originated POST (header or POST field, `hash_equals()` comparison).
- **SQL injection** — 100% prepared statements via PDO with `ATTR_EMULATE_PREPARES = false`.
- **XSS** — every dynamic view output through `e()`; JSON embedded in attributes uses `JSON_HEX_*` flags + `htmlspecialchars()`.
- **Sessions** — `HttpOnly`, `SameSite=Lax`, `Secure` in production, `use_strict_mode`, 2-hour idle timeout for authenticated users.
- **Passwords** — Argon2id, NIST-style rules (8+ chars, ≥1 letter, ≥1 number, no composition silliness).
- **API auth** — Bearer tokens stored as salted hashes, shown in plaintext once, revocable, rate-limited separately from public traffic.
- **Webhook signing** — outbound deliveries carry an HMAC-SHA256 signature receivers can verify.
- **Rate limiting** — file-backed, IP-keyed (or token-keyed for admin API), sensible windows per endpoint.
- **Headers** — CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.

---

## Roadmap

- **Stripe payments** — services with `price > 0` flip to `status='pending'` until a checkout session succeeds. The `appointments.status` enum already includes `pending`; the integration point is marked with a `TODO: Stripe` comment in `BookingController::store()`.
- **Twilio SMS reminders** — the `Mailer` class abstracts dispatch; an `SmsService` would slot in alongside it and the `SendReminders` job would dispatch both.
- **Recurring appointments** — "every Tuesday at 3pm" via an `appointments.recurrence_rule` column + a cron-driven expansion job.
- **Multi-staff support** — add `staff_id` to services, appointments, and business_hours; the SlotGenerator gets an extra filter.
- **i18n beyond English** — the string tables exist (`lang/en.php`); adding a locale is translation work, not engineering.
- **Stricter CSP** — `script-src` still carries `'unsafe-inline'`/`'unsafe-eval'` for the inline Alpine/HTMX wiring; [SECURITY.md](SECURITY.md#tightening-csp) documents the 3-step path to remove both.

The public REST API and outbound webhook system that used to be on this list are built — see [REST API](#rest-api) above.

---

## Suggested screenshots for your portfolio

Drop into `docs/screenshots/`:

| Filename | What to capture |
|---|---|
| `01-home-hero.png`         | Landing page, scrolled to top, slot pills mid-fade |
| `02-booking-calendar.png`  | `/book` with a date selected, slot cards visible |
| `03-booking-form.png`      | Form panel slid in, floating labels active |
| `04-admin-dashboard.png`   | `/admin` with the four stat cards |
| `05-admin-appointments.png`| `/admin/appointments` with the slide panel open |
| `06-email-confirmation.png`| A rendered confirmation email (Gmail or Mailtrap preview) |
| `07-mobile.png`            | iPhone-width view of `/book` |
| `08-api-docs.png`          | `/api/docs` showing a sample request/response |

---

## License

MIT. See [LICENSE](LICENSE).
