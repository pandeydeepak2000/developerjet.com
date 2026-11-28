# Developer guide - DeveloperJet

Last updated: 2026-11-28. Read with `docs/02_PROJECT_STATUS.md` (what is done and verified) and `docs/03_COMPLETE_WORKFLOW.md` (how the product works end to end). Requirements source: `DeveloperJet_SRS_v0.1.md` (later owner decisions in `CLAUDE.md` and `docs/02_PROJECT_STATUS.md` win over it).

## 1. Stack and local run

Laravel 12, PHP 8.2, Blade, Tailwind v4, Vite, MySQL (XAMPP locally), PHPUnit on SQLite in-memory, phpseclib 3 for SSH.

| Task | Command |
|---|---|
| Serve | `php artisan serve --host=127.0.0.1 --port=8000` |
| Assets | `npm run dev` (hot) or `npm run build`. `public/build` is committed because the server has no npm |
| Tests | `php artisan test` or `php vendor/bin/phpunit` (SQLite in-memory, never touches MySQL) |
| Local DB | `php artisan migrate`, then `php artisan db:seed` (catalogue, roles, docs) |
| Format | `vendor/bin/pint` (check: `vendor/bin/pint --test`) |
| Renewals locally | `php artisan schedule:work` and `php artisan queue:work` |
| Docs PDFs | `php scripts/build_diagrams.php && php scripts/build_docs_pdf.php` (one doc: add a filter such as `06_`) |
| Client dark mode | `php scripts/build_dark_overrides.php` after changing client CSS colours |

Local mail uses `MAIL_MAILER=log`: OTP and reset codes appear in `storage/logs/laravel.log`.

## 2. Hard rules

- WHMCS must never be integrated.
- No new live payment method, supplier API or hypervisor without explicit owner approval. Live SSH install is approved (2026-11-25) only through reviewed scripts.
- Never invent prices or resources. Unconfirmed values stay pending.
- Do not push to GitHub, deploy to cPanel or touch production without the owner saying so each time. Ask before destructive actions (dropping tables, `migrate:fresh`, deleting data).
- Never reveal `.env` contents. Never store plaintext root passwords (encrypted casts only). Never log a server password.
- Do not mark a feature complete without passing tests. After each milestone update `docs/02_PROJECT_STATUS.md` with what was actually verified.
- Preserve the SRS files `DeveloperJet_SRS_v0.1.md` and `.pdf`.
- Money is integer paise; format with `App\Support\Money::inr()`. Tax is never silently zero.
- Catalogue values go through the DB and seeders, never hard-coded in Blade. Keep marketing page section counts (asserted in `MarketingPagesTest`).
- The mobile drawer stays outside `<header>` (backdrop-filter makes the header the containing block for fixed children; `MobileNavigationTest`).

## 3. Folder map

- `routes/web.php`: the only route file. Public → guest auth → verified customer `/dashboard` → staff `/staff/{portal}` → admin `/admin`. Read it first for any feature.
- `routes/console.php`: `billing:run` daily 08:00, `servers:check-renewals` daily 08:30, `payments:reconcile` and `support:sla-check` every 15 minutes.
- `app/Http/Controllers/`: thin controllers. `Auth/` (customer), `Staff/` (staff auth), `Admin/` (22 controllers), root controllers for customer flows (`CheckoutController`, `ServicePanelController`, …).
- `app/Services/`: all business logic. `OrderService`, `CartService`, `WalletService`, `InvoiceService`, `TaxService`, `TicketService`, `ProjectService`, `AutomationLeadService`, `DeploymentService`, `RenewalService`, `Billing/`, `Payments/`, `Provisioning/` (workers, `SshTransport`, `RecipeRegistry`, `ServerActions`, `ServerStats`), `Infrastructure/` (provider adapters).
- `app/Support/`: helpers and enums: `Money`, `OrderStatus`, `SubscriptionStatus`, `BillingCycle`, `BillingSettings`, `LeadStatus`, `Setting`, `StaffSecurity`, `DeploymentProgress`, `Impersonation`, `AdminNav`, `DocRenderer`.
- `app/Console/Commands/`: `staff:create-super`, `staff:status`, `staff:portal`, `orders:purge`.
- `config/`: `permissions.php` (catalogue + default roles), `staff.php`, `provisioning.php` (recipes, steps, actions, key_help), `payments.php`, `navigation.php`, `product_pages.php`, `admin_resources.php`, `docs.php`.
- `database/migrations/`: 27 additive migrations. Never edit a shipped migration; add a new one.
- `database/seeders/`: `CatalogSeeder`, `AgentHostingCatalogSeeder`, `AiAccessSeeder`, `RbacSeeder`, `DocsSeeder` (all create-only or upgrade-only; admin edits survive). Customer help articles live in `database/seeders/docs/**/*.md`.
- `resources/provisioning/`: reviewed install and action scripts, referenced as `@path` in `config/provisioning.php`.
- `resources/views/`: `layouts/app` (website), `layouts/dashboard` (customer app), `layouts/admin` (staff), `dashboard/`, `admin/`, `pages/`, `components/` (`x-icon`, `x-status-badge`, `x-stat-card`, `x-empty-state`), `partials/theme-toggle`.
- `resources/css/`: `app.css` (imports Tailwind, `phase1.css`, `phase2.css`) → `refine.css` → `site.css`. Admin adds `admin.css` → `admin-theme.css` (white-blue theme + dark mode, loaded last). Customer dashboard adds `app-theme.css` (imports the generated `app-dark.generated.css`). Docs centre adds `docs.css`.
- `tests/Feature/`: 52 files. Base helpers: `tests/TestCase.php`, `tests/Concerns/BuildsFixtures.php`, `tests/Concerns/FakeTransport.php`.
- `scripts/`: `build_diagrams.php`, `build_docs_pdf.php`, `build_dark_overrides.php`.

## 4. Core patterns

**Route → Controller → Service → Model.** Controllers validate, check permission, call a service and return. Business logic never goes in a controller or Blade. Example: `POST /checkout` → `CheckoutController::store` → `OrderService::place` (transaction, checkout token, wallet reserve) → `Order` + `OrderItem` + `OrderEvent`.

**Permissions.** `<module>.<action>` in `config/permissions.php`. `User::hasPermission()` is the single check (Super Admin always true); `Gate::before` maps it for `@can`; routes use `permission:` middleware. The sidebar only hides links. A new permission is not added to roles that already exist in a database; a Super Admin grants it in Staff & roles. Customer records are always owner-scoped (policy or `where('user_id', …)`). Details: `docs/04_STAFF_ACCESS.md`.

**Money and prices.** Integer paise. Subscriptions snapshot the agreed price; renewals use the snapshot, never the current list price, and never add a setup fee. GST: Razorpay orders are taxed (default 18%, `GST_PERCENT`, overridable in Settings); an order paid fully from the wallet is GST-free.

**Idempotency and state machines.** Checkout token, payment event log and `OrderService::markPaid()` (row lock) are the only way to mark an order paid. States are enforced centrally (`OrderStatus`, `SubscriptionStatus`, `LeadStatus`); an invalid transition is rejected. Notifications dedupe on `notification_logs.dedupe_key`.

**Generic admin resources.** Simple CRUD goes through `Admin\ResourceController` at `/admin/r/{resource}` configured in `config/admin_resources.php`. Complex flows (orders, billing, leads, tickets, projects, users) have dedicated controllers.

**Admin navigation.** `App\Support\AdminNav` defines 9 sections with tabs and the permission each tab needs (`__export` checks `ExportService::allowedTypes`). Add a new admin page there, not in the layout.

**Secrets and uploads.** SMTP, Telegram token, server credentials, AI keys and TOTP seeds are encrypted and write-only; never log, email or render them in HTML except where the owner approved (the customer's own server password behind Show/Copy). Uploads use a private disk, random names and an extension allow-list.

**Provisioning.** `DeploymentService` assigns and deploys. `SshTransport` (phpseclib) checks the host key before sending the password (trust on first use, SHA256 pin on the server record), runs each step as `bash -c '…' 2>&1` with streamed output and a 30-minute timeout, and keeps the connection alive. `SshWorker` writes every line to `deployment_logs` (password redacted, 300 lines per step), resumes completed steps and stops at any step whose command is `null`. A dry run always uses `DryRunWorker`. `ServerActions` runs reboot/restart/reset-login/stop/start/wipe and the customer root-password change (base64 → `chpasswd`). `ServerStats` parses the read-only `common/stats.sh` output with a whitelist and caches it.

**Adding an install script.** Write an idempotent script in `resources/provisioning/<kind>/`, run it on a real test VPS (or at least a throwaway container), then reference it as `@<kind>/<name>` in `config/provisioning.php` and bump the recipe version. Never approve a script you have not run.

**Rate limits.** Named limiters in `AppServiceProvider`: `staff-area` (admin group), `vps-stats` (30/min), `vps-sensitive` (8 per 10 minutes, keyed by route + user). A plain `throttle:N,M` shares one counter per user across routes; do not use it on groups.

**Tests.** `actingAs($staffUser)` signs in on both guards (`TestCase::be`); use `actingAs($user, 'web')` for customer-only isolation. `STAFF_ENFORCE_PRIVILEGED_MFA=false` and an empty `GST_PERCENT` only in phpunit. Remote commands are tested with `FakeTransport`. A new feature needs a feature test: happy path, permission 403, invalid transition, idempotency.

**Scale.** Paginate admin lists (no `get()` on big tables), aggregate in SQL (`GROUP BY`, `sum()`, `count()`), cache things that run on every admin page (alerts summary 60 s), and add a composite index migration for new heavy queries.

## 5. Change guide

| To change | Files | Watch out |
|---|---|---|
| Plan price or size | `AgentHostingCatalogSeeder` / `CatalogSeeder`, or Admin → Products | Only owner-approved prices; seeders never overwrite admin edits |
| New admin page | `routes/web.php`, `config/permissions.php`, controller, view, `App\Support\AdminNav` | Server-side permission check; `AdminAccessTest`, `AdminCompletenessTest`, `StaffManagementTest` |
| New customer page | `routes/web.php` (dashboard group), controller, view, policy | Owner scope; `verified` middleware; read-only while impersonating |
| Payment rule | `app/Services/Payments/*`, `OrderService`, `TaxService`, `WebhookController` | Never trust the browser; signature + amount + idempotent event |
| Renewal rule | `BillingSettings`, `BillingCycle`, `Billing/SubscriptionService`, `RenewalService` | Snapshot price; `billing:run` must stay idempotent |
| Install step or server action | `resources/provisioning/*.sh`, `config/provisioning.php` | Run it on a test VPS first; idempotent; no secrets in output |
| Lead status | `LeadStatus`, `AutomationLeadService` | Reject illegal moves; timeline + audit |
| Admin styles | `resources/css/admin-theme.css` | Use the `--a-*` variables; check light and dark |
| Customer dashboard styles | `resources/css/app-theme.css`, then `php scripts/build_dark_overrides.php` | Use the `--c-*` variables; check dark mode |
| Customer help article | `database/seeders/docs/**`, plus the old body sha1 in `DocsSeeder::SHIPPED_BODY_HASHES` | Production runs `php artisan db:seed --class=DocsSeeder --force` |
| Internal docs | `docs/*.md`, then rebuild the PDFs | Keep the doc list in `README.md` current |

## 6. Decisions index

A-01 money as integer paise. A-02 tax never silently zero. A-03 paid only via `markPaid`. A-04 idempotency at checkout token, payment events and `markPaid` row lock. A-05 only approved prices are orderable. A-06 wallet append-only. A-07 gateways behind an interface (PTSure removed 2026-11-24). A-08 invoices only for paid orders, credit notes prefix CN. A-09 RBAC `module.action`. A-10 config-driven generic admin resources. A-11 secrets encrypted and write-only. A-12 provisioning worker interface. A-13 one in-flight deployment per service, explicit retry. A-15 quotes never charge. A-16 notifications deduped and queued. A-17 private uploads with allow-list. A-19 docs Markdown with raw HTML stripped. A-20 PDFs via dompdf (invoices) and headless Edge (docs).

D-01 to D-06: hybrid KVM (manual suppliers now, provider APIs and Proxmox later), initial suppliers DesiVPS, EricHost, Contabo, Server Broker, no WHMCS.

A-21 providers hold no credentials. A-22 supplier cost separate from selling price, optional cost limit and minimum margin. A-23 recipes versioned, fixed commands, `null` = not approved. A-24 `RemoteTransport` with host-key pinning and resume. A-25 the only command placeholder is a validated, POSIX-quoted domain. A-29 mobile drawer outside the header. A-30 to A-37 billing engine (4 periods, price snapshots, renewal extends from paid-through date, suspension behind `ServiceSuspender`, phone E.164). A-38 to A-45 staff security (separate guard, `/staff/{portal}` slug, 404 for outsiders, session epoch, named limiter, HMAC recovery codes, interactive Super Admin init, mandatory Super Admin MFA). A-46 to A-50 SLA, add-on snapshots, period-end cancellation, CSV exports, webhook + settlement in one transaction.

Added after v0.5:
- A-51 everything is self-hosted: every hosting product is handed over with root access (`access_model` "unmanaged" internally).
- A-52 live SSH install over phpseclib with trust-on-first-use host keys; scripts are reviewed `@path` files.
- A-53 GST by payment route: Razorpay taxed, full-wallet orders GST-free.
- A-54 AI All-Access is sold as API credit; the key is delivered by staff and stored encrypted.
- A-55 the customer panel reads usage only through a read-only script and changes the root password only through the panel (so the stored credential stays correct).
- A-56 admin navigation is data (`AdminNav`): 9 sections, related pages as tabs, same permissions.
- A-57 one theme for admin and client: white-blue light and navy dark, chosen per browser (`dj-admin-theme`, `dj-theme`), following the system setting when unset.

## 7. Browser QA

Use an isolated SQLite database served by `php -S` (not `artisan serve`, which drops env overrides and would read `.env`/MySQL). QA scripts live in `C:/tmp/djet-qa/` (for example `serve-guide.sh` on :8003 with live provisioning and MFA off). Run Git Bash scripts with `MSYS_NO_PATHCONV=1`. Check widths 320, 390, 768, 1024, 1366 and 1440, in light and dark. The test VPS is reached only with the SSH key in `C:/tmp/djet-qa/ssh/`; never change its root password and never touch other projects on it.
