# Slate test suite

Focused on the high-risk logic — the booking engine, the input validator,
the .ics generator, the booking lifecycle, and the auth/CSRF/rate-limit
gates. Not chasing coverage percentages; chasing the things that would
embarrass us if they broke.

## What's covered

| Suite | File | Focus |
|---|---|---|
| Unit | `tests/Unit/SlotGeneratorTest.php` | Closed days, blocked dates, past dates, buffer math, conflict detection, status filtering, close-time overflow. |
| Unit | `tests/Unit/ValidatorTest.php` | Happy + unhappy path per rule (`required`, `email`, `min`/`max`, `in`, `date`, `time`, `phone`), plus optional-skip + first-failure-wins. |
| Unit | `tests/Unit/IcsGeneratorTest.php` | CRLF line endings, comma/semicolon/backslash/newline escaping, UID uniqueness, METHOD/SEQUENCE pairing on CANCEL, UTC conversion. |
| Feature | `tests/Feature/BookingFlowTest.php` | Guest book, conflict detection (incl. concurrent-sim), cancel-token uniqueness, confirmation-email logging, customer-side cancel, invalid/unknown tokens. |
| Feature | `tests/Feature/AuthTest.php` | Register / login / wrong password / NIST password rules / rate-limit-after-5 / counter reset after success / CSRF gates. |

About 40 tests total.

## Setup

### 1. Install dev dependencies

```bash
composer install
```

### 2. Create the test database

```sql
CREATE DATABASE slate_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 3. Import the schema

```bash
mysql -u root slate_test < database/schema.sql
```

(No need to import `seed.sql` — every test re-seeds its own baseline in
`TestCase::setUp()`.)

### 4. Optional: a dedicated test `.env`

If your local `.env` points at a non-default MySQL setup, set the same
values in your shell *or* override at run-time:

```bash
DB_HOST=127.0.0.1 DB_USERNAME=root DB_PASSWORD=secret ./vendor/bin/phpunit
```

The bootstrap forces `DB_DATABASE=slate_test`, `APP_ENV=testing`, and
`MAIL_DRIVER=log` regardless of what `.env` contains, so production
data is never at risk.

## Running

```bash
# Whole suite
./vendor/bin/phpunit

# Just one suite
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Feature

# Pretty named output
./vendor/bin/phpunit --testdox

# Just one file
./vendor/bin/phpunit tests/Unit/SlotGeneratorTest.php

# Just one test
./vendor/bin/phpunit --filter test_it_respects_buffer_time_between_appointments
```

Composer also exposes shortcuts:

```bash
composer test          # whole suite
composer test:unit
composer test:feature
```

## How the tests work

### `TestCase::setUp()`

Runs before *every* test:

1. **`resetDatabase()`** — `TRUNCATE` every domain table.
2. **`seedDefaults()`** — re-inserts 4 services, 7 business-hour rows, and
   the settings the SlotGenerator needs (`buffer_minutes=15`,
   `min_notice_hours=0` so tomorrow is bookable, `advance_booking_days=365`).
3. **`resetRateLimits()`** — wipes `/storage/cache/ratelimit/*.json`.
4. **`resetSuperglobals()`** — clears `$_POST` / `$_GET` / `$_SESSION` and
   plants a fresh `$_SESSION['_csrf']`.

### Factories

`createService($overrides)`, `createAppointment($overrides)`, `createUser($overrides)`,
`authenticate($user)`. Each returns the fresh row.

### Capturing redirects

The production `redirect()` helper normally calls `exit`. Under
`APP_TESTING` it throws `App\Core\TestRedirect` carrying the target URL.
Tests use `$this->captureRedirect(fn() => $controller->action())` to
assert the redirect target.

### Skipping SMTP

`MAIL_DRIVER=log` in the bootstrap tells the Mailer to skip PHPMailer and
just insert into `email_log`. The `booking_sends_confirmation_email`
test then asserts the row exists.

### Rate-limit reset

`RateLimit::reject()` throws `App\Core\TestExit` in test mode (same
pattern as `redirect`). `test_rate_limit_kicks_in_after_5_failed_logins`
uses `expectException(TestExit::class)` on the 6th attempt.

## CI

`.github/workflows/tests.yml` runs the suite on every push and PR against
`main`/`master`. PHP 8.2, MySQL 8.0 service container, no extra config
needed.

Add the green badge to your README once you push:

```markdown
[![Tests](https://github.com/yourhandle/slate/actions/workflows/tests.yml/badge.svg)](https://github.com/yourhandle/slate/actions/workflows/tests.yml)
```
