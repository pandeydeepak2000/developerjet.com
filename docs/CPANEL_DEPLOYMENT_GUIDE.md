# cPanel Deployment & Operations Guide

For the owner/operator running DeveloperJet on cPanel shared hosting. This is an **internal** guide — customers
never see it. Customer-facing help lives in the Documentation centre at `/docs` (source: `database/seeders/docs/`).

Verified against a live cPanel account: PHP 8.2.36, Laravel 12.70.2, MySQL, `~/developerjet.com`.

---

## 0. Where the documentation lives

| Audience | Location | How to read it |
|---|---|---|
| **Customers** | `database/seeders/docs/**/*.md` → seeded into the DB | On the site at `/docs` (25 articles, searchable) |
| **Support / Sales staff** | `docs/ADMIN_OPERATIONS_GUIDE.md`, `docs/SALES_TEAM_OPERATIONS.md`, `docs/STAFF_*.md` | In the repo |
| **You (owner / DevOps)** | `docs/CPANEL_DEPLOYMENT_GUIDE.md` (this file), `docs/PRODUCTION_READINESS_CHECKLIST.md`, `docs/01_DEVELOPER_GUIDE.md` | In the repo |

Customer docs are edited in **Admin → Documentation** or by changing the `.md` files and re-running
`php artisan db:seed --class=DocsSeeder`. The seeder never overwrites an article a staff member edited in Admin.

---

## 1. First-time deployment

Run these from **cPanel → Terminal** (or SSH), inside the project root.

```bash
cd ~/developerjet.com

# 1. Dependencies (vendor/ is NOT in git; public/build/ IS in git, so no npm needed)
composer install --no-dev --optimize-autoloader

# 2. Environment
cp .env.example .env        # only on a brand-new install
nano .env                   # set the values in section 2 below
php artisan key:generate    # ONLY if APP_KEY is empty - never regenerate on a live site

# 3. Database
php artisan migrate --force
php artisan db:seed --force # catalogue, RBAC roles, docs, providers

# 4. Storage symlink (REQUIRED - invoice PDFs and ticket attachments break without it)
php artisan storage:link

# 5. Caches
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

# 6. Super Admin (interactive - you type the password at a hidden prompt)
php artisan staff:status you@yourdomain.com
php artisan staff:create-super you@yourdomain.com "Your Name"
```

Then sign in at `https://yourdomain.com/staff/developerjet/login` and complete first sign-in:
email code → new password → enrol an authenticator app → **store the recovery codes offline**.

> **Warning:** Never run `migrate:fresh`, `db:seed` with `--force` on a populated DB without a backup, or
> `key:generate` on a live site. Re-generating `APP_KEY` makes every encrypted column (AI API keys, SSH
> credentials, staff TOTP secrets) permanently unreadable.

---

## 2. `.env` values for production

```ini
APP_ENV=production
APP_DEBUG=false                 # never true in production - it leaks stack traces and .env values
APP_URL=https://yourdomain.com  # must be the real https URL, or reset links and emails are wrong

DB_CONNECTION=mysql
DB_HOST=localhost               # on cPanel this is usually localhost, NOT 127.0.0.1
DB_PORT=3306
DB_DATABASE=USER_developerjet
DB_USERNAME=USER_dbuser
DB_PASSWORD=<strong password>

SESSION_DRIVER=database         # survives across processes; file also works on a single server
SESSION_LIFETIME=480
STAFF_SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=true      # only when the site is served over HTTPS

QUEUE_CONNECTION=database       # NOT sync - see section 4
CACHE_STORE=database

MAIL_MAILER=smtp                # see section 5
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_USERNAME=support@yourdomain.com
MAIL_PASSWORD=<mailbox password>
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=support@yourdomain.com
MAIL_FROM_NAME="DeveloperJet"

# Provisioning safety switches - LEAVE THESE OFF
PROVISIONING_WORKER=dry_run
PROVISIONING_LIVE_ENABLED=false
```

After any `.env` change: `php artisan config:cache` (config is cached, so edits are ignored until you re-cache).

---

## 3. Document root

The web server must serve `public/`, not the project root. Two options:

1. **Preferred** — in cPanel → Domains, set the domain's document root to `/home/USER/developerjet.com/public`.
2. **Fallback** — keep the docroot at the project root and use a root `.htaccess` that rewrites everything into
   `public/`. Never move or copy `.env` into `public/`.

Verify: `https://yourdomain.com/up` should return a healthy response, and `https://yourdomain.com/.env`
must return **404 or 403**. If `.env` is downloadable, stop and fix the docroot immediately.

---

## 4. Queues and scheduler (cPanel Cron Jobs)

Your live server showed `Queue: sync` — with sync, every email and deployment job runs inside the web request and
can time out. Set `QUEUE_CONNECTION=database` in `.env`, then add two cron jobs in **cPanel → Cron Jobs**:

```bash
# Every minute - runs due scheduled tasks (billing:run, reminders, monitoring)
/usr/local/bin/php /home/USER/developerjet.com/artisan schedule:run >> /dev/null 2>&1

# Every 5 minutes - drains the queue, exits when empty so new code is always picked up
/usr/local/bin/php /home/USER/developerjet.com/artisan queue:work database --stop-when-empty --tries=3 --timeout=120 >> /dev/null 2>&1
```

Replace `USER` with your cPanel username and check the PHP path with `which php` (your server uses
`/usr/local/bin/php`). After a deploy, run `php artisan queue:restart` so workers reload the new code.

---

## 5. Real email (SMTP)

Your live server showed `Mail: log` — verification codes and reset links are being written to
`storage/logs/laravel.log` instead of delivered. Customers cannot verify accounts or reset passwords until this
is fixed.

1. In cPanel → Email Accounts, create `support@yourdomain.com` with a strong password.
2. Put the SMTP values from section 2 into `.env`, then `php artisan config:cache`.
3. Sign in as Super Admin → **Settings → Email (SMTP)** → enter the same values → **Send test email**.
4. Confirm the test arrives (check spam). OTP and reset flows now work for customers.

> On cPanel, port 465 (ssl) or 587 (tls) with `mail.yourdomain.com` normally works. If sending fails, ask the
> host whether outbound SMTP is restricted.

---

## 6. Updating the site after a Git push

Run in cPanel → Terminal, inside `~/developerjet.com`:

```bash
cd ~/developerjet.com
php artisan down --render="errors::503"   # optional for bigger updates

git fetch origin main
git reset --hard origin/main              # server has no local code edits; .env is NOT tracked so it is kept

composer install --no-dev --optimize-autoloader
php artisan migrate --force               # safe: migrations are additive
php artisan db:seed --class=DocsSeeder    # only when docs changed - never overwrites Admin edits

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

php artisan up
```

`public/build/` is committed to Git, so **npm is not needed on the server**. If assets look stale, confirm
`public/build/manifest.json` came with the pull.

---

## 7. Health checks after every deploy

```bash
php artisan about              # Env=production, Debug=OFF, Queue=database, Mail=smtp, storage LINKED
php artisan migrate:status     # every migration "Ran"
php artisan staff:status you@yourdomain.com
curl -s -o /dev/null -w "%{http_code}\n" https://yourdomain.com/up      # expect 200
curl -s -o /dev/null -w "%{http_code}\n" https://yourdomain.com/.env    # expect 403/404, NEVER 200
```

If `.env` ever returns 200, treat it as an incident: fix the docroot (section 3), then rotate every credential
in `.env` (database password, SMTP password, gateway keys) and re-run `php artisan config:cache`.

---

## 8. Backups (not implemented in-app yet)

- **Database**: cPanel → Backup Wizard → download a full backup weekly, plus before every `migrate`.
- **Files**: back up `storage/app/private` (invoices, ticket attachments) and `.env` separately. `.env` never
  goes into Git.
- Test a restore on a staging copy at least once.

---

## 9. What stays OFF until explicitly approved

- Live provisioning (`PROVISIONING_LIVE_ENABLED=false`) — SSH transport is inert by design; deployment tests
  run against a fake transport only.
- Supplier APIs and Proxmox — no verified credentials exist.
- n8n resale (`n8n.resale_verified`) and OpenClaw spec (`openclaw.spec_approved`) — owner decisions.
- Automatic suspension (`billing.live_suspension_enabled`) — staff tasks are created instead.

See `docs/PRODUCTION_READINESS_CHECKLIST.md` for the full owner-decision list.

