# Developer Handover — DeveloperJet

Last verified: 2026-11-21. Local only, nothing production verified.
Read together: `docs/01_DEVELOPER_GUIDE.md` (rules + architecture), `docs/02_PROJECT_STATUS.md` (what is done / pending), `docs/03_COMPLETE_WORKFLOW.md` (client-to-admin workflow + automation). SRS is the requirements source of truth: `DeveloperJet_SRS_v0.1.md`.

## 1. What this project is

Laravel 12 / PHP 8.2 / Blade / Tailwind v4 / Vite / MySQL (XAMPP local) / PHPUnit (SQLite in-memory).
DeveloperJet (AmbitionHost Pvt. Ltd.) sells AI hosting, developer VPS hosting and digital services. Customer registers → picks plan → pays (verified only) → staff manually buys/assigns a VPS → deployment (live install gated) → service active → renewals / tickets / support from one dashboard. Delivery is NOT instant — manual VPS assignment is by design.

## 2. Run it locally

- Serve: `php artisan serve --host=127.0.0.1 --port=8000`
- Assets: `npm run dev` (hot) or `npm run build`
- Tests: `php artisan test` (SQLite in-memory, never touches MySQL)
- Migrate + seed catalogue: `php artisan migrate`, then `php artisan db:seed --class=CatalogSeeder`
- Format: `vendor/bin/pint` (check: `vendor/bin/pint --test`)
- Scheduler + queue (renewals/reminders): `php artisan schedule:work` and `php artisan queue:work`
- Local DB: MySQL `developerjet_local` on 127.0.0.1:3306. Mail: `MAIL_MAILER=log` — OTP/reset codes go to `storage/logs/laravel.log`. No real SMTP yet.
- Browser QA (isolated, never artisan serve): `C:/tmp/djet-qa/sprint-setup.sh` builds `qa-sprint.sqlite`, `serve-sprint.sh` serves it on :8001 with `STAFF_ENFORCE_PRIVILEGED_MFA=false`. Check widths 320, 390, 671, 768, 1024, 1440.

## 3. Folder map — where to look

- `routes/web.php` — ONLY route file. Public → guest auth → verified customer `/dashboard` → staff `/staff/{portal}` → admin `/admin`. Read it first for any feature.
- `routes/console.php` — scheduled commands: `billing:run` daily 08:00, `payments:reconcile` + `support:sla-check` every 15 min.
- `app/Http/Controllers/` — thin controllers. `Auth/` (customer), `Staff/` (staff auth), `Admin/` (21 admin modules), root controllers for customer flows (Cart, Checkout, Catalog, Dashboard, etc.).
- `app/Services/` — ALL business logic lives here, never in controllers or Blade. `OrderService`, `CartService`, `WalletService`, `InvoiceService`, `TicketService`, `ProjectService`, `AutomationLeadService`, `Billing/SubscriptionService` + `RenewalService`, `Payments/` (gateway interface), `Provisioning/` (worker + recipes), `Infrastructure/` (provider adapters).
- `app/Support/` — small helpers / enums: `Money` (paise), `OrderStatus`, `SubscriptionStatus`, `BillingCycle`, `BillingSettings`, `LeadStatus`, `Setting`, `StaffSecurity`, `Permissions`, `SafeText`, `DocRenderer`.
- `app/Models/` — Eloquent models, one table each. `User` is shared by customer + staff (separate guards `web` / `staff`).
- `config/` — `permissions.php` (catalogue + default roles), `staff.php` (portal slug default), `navigation.php`, `docs.php`.
- `database/migrations/` — 24 migrations, additive only. Never edit a shipped migration; add a new one. Latest: 2026_11_27_100000 scale composite indexes (orders/tickets/invoices/users).
- `database/seeders/` — `CatalogSeeder` is idempotent (never overwrites admin edits), plus `RbacSeeder`, `DocsSeeder`, etc.
- `resources/views/` — `layouts/app.blade.php` (public), `layouts/dashboard.blade.php` (customer), `layouts/admin.blade.php` (admin shell), `admin/` (7 direct views + generic resource views), `dashboard/`, `pages/`, `components/` (`x-icon`, `x-status-badge`, `x-stat-card`).
- `resources/css/` — load order matters: `app.css` → `refine.css` → `site.css` → `phase2.css` (admin shell + shared) → `admin.css` (redesign override, loaded last). `vite.config.js` lists them.
- `tests/Feature/` — 38 feature test files. `tests/TestCase.php` + `tests/Concerns/BuildsFixtures.php` are the base helpers.
- `docs/` — only 10 .md files now (see §6). `docs/diagrams/*.svg` feed the PDF. `scripts/build_docs_pdf.php` builds `docs/generated/DeveloperJet_Complete_Workflow.pdf` from `03_COMPLETE_WORKFLOW.md` only.
- `scripts/` — `build_diagrams.php`, `build_docs_pdf.php`.

## 4. Core patterns — ye tode to project tootega

### 4.1 Route → Controller → Service → Model
- Controller thin rakho: validate input, permission check, Service ko call karo, redirect/view return karo. Business logic kabhi controller ya Blade me mat likho.
- Service me logic: `app/Services/OrderService.php` (order place/pay/cancel), `WalletService`, `InvoiceService`, `TicketService`, `ProjectService`, `AutomationLeadService`, `Billing/SubscriptionService` + `RenewalService`, `Payments/PaymentService`.
- Model sirf relations, casts, aur chhote helpers. `User::hasPermission()` hi permission ka single check hai.
- Example flow: `POST /checkout` → `CheckoutController::store` → `OrderService` (DB transaction + idempotency token + wallet reserve) → `Order` + `OrderItem` + `OrderEvent` (timeline).

### 4.2 Permissions (RBAC) — sabse important pattern
- Catalogue: `config/permissions.php` — `<module>.<action>`, jaise `orders.view`, `plans.approve`, `leads.assign`, `tickets.reply`.
- Check: `$user->hasPermission('orders.view')` (Super Admin ko hamesha true). Blade me `@can('orders.view')`, route me `middleware('permission:dashboard.view')`, `Gate::before` isi ko map karta hai.
- Sidebar sirf link chhupata hai, access nahi deta — har action server-side check hota hai. Naya action banao to pehle `config/permissions.php` me add karo, phir controller/route me check karo, phir role editor me dikhega.
- Sensitive actions (staff suspend, reset access, priced add-on, slug change) me `staff.reauth` middleware — last 10 min me password (+ TOTP) confirm hona chahiye.
- Nayi permission purane roles ko auto nahi milti — Super Admin ko Staff & roles me grant karna padta hai (`tickets.reply`, `*.export` ka yahi case tha).
- Customer data hamesha owner-scoped: `where('user_id', $user->id)` ya Policy. Kabhi bina scope ke customer record mat dikhao.

### 4.3 Money — integer paise, kabhi float nahi
- Saare amounts integer paise me store hote hain. Display ke liye sirf `App\Support\Money::inr($minor)` use karo.
- Tax kabhi silently zero mat karo — tax configure nahi hai to page par 'tax not configured' dikhao.
- Subscription price snapshot hota hai: renewal hamesha agreed snapshot se banta hai, current list price se nahi. Setup fee sirf pehle order par, renewal par kabhi nahi.

### 4.4 Idempotency + state machines
- Checkout token se double-click par ek hi order banta hai. Payment webhook/event log idempotent hai — duplicate callback dobara settle nahi karta.
- `OrderService::markPaid()` hi ek rasta hai order ko paid karne ka (row lock ke saath). Browser ka 'success' kabhi trust mat karo — sirf verified signature + amount match.
- States centrally enforced: `App\Support\OrderStatus` (draft → pending_payment → paid → awaiting_provisioning → provisioning → active; side: failed/cancelled/refund_pending/refunded), `SubscriptionStatus`, `LeadStatus` (10-status). Galat transition reject hoti hai, silently apply nahi hoti.
- Notifications deduped: `notification_logs.dedupe_key` unique, ek hi event ek hi baar jata hai. Jobs ke 3 tries + backoff hote hain.

### 4.5 Generic admin resources
- Simple CRUD ke liye naya controller mat banao: `Admin\ResourceController` + `/admin/r/{resource}` use karo. Resource config se fields, permissions aur list columns aate hain.
- Complex flows (orders, billing, leads, tickets, projects) ke liye dedicated controller hai — wahi pattern follow karo.

### 4.6 Catalogue, secrets, uploads
- Nayi price/resource/plan value DB + seeder se jati hai, Blade me hard-code kabhi nahi. `CatalogSeeder` idempotent hai — admin ke edits ko overwrite nahi karta.
- Secrets (SMTP, Telegram token, SSH credential, AI keys, TOTP seed) encrypted + write-only hain — kabhi log, email ya response me mat dikhao. Root password plaintext me kabhi store mat karo.
- Uploads private disk par, random naam, extension allow-list, download per-record authorised.

### 4.7 CSS / Blade layering
- Load order: `app.css` → `refine.css` → `site.css` → `phase2.css` (admin shell) → `admin.css` (redesign override, last). Naya admin style sirf `admin.css` me likho.
- Mobile drawer `<header>` ke bahar sibling hai — andar mat lao (backdrop-filter fixed child ko tod deta hai, `MobileNavigationTest` fail hoga).
- Components reuse karo: `x-icon`, `x-status-badge`, `x-stat-card`, `x-empty-state`.

### 4.8 Tests — bina test ke feature complete nahi
- Base: `tests/TestCase.php::be()` — staff user ko dono guards (`web` + `staff`) par sign in karta hai; customer-only isolation ke liye `actingAs($user, 'web')` pass karo. Fixtures: `tests/Concerns/BuildsFixtures.php`.
- Tests SQLite `:memory:` par chalte hain, MySQL ko kabhi touch nahi karte. `STAFF_ENFORCE_PRIVILEGED_MFA=false` sirf phpunit/QA me.
- Naya feature = naya Feature test (happy path + permission 403 + invalid transition + idempotency). Marketing page ka section count change kiya to `MarketingPagesTest` update karna padega.

## 5. Change guide — kuch badalna ho to kaunsi file chhedni hai

| Badalna hai | Chhedni files | Dhyan rakho |
|---|---|---|
| Plan price / resource | `database/seeders/CatalogSeeder.php`, `app/Models/Plan.php`, `PlanPrice.php`, `Admin\PlanController` | Blade me hard-code nahi; `price_status` approved + `plans.approve` ke bina sell nahi hota |
| Naya admin module / page | `routes/web.php` (admin group), `config/permissions.php`, `app/Http/Controllers/Admin/XController.php`, `resources/views/admin/...`, `layouts/admin.blade.php` sidebar | Permission check server-side; sidebar sirf link chhupata hai; test `AdminAccessTest` + `AdminCompletenessTest` |
| Naya customer page | `routes/web.php` (dashboard group), controller, view, Policy / scope | `user_id` scope ke bina customer record kabhi mat dikhao; `verified` middleware |
| Payment gateway | `app/Services/Payments/PaymentGateway.php` + gateway impl, `GatewayManager`, `WebhookController`, config | Browser result kabhi trust nahi; signature + amount match + idempotent event log |
| Renewal / billing rule | `app/Support/BillingSettings.php`, `BillingCycle.php`, `app/Services/Billing/SubscriptionService.php` + `RenewalService.php`, `routes/console.php` | Renewal snapshot price se banta hai; setup fee renewal par nahi; `billing:run` idempotent rakho |
| Notification / event | `app/Services/Notifier.php`, `app/Jobs/Send*`, `notification_logs.dedupe_key`, settings template | Dedupe key unique; secret kabhi log me nahi |
| Lead status / step | `app/Support/LeadStatus.php`, `app/Services/AutomationLeadService.php` | Illegal transition reject hoti hai; timeline + audit log likhna mat bhoolo |
| Deployment recipe | `app/Services/Provisioning/RecipeRegistry.php` + recipe version bump | Command `null` = not approved; live SSH gated hai; pehle `DryRunWorker` se check karo |
| Role / permission | `config/permissions.php`, `database/seeders/RbacSeeder.php` | Nayi permission purane roles ko auto nahi milti; Super Admin ko Staff & roles me grant karna padta hai |
| Admin CSS | Sirf `resources/css/admin.css` | `phase2.css` mat chhedo; load order me `admin.css` last hai |
| Docs | `docs/03_COMPLETE_WORKFLOW.md` edit karo, phir `php scripts/build_docs_pdf.php` | 01/02/03 ke alawa koi naya workflow doc mat banao |

## 6. Pending list — 2026-11-21 ko kya baaki hai

### A. Owner ko karna hai / decision pending
- Super Admin `ambitionmanagements@gmail.com` abhi bana nahi — MySQL 127.0.0.1:3306 refuse kar raha tha 2026-11-21 ko. Owner: MySQL/XAMPP start karo, `php artisan staff:status ambitionmanagements@gmail.com`, phir `staff:create-super` interactively.
- Saare sellable plans ki prices/resources approve nahi — Hermes/Workspace illustrative, Claude/Docker/AI Bundle/OpenClaw/n8n pending/draft.
- Tax rate, invoice prefix/start number, company details/GSTIN, cost limit/minimum margin, support scope wording — SRS section 20.
- Production staff login slug choose karna hai (default `developerjet`).

### B. Integration verify nahi — fake/test par hi hai
- Razorpay: fakes only, real sandbox payment kabhi nahi chala. PTSure: skeleton disabled, official API docs + sandbox credentials chahiye.
- Real SMTP + Telegram: verify nahi. Notifications queued/deduped hain, tested fakes se.
- SSH transport inert, live install commands approve nahi, supplier APIs verify nahi, Proxmox hard-disabled.
- Backups, real monitoring, real-device (iOS/Android) + screen-reader testing baaki.

### C. Feature gaps
- XLSX exports nahi (CSV only, 9 types). Per-plan renewal-rule overrides nahi (global settings hi hain).
- Customer self-service cancellation: request flow hai, staff approval par depend karta hai. Automatic expiry OFF by default.
- Customer dashboard: no separate notification centre, no separate My servers page, no separate payment-history page. Global admin search ab User Manager (/admin/users) par hai — customers + staff, name/email/phone search.
- Product comparison rebuild brief section 4 se aage start nahi hua (brief truncated tha).
- AI All-Access: presentation/request-access only, backend nahi. Hermes/Workspace/n8n/OpenClaw/Claude/Docker controls sab Unavailable (verified provider integration nahi).

### D. Admin redesign QA — FIXED (2026-11-21, dobara verify bhi ho gaya)
- Chaaron responsive issues fix ho gaye (`resources/css/admin.css` + `refine.css`): 320px par koi horizontal overflow nahi (grid `minmax(0,1fr)` + `min-width:0`, cards 244px = grid 244px), Recent transactions me poora email dikhta hai (768px se neeche list-row wrap, `overflow-wrap:anywhere`), header har width par single-row 61-71px (flex-wrap nowrap, 671px se neeche `.admin-user` chhupa), quick actions har width par consistent 44px pill buttons.
- Verify: headless Edge, authenticated dashboard HTML (built CSS, 320/390/671/768/1024/1440px): 0 overflow, 0 clipped emails, single-row header, consistent pills. `php artisan test` 409 / 3425, `npm run build`, `pint --test` PASS. Real devices (iOS/Android) aur screen readers abhi bhi test baaki hain.

### E. Docs cleanup — is task me done
- Delete kiye (backup `C:/tmp/djet-docs-backup-2026-11-21-2/docs`, 21 files): `DEVELOPERJET_WORKFLOW_GUIDE.md`, `BUSINESS_AUTOMATION_WORKFLOW.md`, `BILLING_RENEWAL_WORKFLOW.md`, `ADMIN_RENEWAL_SETTINGS.md`, `ADMIN_PROVISIONING_RUNBOOK.md` + 2 purane v0.2 PDF. Inka content `03_COMPLETE_WORKFLOW.md` + operational guides me hai.
- Bache hue docs: 01, 02, 03, 04 (ye file), `ADMIN_OPERATIONS_GUIDE`, `STAFF_PORTAL_GUIDE`, `STAFF_ROLES_AND_PERMISSIONS`, `SALES_TEAM_OPERATIONS`, `STAFF_LOGIN_TROUBLESHOOTING`, `PRODUCTION_READINESS_CHECKLIST` + `diagrams/*.svg`.
- PDF build: `scripts/build_docs_pdf.php` ab sirf 03 se `docs/generated/DeveloperJet_Complete_Workflow.pdf` banata hai.

### F. User Manager + scale — is task me done (2026-11-21)
- User Manager: `app/Http/Controllers/Admin/UserManagerController.php`, views `admin/users/index|show.blade.php`, routes `admin.users.*` + `admin.impersonation.exit`. Email change aur impersonate dono `staff.reauth` ke peeche hain; impersonation hamesha read-only rakho — naya customer POST route `impersonation.readonly` group ke bahar mat rakho bina soche.
- Permission: `customers.impersonate` sirf naye Administrator seed ko default mili hai; existing production roles ko Super Admin Staff & roles se grant karega (RbacSeeder firstOrCreate hai, purane roles overwrite nahi hote).
- Scale rules aage ke liye: koi admin list `get()` se mat load karo — paginate karo; dashboard/report aggregates SQL `GROUP BY` / `sum()` / `count()` se nikalo, PHP filter nahi; har admin page par chalne wali cheez (bell alerts) cache karo; nayi badi table query par composite index migration add karo.
