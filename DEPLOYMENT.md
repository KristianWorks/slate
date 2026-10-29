# Deploying Slate

Three deploy paths, ranked from "easiest, free, slightly hacky" to
"most professional, still free or near-free":

| Path | Cost | Effort | Custom domain | What it signals |
|---|---|---|---|---|
| **A. InfinityFree** (shared cPanel)        | $0          | ~30 min | optional       | Working public demo |
| **B. Railway / Fly.io** (Docker)           | $0–5 / mo   | ~20 min | optional       | Containerised, prod-shaped |
| **C. Self-hosted VPS** (Docker)            | $4–6 / mo   | ~45 min | yes            | "I deploy things" |

Pick the path that matches the story you want to tell on your CV.

---

## Path A — InfinityFree (free shared PHP + MySQL)

Best for: a working URL to put on a CV without paying anything.

[InfinityFree](https://infinityfree.net) gives you unlimited domains, unlimited
storage, MySQL databases, FTP, phpMyAdmin, and a free `*.infinityfreeapp.com`
subdomain. No expiration. The catch: shared hosting limits (no shell access,
slower CPU, no real cron — you'll work around the last one).

### A.1 Create the account

1. Sign up at infinityfree.net.
2. Create a hosting account → choose a free subdomain (e.g. `slate.infinityfreeapp.com`).
3. Note the FTP credentials shown on the dashboard.

### A.2 Create the database

1. Control Panel → **MySQL Databases** → Create a new database.
2. Note the host (e.g. `sql123.infinityfree.com`), database name, username, and password.
3. Open **phpMyAdmin** for that database.
4. Click **Import** → upload `database/schema.sql` → Go.
5. Open `database/seeders/make_admin.php` locally, edit the password if you
   want, then run it: `php database/seeders/make_admin.php "YourAdminPassword"`.
   It prints an `UPDATE` statement; copy that and a modified `database/seed.sql`
   somewhere local with the real hash pasted in, then **Import** that into phpMyAdmin.

### A.3 Upload the code

1. Run `composer install --no-dev --optimize-autoloader` locally so `vendor/` is populated.
2. Connect to FTP using the credentials from A.1 (FileZilla works fine).
3. Upload **the entire project** to `htdocs/` *except*:
   - `.env` (you'll create one on the server)
   - `.git/` / `node_modules/` / `docs/`
4. On the server, copy `.env.example` to `.env` and edit it through cPanel's
   file manager. Fill in `DB_*` from A.2 and `MAIL_*` from your Gmail App Password.
   Set `APP_ENV=production` and `APP_DEBUG=false`.

### A.4 Point the web root at /public

InfinityFree serves from `htdocs/` by default. The root `.htaccess` forwards
all requests into `/public/`, so this works out of the box — but you can also
move `public/index.php`, `public/assets/`, `public/favicon.svg`, and `public/.htaccess`
directly into `htdocs/` and adjust the `BASE_PATH` line in `index.php` to
`dirname(__DIR__)` → `dirname(__FILE__)`. The former is simpler; only do the
latter if InfinityFree's mod_rewrite is acting up.

### A.5 Reminder cron (without shell cron)

InfinityFree doesn't expose system cron. Use **cron-job.org** (free):

1. Add a `JOBS_KEY=some-long-random-string` to `.env`.
2. Add this route to `public/index.php` just above the 404 handler:

   ```php
   $router->get('/jobs/reminders', function (): void {
       if (!hash_equals((string) env('JOBS_KEY', ''), (string) ($_GET['key'] ?? ''))) {
           http_response_code(403); echo 'forbidden'; return;
       }
       require base_path('app/jobs/SendReminders.php');
   });
   ```

3. Sign up at [cron-job.org](https://cron-job.org), schedule a GET to
   `https://your-domain.infinityfreeapp.com/jobs/reminders?key=YOUR_JOBS_KEY`
   every 5 minutes.

---

## Path B — Railway (Docker)

Best for: showing employers you can containerise.

[Railway.app](https://railway.app) gives you ~$5/month free credit (enough to
run this app continuously). It auto-detects the `Dockerfile` and `docker-compose.yml`
in the repo.

### B.1 Push to GitHub

```bash
git init && git add . && git commit -m "Initial commit"
gh repo create slate --public --source=. --push
```

### B.2 Deploy

1. railway.app → New Project → Deploy from GitHub Repo → pick `slate`.
2. Railway detects the Dockerfile and builds the app service.
3. Click **+ New** → **Database** → **Add MySQL**. Railway provisions one and
   exposes `MYSQLHOST` / `MYSQLPORT` / `MYSQLUSER` / `MYSQLPASSWORD` / `MYSQLDATABASE`.
4. In the app service's **Variables** tab, map them:
   ```
   DB_HOST     = ${{MySQL.MYSQLHOST}}
   DB_PORT     = ${{MySQL.MYSQLPORT}}
   DB_DATABASE = ${{MySQL.MYSQLDATABASE}}
   DB_USERNAME = ${{MySQL.MYSQLUSER}}
   DB_PASSWORD = ${{MySQL.MYSQLPASSWORD}}
   APP_ENV     = production
   APP_DEBUG   = false
   APP_URL     = https://slate-production.up.railway.app
   APP_TIMEZONE= America/Toronto
   MAIL_HOST   = smtp.gmail.com
   MAIL_PORT   = 587
   MAIL_USERNAME = you@gmail.com
   MAIL_PASSWORD = xxxx-xxxx-xxxx-xxxx
   MAIL_ENCRYPTION = tls
   MAIL_FROM_ADDRESS = you@gmail.com
   MAIL_FROM_NAME = "Slate Bookings"
   ```
5. Hit **Generate Domain** on the app service. You're live.

### B.3 Run schema + seed once

Railway's MySQL service exposes a public connection string. Either:
- Use a desktop MySQL client (TablePlus / MySQL Workbench) → connect → run `schema.sql` and `seed.sql`.
- Or temporarily expose a one-shot init endpoint and remove it after running.

### B.4 Reminder cron

Railway has **scheduled tasks** in the service settings → "Cron Schedule".
Add a second service from the same repo with `Start Command: php app/jobs/SendReminders.php`
and a `*/5 * * * *` cron.

---

## Path C — Fly.io (Docker)

Similar story to Railway. The Dockerfile in this repo works directly with `fly launch`.

```bash
fly launch --copy-config --no-deploy
# Edit fly.toml to expose port 80 internally
fly secrets set DB_HOST=... DB_PASSWORD=... APP_ENV=production APP_DEBUG=false ...
fly deploy
```

For MySQL on Fly: either run `fly mysql create` (their managed offering) or
attach a $4/mo PlanetScale free-tier database via env vars.

---

## Path D — Local Docker (for your own laptop / demos)

Already wired up. Just:

```bash
cp .env.example .env       # tweak nothing — docker-compose.yml supplies env vars
docker compose up --build
open http://localhost:8080
```

Credentials:
- App: http://localhost:8080
- MySQL: `localhost:3307` user `slate` password `slate_secret` database `slate`
- Admin login: `admin@slate.test` / the password baked into `database/seed.sql` — regenerate it first with `php database/seeders/make_admin.php "admin123"` and paste the resulting hash into seed.sql before bringing the stack up.

To wipe and restart: `docker compose down -v && docker compose up --build`.

---

## Post-deploy checklist

Before sharing the URL with anyone:

- [ ] `.env` has `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `.env` has a fresh `DB_PASSWORD` you didn't commit anywhere.
- [ ] `.env` has working `MAIL_*` (Gmail App Password recommended). Send yourself a confirmation from `/admin/email-test` to verify.
- [ ] Admin password regenerated from the seed default. Run `php database/seeders/make_admin.php "RealPassword"` and replace the seed hash before importing.
- [ ] HTTPS configured (Railway, Fly.io, InfinityFree all give you free SSL — check the toggle).
- [ ] HSTS will auto-activate once HTTPS is on (via `public/.htaccess`).
- [ ] Reminder cron installed and tested — make a booking ~24h out and watch `email_log` fill.
- [ ] Hit a deliberately-broken URL (e.g. `/no-such-route`) and confirm the 404 page renders.
- [ ] Fail-login 6× in a row; the 6th response should be the 429 page.
- [ ] `/admin` only reachable after signing in as the admin user.

That's the deployment story. The README explains *what* you built; this file
explains *how to put it on the internet*.

---

## Demo mode (for the public portfolio deployment)

When this app is hosted as a public portfolio piece, recruiters will sign in
and poke around. Demo mode lets them safely explore without breaking the
demo for the next visitor.

### Turn it on

In your production `.env`:

```dotenv
DEMO_MODE=true
DEMO_RESET_TOKEN=replace-me-with-a-long-random-string
```

Now:
- A "Demo credentials" banner shows on `/` and `/login` with one-click clipboard copy
- A small `DEMO` badge appears in the admin sidebar
- The demo-admin user (`demo-admin@slate.test`) is **blocked** from deleting services, changing business hours, and cancelling appointments — they get a friendly toast instead
- The `/cron/reset-demo` endpoint becomes reachable (gated by `DEMO_RESET_TOKEN`)

Other accounts (real admins, real customers) are unaffected — only the
specific `demo-admin@slate.test` email triggers the guards.

### First-run seed

The first time you deploy with `DEMO_MODE=true`, run the reset job once
to create the demo accounts and seed sample appointments:

**Locally / SSH:**
```bash
php /path/to/slate/app/jobs/ResetDemo.php
```

**Via the HTTP endpoint** (if you can't SSH):
```
GET https://your-domain.tld/cron/reset-demo?token=<DEMO_RESET_TOKEN>
```

Watch the output — you should see lines like `Truncated: …`, `Re-seeded
4 services`, `Generated 8 sample appointments`, `--- Demo reset completed ---`.

After this, the demo accounts work:
- **Admin** → `demo-admin@slate.test` / `demo1234`
- **Customer** → `demo-user@slate.test` / `demo1234`

### Nightly automatic reset

Schedule the same job to run every 24 hours so the demo stays fresh:

**Linux / macOS cron** — runs at 03:00 local time:
```cron
0 3 * * * /usr/bin/php /var/www/slate/app/jobs/ResetDemo.php >> /var/log/slate-demo-reset.log 2>&1
```

**Windows Task Scheduler** — daily 03:00 trigger:
```
Action:    php.exe
Arguments: C:\xampp\htdocs\slate\app\jobs\ResetDemo.php
```

**Shared host without shell cron** (the InfinityFree path):

1. Sign up at [cron-job.org](https://cron-job.org).
2. Create a job:
   - URL: `https://your-demo.infinityfreeapp.com/cron/reset-demo?token=<DEMO_RESET_TOKEN>`
   - Schedule: every 24 hours, around 03:00 in your timezone
   - Method: GET
3. Save. The first scheduled run will reset the demo overnight.

### Safety notes

- The job hard-aborts unless `DEMO_MODE=true` — there is no override flag. You cannot accidentally trigger it on a non-demo deployment.
- `DEMO_RESET_TOKEN` is the only thing protecting the HTTP endpoint. Make it long (32+ random chars) and treat it like any other secret.
- The `/cron/reset-demo` route also returns 404 (not 403) when `DEMO_MODE=false`, so attackers can't even tell the endpoint exists outside of demo mode.
- Every reset is logged to `/storage/logs/demo-reset.log` with timestamps. Worth tailing if you suspect odd behaviour.
