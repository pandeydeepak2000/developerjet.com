# Developer guide - DeveloperJet

Last verified: 2026-11-21. Local only, nothing production verified.
Supersedes: docs/ARCHITECTURE_DECISIONS.md and the developer parts of docs/DEVELOPERJET_WORKFLOW_GUIDE.md. Backup: C:/tmp/djet-cleanup-backup-2026-11-21/docs.

## Stack and local run

Laravel 12, PHP 8.2, Blade, Tailwind v4, Vite, MySQL (XAMPP local), PHPUnit with SQLite in-memory.

Serve: php artisan serve --host=127.0.0.1 --port=8000
Assets: npm run dev (hot) or npm run build
Tests: php artisan test (SQLite in-memory, safe, never touches MySQL)
Migrate local DB: php artisan migrate, then php artisan db:seed --class=CatalogSeeder
Format: vendor/bin/pint (check with vendor/bin/pint --test)
Audit: composer audit
Queues and scheduler for renewals: php artisan schedule:work and php artisan queue:work
Local DB: MySQL developerjet_local on 127.0.0.1:3306. Mail: MAIL_MAILER=log, OTP and reset codes go to storage/logs/laravel.log, local only. SMTP is not configured or tested.

## Hard rules

- WHMCS must never be integrated.
- Do not implement live payments or VPS provisioning without explicit owner approval.
- Never invent prices or resources; unconfirmed values stay pending, illustrative or displayed.
- Do not push to GitHub, deploy to cPanel, or touch production. Ask before destructive actions: dropping tables, migrate:fresh, deleting data.
- Never reveal .env contents. Do not mark a feature complete without passing tests.
- Preserve uncommitted SRS files DeveloperJet_SRS_v0.1.md and .pdf.
- Money is integer paise; format with App\Support\Money::inr().
- New catalogue values go through the DB and seeder, never hard-coded in Blade.
- Keep existing marketing page structure; section counts are asserted in MarketingPagesTest.
- After each milestone update docs/02_PROJECT_STATUS.md with what is actually verified.
- Docs PDFs: rebuild with php scripts/build_diagrams.php and php scripts/build_docs_pdf.php after editing docs/*.md.

## Owner-confirmed decisions

- Hybrid KVM infrastructure: on-demand manual VPS purchase and manual assignment now, then auto-install; future provider APIs and dedicated Proxmox KVM (OVH etc.).
- Initial suppliers: DesiVPS, EricHost, Contabo Core and Performance Plus, Server Broker. Never assume a supplier API; verify with official docs and authorised credentials.
- Separate managed and unmanaged access models. No WHMCS. Never store plaintext root passwords.
- Live install commands, supplier adapters and Proxmox stay gated until explicitly approved. Do not claim full automation is live.
- Super Admin email designated: ambitionmanagements@gmail.com. Create only by the owner running php artisan staff:create-super interactively. No default credentials.
- Billing defaults proposed, owner may change: 7-day renewal invoice, reminders 7 and 3 days before, due date and 1 day overdue, 3 grace days, auto-suspend OFF.

## Architecture

- Auth: app/Http/Controllers/Auth, OtpService with HMAC-hashed codes, 10 minute expiry, 5 attempts, 60 second resend cooldown, 5 sends per hour. User implements MustVerifyEmail; verified middleware sends unverified users to OTP. Phone is optional in DB, required at registration, E.164, +91 default, no SMS.
- Catalogue: product_categories, resource_profiles, plans, plan_prices in paise, addons, addon_plan. Seeded idempotently by CatalogSeeder, never overwrites admin edits.
- Cart: carts and cart_items, CartService, guest session token, merged on Login event. Prices read server-side only. Checkout token makes placement idempotent.
- Dashboard: layouts/dashboard, reusable x-stat-card and x-empty-state, policies for Order, Service and Invoice. Customer records are owner-scoped.
- RBAC: roles to module.action grants, User::hasPermission is the single check, Gate::before maps it for @can. Seven seeded roles plus custom roles. Super Admin is a role flag. Staff and customer guards are separate; staff guard is staff, customer guard is web.
- Staff portal: /staff/{slug}/login, default slug developerjet, DB setting staff_portal.slug. /admin/login no longer exists; /admin is 404 for outsiders. Recovery: php artisan staff:portal --show, --set=, --reset. Sensitive staff routes use staff.reauth. Admin group uses named limiter throttle:staff-area, not a plain throttle.
- Staff MFA: TOTP RFC 6238, replay protection, recovery codes 10 x XXXXX-XXXXX, HMAC-SHA256 at rest, single use, rotation. Mandatory for Super Admins via STAFF_ENFORCE_PRIVILEGED_MFA, default true, false only in phpunit and QA.
- Orders: unique numbers, item and config snapshots, timeline, notes, assignment, cancel, refund workflow, audit log, enforced state machine. Paid only via OrderService::markPaid from verified payment or a fully wallet-funded order.
- Payments: Razorpay built and tested against fakes only, encrypted creds, server-side order, signature verification, webhook signature and idempotent event log, reconciliation command payments:reconcile. PTSure is a skeleton, disabled by payments.ptsure_integration_verified=false. Gateway refunds are recorded manually, no refund API call. No auto-charge.
- Wallet: append-only ledger, running balance, idempotent references, row locks, no negative balance, top-up requests to admin verification to single credit, audited adjustments.
- Invoicing and subscriptions: atomic sequence, configurable prefix, start and padding, PDF via dompdf, email via queue, credit notes, GSTIN only when configured, no invoice for unpaid orders. Subscriptions snapshot agreed price, discount and tax class; legacy rows take price from original order. Four periods: monthly, quarterly, semiannual, yearly.
- Billing engine: SubscriptionService, RenewalService, php artisan billing:run daily 08:00, SendRenewalReminder job, BillingCycle, BillingSettings, SubscriptionStatus. Renewal is an unpaid invoice plus renewal order, one per period, no setup fee on renewal, extends from paid-through date, month-end and leap-year safe. Suspension and reactivation go through ServiceSuspender, Null binding by default, and billing.live_suspension_enabled default off. Without a verified adapter, staff tasks and alerts are created instead.
- Provisioning: worker interface, only DryRunWorker exists, dry runs never touch service or order state. Live SshWorker is a failing placeholder; SshTransport is inert. Recipes are versioned config validated by RecipeRegistry; commands are fixed templates, null means not approved; only read-only probes are approved. AutomaticProvisioner has only a Null implementation; deploys require manual mode. One in-flight deployment per service.
- Notifications: notification_logs.dedupe_key unique, Telegram queued job with 3 tries and backoff, customer email via one editable template mailable, failures never store tokens. Real SMTP and Telegram are not verified.
- Uploads: private local disk, random names, extension allow-list, downloads authorised per record.
- Docs centre: 25 articles in database/seeders/docs/*.md, DocRenderer with single H1, search all-words ranked title over excerpt over body, DocsSeeder upgrades only unedited shipped bodies, legacy slugs 301 in config/docs.php. No domain sales content; Custom Domain Setup and HTTPS only.
- Exports: CSV only, ExportService, streamed in chunks, per-type permission, explicit column allow-lists, formula-injection escaping, no secrets or PII beyond what is needed. XLSX is not implemented.
- Security: throttled auth routes, login lockout, no account enumeration on reset, SecurityHeaders middleware, role not mass-assignable, no-store on authenticated pages, CSP subset, COOP, trustHosts outside local and testing.

## Decisions index

A-01 money as integer paise. A-02 tax never silently zero. A-03 paid only via markPaid. A-04 idempotency at checkout token, payment events and attempts, and markPaid row lock. A-05 illustrative prices are display-only, ordering needs price_status approved and plans.approve. A-06 wallet append-only. A-07 gateways behind an interface, PTSure inert skeleton. A-08 invoices only for paid orders, one per order, credit notes use prefix CN. A-09 RBAC roles to module.action. A-10 config-driven generic admin resources, dedicated controllers for complex flows. A-11 secrets encrypted and write-only. A-12 provisioning worker interface, DryRunWorker only. A-13 one in-flight deployment, tries 1, explicit retry. A-14 automatic mode cannot bypass checks. A-15 quotes never charge. A-16 notifications deduped and queued. A-17 uploads private with allow-list. A-18 n8n resale and OpenClaw spec are off by default. A-19 docs Markdown with raw HTML stripped. A-20 PDF via dompdf.
D-01 to D-06 hybrid KVM, manual assignment then auto-install, initial suppliers, future OVH and Proxmox, managed versus unmanaged, no WHMCS.
A-21 providers hold no credentials, verified_capabilities explicit. A-22 supplier cost separate from selling price, optional cost limit and minimum margin. A-23 recipes versioned, fixed templates, null not approved. A-24 RemoteTransport, inert SshTransport, host-key pin required, resume from completed steps. A-25 domain placeholder only from DNS-verified validated domain, POSIX-quoted. A-26 staff 2FA TOTP, recovery codes deferred in v0.2, later implemented in v0.4. A-27 marketing copy in config, prices from DB. A-28 PDFs from Markdown and SVG via headless Edge or Chrome.
A-29 mobile drawer is a sibling of header, never a child, because backdrop-filter makes header the containing block for position fixed children. A-30 period key yearly kept, quarterly and semiannual added. A-31 per-period approval_status NULL inherits plan status. A-32 subscriptions snapshot agreed price. A-33 renewal reuses verified payment path, one invoice per period. A-34 renewals extend from paid-through date. A-35 suspension behind ServiceSuspender and live flag off. A-36 reminders are rows sent by queued job. A-37 phone E.164, structural validation, no SMS.
A-38 separate staff session guard over same users table, EnsureStaff switches guard and restores it. A-39 staff login pattern /staff/{portal}, slug compared per request, wrong value is plain 404. A-40 unauthenticated /admin is 404 unless a dj_staff_portal hint cookie exists. A-41 session revocation by staff_session_epoch. A-42 EnsureStaff implements AuthenticatesRequests, admin group uses named limiter staff-area. A-43 recovery codes HMAC-SHA256, staff reset tokens in own table and broker. A-44 Super Admin init only via interactive CLI, promotion needs --promote plus typed email and current password. A-45 mandatory MFA for Super Admins. A-46 ticket SLA optional hours per priority, department override, blank means no SLA, idempotent support:sla-check. A-47 subscription add-ons are price snapshots billed from next renewal, no proration engine. A-48 cancellation at period end is a flag executed by billing:run, expiry optional, ended services open retention_review task. A-49 exports are CSV streamed in chunks with allow-lists. A-50 webhook event row and settlement commit in one transaction.

## Browser QA notes

Use an isolated SQLite DB served by php -S, not artisan serve, which drops env overrides and would read .env and MySQL. Sprint setup: C:/tmp/djet-qa/sprint-setup.sh builds qa-sprint.sqlite, serve-sprint.sh serves it on :8001, run scripts with MSYS_NO_PATHCONV=1 in Git Bash. Check widths 320, 390, 671, 768, 1024, 1280, 1440.

## What is not done

See docs/02_PROJECT_STATUS.md. In short: real Razorpay sandbox, PTSure docs, real SMTP and Telegram, SSH transport, install commands, supplier adapters, Proxmox, backups, XLSX exports, per-plan renewal overrides, customer self-service cancellation, automatic expiry, real-device and screen-reader testing.
