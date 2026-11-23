# DeveloperJet — Project Context

Premium AI hosting & digital services commerce platform ("DeveloperJet — Powered by AmbitionHost").
Source of truth for requirements: `DeveloperJet_SRS_v0.1.md` (confirmed vs. proposed items are labelled there).

## Stack
Laravel 12 · PHP 8.2 · Blade · Tailwind v4 · Vite · MySQL (XAMPP, local) · PHPUnit (SQLite in-memory).

## Hard rules
- WHMCS is **never** integrated. Keep it fully separate.
- No live payments and no VPS provisioning until a later phase is explicitly approved.
- Do not invent prices or VPS resources. Unconfirmed values stay `pending`/`illustrative`/`displayed`.
- Do not push to GitHub or deploy to cPanel without explicit permission.
- Never print or commit `.env` secrets. Never run destructive DB commands without approval.

## Local environment
- Database: MySQL `developerjet_local` on `127.0.0.1:3306` (created fresh; existing XAMPP databases untouched).
- Tests use SQLite `:memory:` (see `phpunit.xml`) so they can never touch MySQL.
- Mail: `MAIL_MAILER=log`. OTP and reset emails are written to `storage/logs/laravel.log`
  (the OTP appears in plain text there — local development only). **SMTP is not configured or tested.**
- Run: `php artisan serve --host=127.0.0.1 --port=8000` (+ `npm run dev` for hot reload, or `npm run build`).
  Preview: http://127.0.0.1:8000

## Architecture (Phase 1)
- Auth: `app/Http/Controllers/Auth/*`; OTP logic in `app/Services/OtpService.php` (HMAC-hashed codes,
  10 min expiry, 5 attempts, 60 s resend cooldown, 5 sends/hour — engineering proposals, not owner-approved).
  `User` implements `MustVerifyEmail`; the `verified` middleware sends unverified users to the OTP page.
- Catalogue: `product_categories`, `resource_profiles`, `plans`, `plan_prices` (paise), `addons`, `addon_plan`.
  Seeded idempotently by `CatalogSeeder` (never overwrites admin edits).
- Cart: `carts`/`cart_items`, `CartService`; guests tracked by a session token; merged on the `Login` event
  (`MergeGuestCart`). Prices are read server-side only.
- Dashboard: `layouts/dashboard`, reusable `x-stat-card`, `x-empty-state`; policies for Order/Service/Invoice.
- Security: throttled auth routes, login lockout, no account enumeration on reset, `SecurityHeaders`
  middleware, `role` not mass-assignable, `no-store` on authenticated pages.

## Pending owner decisions
See `docs/02_PROJECT_STATUS.md` and SRS section 20 (Q-001 prices/resources, Q-014 email provider, etc.).

## v0.2 addendum
Hybrid KVM infrastructure (manual suppliers now; supplier APIs and Proxmox later, disabled). New families: Claude Code (managed) and Docker VPS (unmanaged). See docs/02_PROJECT_STATUS.md, docs/01_DEVELOPER_GUIDE.md (D-01..D-06, A-21..A-28) and docs/03_COMPLETE_WORKFLOW.md.

## v0.3 addendum (2026-11-20)
- Billing engine: `App\Services\Billing\SubscriptionService`, `App\Services\RenewalService` (`php artisan billing:run`, daily 08:00), `App\Jobs\SendRenewalReminder`, `App\Support\{BillingCycle,BillingSettings,SubscriptionStatus}`. Periods: monthly, quarterly, semiannual, yearly. See `docs/03_COMPLETE_WORKFLOW.md`.
- Suspension/reactivation only via a verified `ServiceSuspender` with `billing.live_suspension_enabled` (off). Default = staff tasks in Admin → Billing.
- Users have an optional-at-DB, required-at-registration `phone` (E.164). Customer Profile & security page at `/dashboard/profile`.
- Mobile drawer must stay outside `<header>` (see A-29).

## v0.5 addendum - Business Automation (2026-11-21)
- Navigation: three product groups only (AI Products, Developer Hosting, Digital Services); no standalone Cloud VPS group, no Managed vs Unmanaged menu entry; Docker VPS Hosting is the single canonical Docker link (config/navigation.php, NavigationStructureTest).
- Business Automation: /business-automation, three draft quote-only plans (Automation Starter/Growth/Enterprise, price_status pending), requirement form with CSRF/rate limiting/honeypot/signed timing token/sanitisation/secret redaction/duplicate suppression, queued customer ack + sales email + Telegram alert (fakes only in tests), 10-status sales quotation lifecycle with conversion to a project and no payment step. Docs: docs/03_COMPLETE_WORKFLOW.md, docs/SALES_TEAM_OPERATIONS.md, docs/STAFF_LOGIN_TROUBLESHOOTING.md.
- Super Admin: onboarding verified on a disposable test database only; the real account for ambitionmanagements@gmail.com was not created in this session, and the live MySQL check was blocked (connection refused on 127.0.0.1:3306 on 2026-11-21). Full suite: 391 passed / 3310 assertions.

## v0.4 addendum — completion sprint (2026-11-20)
Confirmed by the owner in the sprint brief: one shared staff login URL for all roles, default `/staff/developerjet/login`,
changeable only by a Super Admin; designated Super Admin email `ambitionmanagements@gmail.com` (account to be created by
the owner with the interactive command — no default credentials). Proposed defaults (owner may change): Super Admin MFA
mandatory, cancellation approval on, immediate cancellation off, automatic expiry off, add-ons billed from next renewal,
no SLA targets until set. Details: `docs/STAFF_PORTAL_GUIDE.md`, `docs/STAFF_ROLES_AND_PERMISSIONS.md`,
`docs/ADMIN_OPERATIONS_GUIDE.md`, `docs/PRODUCTION_READINESS_CHECKLIST.md`.
