# DEVELOPERJET
## Software Requirements Specification (SRS)
### Premium AI Hosting, Automation & Digital Services Commerce Platform

**Brand:** DeveloperJet — Powered by AmbitionHost  
**Primary domain:** https://developerjet.com  
**Marketing/referral page:** https://ambitionhost.in/agent-hosting  
**Parent company:** AmbitionHost Pvt. Ltd.  
**Document ID:** DJ-SRS-001  
**Version:** 0.1 — Discussion baseline / not implementation-approved  
**Prepared:** 19 November 2026  
**Audience:** Product owner, UI/UX team, architects, AI coding agents, developers, QA and operations

> **Status legend:** **CONFIRMED** = stated by owner or directly visible in supplied screenshot; **PROPOSED** = recommended design pending approval; **PENDING** = requires decision, inspection, supplier confirmation or legal/accounting review; **DEPENDENCY** = condition that must be satisfied before delivery. This specification is a requirements baseline, not a claim that features already exist.

---

## 1. Executive summary

DeveloperJet is envisioned as a premium independent SaaS/commerce platform for AI-agent hosting and managed digital services. Customers should discover products, configure plans, create accounts with **email OTP verification**, maintain a cart, make verified payments, receive properly numbered billing documents and manage services from an elegant client dashboard. Administrators should control catalog pricing, plan configurations, order fulfilment, VPS deployment, payment gateways, staff access, support and notifications without modifying application source code.

**Initial AI hosting catalogue:** 3 Hermes CLI plans; 3 Hermes Workspace plans; 3 n8n plans; 1 AI Complete Bundle (Hermes CLI + Workspace + n8n); plus 3 standalone OpenClaw plans = **13 AI hosting sales plans**. Digital services (WordPress, Laravel, general web development, SEO, maintenance and AI application hosting) are separate categories with configurable packages and custom quotations. The 10 VPS configurations mentioned on the AmbitionHost marketing page are an **infrastructure/pricing reference**, not a verified resource list in this document.

**Fulfilment strategy:** launch with admin-assigned VPS + automatic secure deployment when admin clicks Deploy. Later allow a guarded Auto Provisioning toggle with capacity management and isolated VMs or provider-created VPS. The client experience must stay consistent across both modes.

**Hard boundary:** DeveloperJet must remain operationally and financially independent of AmbitionHost's WHMCS installation. No WHMCS dependency, shared credential assumption or silent coupling.

**Owner's product aspiration:** a credible, fast, polished website that feels production-ready, with outstanding UX, clear inclusions/exclusions, secure operations and consistent service delivery.

### Owner-facing summary (Hindi)

DeveloperJet को केवल hosting landing page नहीं, बल्कि पूरा **product catalogue + cart + verified checkout + billing + service delivery + support** platform बनाना है। ग्राहक बिना verified account के checkout पूरा नहीं कर सकेगा। Admin plans, prices, payment methods, invoice numbering, staff permissions और VPS deployments manage करेगा। अभी manual VPS assignment पर launch और बाद में capacity-controlled automatic deployment रखने की योजना है। Custom web-development/SEO orders में workflow automation होगा, लेकिन quality review और client approval जैसे काम human-controlled रहेंगे।

## 2. Source of truth, evidence and assumptions

| Evidence ID | Source | What can be stated | What is NOT verified |
|---|---|---|---|
| EV-01 | Supplied VS Code screenshot dated 19 Nov 2026 | Local folder shown as `C:\\laravel\\developerjet.com`; Explorer shows Laravel-style `artisan`, `composer.json`, `routes`, `database`, `resources`, `vendor`, and Vite files | Exact Laravel/PHP versions, dependency state, functioning backend, migrations, environment health |
| EV-02 | Visible README excerpt in the screenshot | Routes described include `/`, `/hermes`, `/hermes-cli`, `/hermes-workspace`, `/n8n`, `/login`, `/register`, `/forgot-password`, `/about`, `/contact` | Full route definitions, working payment/authentication, remaining README text |
| EV-03 | Visible README wording | `/login` and `/register` are called *visual-only* and direct to WhatsApp; `/forgot-password` has no reset email | Whether there are any unshown alternative authenticated routes |
| EV-04 | Owner's requirements discussed through 19 Nov 2026 | 13 AI hosting sales plans; OTP; cart; gateways; wallet; numbered invoices; admin VPS assignment; future auto provisioning; staff RBAC; notifications; docs; digital services | Actual prices, VPS specs, provider integrations and implementations |
| EV-05 | External vendor documentation referenced in §23 | Security and integration considerations for payments/OpenClaw/n8n | Vendor commercial approvals, production credentials and live capability |

**Repository inspection is still required.** This SRS is intentionally written without reading source code beyond the screenshot. A future agent must inspect source before declaring anything implemented, replacing frontend components or selecting final deployment infrastructure. The `.env` file is visible in Explorer but its secrets must never be read into reports.

## 3. Business goals and success criteria

**G-01 (CONFIRMED):** Enable genuine purchases via product pages, cart, checkout and payment, not WhatsApp-only pseudo-checkouts.

**G-02 (CONFIRMED):** Let the admin change every saleable plan, price, description, resource limit and active state via backend.

**G-03 (CONFIRMED):** Make service fulfilment reliable, traceable and primarily automated after approved capacity/assignment.

**G-04 (CONFIRMED):** Offer premium design, mobile responsiveness, support and self-service documentation.

**G-05 (PROPOSED):** Measure conversion funnel, payment success/failure, deployment success and renewal retention from audited business events.

**G-06 (PROPOSED):** Reduce support workload with searchable documentation, self-service status, standard fulfilment workflows and safe staff delegation.

Launch acceptance requires the **P0** items in §19 to pass staging tests; no promised deployment-success, uptime or conversion percentages are asserted without production measurement.

## 4. Actors, roles and authority

| Actor | Primary scope |
|---|---|
| Anonymous visitor | Browse products/docs, compare packages, create guest cart; no completed checkout or private data |
| Verified customer | Manage profile, orders, cart, payment methods, invoices, wallet, services, projects and tickets limited to own account |
| Super admin/owner | All platform settings, payment credentials, role policies, high-risk actions, provisioning mode and audit access |
| Billing manager | Invoices, approved payments/refunds/reconciliation within assigned monetary limits; no raw gateway secrets by default |
| Sales/support agent | Assigned leads/tickets/orders, customer communications, scoped CRM visibility; no server root access by default |
| Deployment operator | View assigned hosting orders and perform approved provisioning; no unrestricted accounting authority |
| Developer/SEO specialist | Assigned projects, tasks, milestones and deliverables; least-privilege customer access |
| Background worker | Idempotent gateway callbacks, email, reminders, provisioning and health-check jobs under separate service credentials |

**Authorization principle (CONFIRMED):** Owner assigns access not just to sidebar menu visibility but to create/read/update/approve/export/refund/deploy actions. Server-side authorization must enforce every permission.

## 5. Product catalogue and plan taxonomy

### 5.1 AI hosting: 13 sales plans (CONFIRMED catalogue count)

| Product family | Example tier names | Number | Key workflow |
|---|---|---:|---|
| Hermes CLI | Starter / Growth / Pro *(names proposed)* | 3 | Terminal access; **no subdomain selection** |
| Hermes Workspace | Starter / Growth / Pro *(names proposed)* | 3 | Secure Workspace URL and optional custom domain |
| n8n Managed Setup | Starter / Growth / Pro *(names proposed)* | 3 | n8n URL, instance credentials and automation setup |
| AI Complete Bundle | Bundle *(name confirmed)* | 1 | Hermes CLI + Workspace + n8n on planned managed configuration |
| OpenClaw Managed Hosting | Starter / Pro / Business *(names proposed)* | 3 | Independent assistant hosting and onboarding |
| **Total** | | **13** | |

**Plan/resource separation:** a *saleable plan* has price, contract, features and eligibility; a *resource profile* has vCPU, RAM, disk and deployment compatibility; a *server inventory object* is actual assigned capacity. Multiple plans may use the same resource profile. Admin must not implicitly treat 10 marketing VPS configurations as exactly 10 saleable plans. Exact CPU/RAM/disk values and price points remain PENDING until marketing page details and repository are read.

### 5.2 Other services (CONFIRMED intended offerings; packaging PROPOSED)

| Category | Order type | Suggested tiers/handling |
|---|---|---|
| WordPress website design | Fixed scope or quote | Starter / Business / Store; pages, themes, revisions, content and hosting explicit |
| Laravel development | Project / quote | Basic / Business / Custom; requirements, integrations and milestones |
| General web development | Fixed scope or quote | Landing Page / Business Website / Custom Platform |
| AI-generated application hosting | Managed hosting assessment | Basic / Growth / Managed; runtime requirements and supported stacks verified first |
| SEO services | Monthly project | Essential / Growth / Advanced; deliverables and reporting, never guaranteed rankings |
| Website care/maintenance | Recurring | Basic Care / Business Care / Priority Care; scope and response targets |
| Custom AI integration/automation | Request for proposal | Paid discovery or quote; includes approval boundaries |

**Future catalogue expansion (PROPOSED):** chatbot implementation, API integrations, migrations, technical audits and automation consulting. No plan is considered priced or launched until admin configuration, terms, support coverage and delivery process exist.

### 5.3 Customer-facing inclusions and exclusions (P0)

Each published product must display: intended audience, use cases, included compute resources, access method, management boundary, available regions, supported features, upgrade path, setup time estimate only after capacity assessment, subscription/renewal model, whether AI API usage is included, third-party licensing and a prominent **What's Not Included** section. Price, tax estimate and add-ons must come from backend configuration. No unsupported claim such as guaranteed Google ranking or unlimited resources.

## 6. User journeys and behavioural requirements

### J-01: Guest cart to verified purchase

1. Visitor opens product page and selects a plan and supported add-ons.
2. Guest cart persists securely for the browser session; pricing recalculated server-side.
3. Clicking checkout without verified session redirects to login/register, preserving the cart and intended destination.
4. Register requests email address, password and agreed terms; one-time code is sent with rate limiting.
5. Only a valid non-expired code activates the account; login requires verified email (and password/approved login method).
6. Customer provides billing details, config and confirms checkout.
7. Server creates an idempotent payable order with a configuration/price snapshot.
8. Payment is initiated via enabled gateway or eligible wallet funds.
9. Payment is server-verified; success issues appropriate billing records, sends notifications and queues fulfilment.
10. Customer sees current order/service status and next action in dashboard.

### J-02: Manual VPS assignment and deployment

1. Hosting order reaches **Paid / Awaiting Assignment**.
2. Admin chooses VPS from inventory or registers customer-dedicated VPS with approved access.
3. Server specifications are validated against purchased resource profile and target software requirements.
4. Privileged operator clicks **Deploy**; job is queued with one unique execution ID.
5. Worker performs OS/reachability/security checks, installs required components, configures service endpoint and HTTPS, verifies health, records results.
6. On success, service becomes Active; customer receives secure first-login material and docs. Admin Telegram/email receive success event.
7. On failure, order stays visible with actionable error, safe retry, retained logs and no premature credential delivery.

### J-03: Future automated allocation

When mode toggle is ON *and provisioner is healthy*, system checks available provider quota or dedicated-host free resources, reserves capacity atomically, provisions isolated VM/VPS, deploys, tests and delivers automatically. If prerequisites fail it must **fail closed to manual review**, never oversell or silently create unisolated tenants.

### J-04: Digital service purchase

Customer selects fixed package or submits scope form; fixed scope proceeds to checkout while custom scope generates request/quote. Verified payment/approval creates project, assigns staff, tracks milestones/deliverables/revisions and requires client approval for launch. Fully automatic coding, SEO ranking or client-facing publication is **not** promised.

## 7. Functional requirements — public site and identity

| ID | Requirement | Priority | State |
|---|---|---|---|
| FR-001 | Premium responsive marketing site with clear product families, comparisons, FAQs, pricing, docs and trust/legal pages | P0 | CONFIRMED |
| FR-002 | Admin-driven visible products, plans, feature lists, pricing, stock/eligibility and order links | P0 | CONFIRMED |
| FR-003 | Server-side search/filter of products and plan comparisons; responsive mobile navigation | P1 | PROPOSED |
| FR-004 | Email OTP at registration; unverified accounts blocked from service use/checkout | P0 | CONFIRMED |
| FR-005 | OTP expiry, limited retry/resend, hashed code storage, anti-abuse protections and audit | P0 | PROPOSED security baseline |
| FR-006 | Secure login/logout, password reset emails, session revocation and optional MFA | P0 | PROPOSED security baseline |
| FR-007 | Guest cart, merge-after-login and preservation of selected configuration after OTP verification | P0 | CONFIRMED |
| FR-008 | Checkout and final order placement require authenticated, email-verified account | P0 | CONFIRMED |
| FR-009 | Email delivery retry/error tracking without exposing OTP or plaintext passwords | P0 | PROPOSED security baseline |

**Open implementation point:** Choose email provider and rate/expiry thresholds after deliverability test. Suggested OTP defaults (e.g., 10-minute expiry, limited tries) are engineering proposals, not owner-approved business terms.

## 8. Functional requirements — configuration, cart, orders and payments

| ID | Requirement | Priority | State |
|---|---|---|---|
| FR-010 | Configurable cart item model: product, tier, billing cycle, quantity limits, region, resource profile and add-ons | P0 | CONFIRMED + PROPOSED fields |
| FR-011 | Hermes CLI configuration excludes subdomain/custom-domain controls | P0 | CONFIRMED |
| FR-012 | Hermes CLI supports optional n8n add-on subject to compatibility and licence; customer's AI API key can be supplied or skipped | P0 | CONFIRMED |
| FR-013 | Workspace and n8n support available default subdomain or validated custom domain | P0 | CONFIRMED |
| FR-014 | API keys are BYOK by default unless plan explicitly includes usage; use encrypted vault/just-in-time delivery and never display saved keys in cleartext | P0 | CONFIRMED + PROPOSED security |
| FR-015 | Bundle includes CLI + Workspace + n8n; domain mapping applies to browser services only | P0 | CONFIRMED |
| FR-016 | OpenClaw is a standalone category with 3 plans; initial launch does not require cross-sell add-on | P0 | CONFIRMED recommendation accepted by owner |
| FR-017 | Every order has unique immutable order ID; store purchase-time plan price/terms/configuration snapshot | P0 | CONFIRMED + PROPOSED control |
| FR-018 | Admin enables/configures Razorpay and PTSure independently with test/live modes, status and credential validation | P0 | CONFIRMED |
| FR-019 | Verify gateway callbacks/webhooks server-side; deduplicate events; do not fulfil on browser redirect alone | P0 | PROPOSED security baseline |
| FR-020 | Payment states include initiated, pending, authorized, captured/succeeded, failed, refunded, disputed and reconciliation-needed as relevant to provider | P0 | PROPOSED |
| FR-021 | Payment failure triggers accurate customer/admin status and Telegram/email notifications without creating paid order | P0 | CONFIRMED |
| FR-022 | Support partial/full refunds with permission checks, gateway verification and ledger consistency | P1 | PROPOSED |
| FR-023 | Add subscriptions, renewals, invoice reminders, grace/suspension policy and cancellation workflow | P1 | CONFIRMED intention; policy PENDING |

**Gateway integration boundary:** PTSure API permissions, supported currencies, merchant account onboarding and refund/webhook schemas must be verified from the merchant's actual documentation before implementation. Do not guess its payloads.

## 9. Functional requirements — wallet, invoices, taxation

| ID | Requirement | Priority | State |
|---|---|---|---|
| FR-024 | Internal customer wallet with balance, immutable double-entry or equivalent balanced ledger, credit/debit/refund trail | P0 | CONFIRMED |
| FR-025 | Wallet funding page displays editable Contact Our Sales Team card; manual top-up requires staff approval and proof review | P0 | CONFIRMED |
| FR-026 | Wallet debits must be transactional, idempotent and blocked for insufficient funds; concurrent checkouts cannot double-spend | P0 | PROPOSED security baseline |
| FR-027 | Invoice prefix and initial number editable by authorized admin (example `AUN00058`); generated numbers unique and never casually reused | P0 | CONFIRMED |
| FR-028 | Separate order IDs, gateway transaction IDs, receipts, invoices, adjustments and credit notes | P0 | PROPOSED accounting baseline |
| FR-029 | Customer-downloadable PDF invoice with seller details, buyer, lines, tax breakdown, period, reference and payment status | P0 | CONFIRMED |
| FR-030 | Tax/price rules configurable by product, customer location, legal entity and accounting determination | P0 | CONFIRMED need; specifics PENDING |
| FR-031 | Wallet top-up tax treatment must **not** default to tax-free; finance/legal approval required for deposit versus advance and subsequent supply | P0 | DEPENDENCY |
| FR-032 | Reconciliation, audit trail and export for payment/wallet/invoice activity | P1 | PROPOSED |

**Tax guardrail:** The owner's requested "no GST on adding wallet funds" is a business expectation, **not a validated tax rule**. The product must distinguish wallet funding receipts from taxable supplies and configure treatment only after a qualified Indian tax professional reviews the flow. Invoice numbering and statutory fields must likewise be reviewed for the applicable GST regime.

## 10. Functional requirements — provisioning, domains and operations

| ID | Requirement | Priority | State |
|---|---|---|---|
| FR-033 | Admin-facing VPS inventory with provider, IP, location, vCPU, RAM, disk, SSH port, state, reservation and owner | P0 | CONFIRMED + PROPOSED fields |
| FR-034 | Manual mode: admin assigns matching VPS, confirms access and clicks Deploy; execution automated after click | P0 | CONFIRMED |
| FR-035 | Deployment worker isolates high-privilege SSH operations from public web request process | P0 | PROPOSED security baseline |
| FR-036 | Privileged access through encrypted secrets or ephemeral SSH credentials, no plaintext root passwords in database/logs, rotate/revoke credentials | P0 | PROPOSED security baseline |
| FR-037 | Deploy requested product using versioned recipes, locked dependencies, OS compatibility tests, idempotent retries and health checks | P0 | CONFIRMED intention + PROPOSED controls |
| FR-038 | Generate managed endpoint, access route, TLS, firewall restrictions, user permissions and secure first-login delivery | P0 | CONFIRMED intention + PROPOSED controls |
| FR-039 | Provisioning status visible to customer/staff; failure captures non-secret diagnostics, alerts and retry/reassignment actions | P0 | CONFIRMED |
| FR-040 | Automated mode toggle available to super admins; only active if configured, capacity checks pass and virtualization/provider API is supported | P1 | CONFIRMED intention; implementation PENDING |
| FR-041 | Dedicated-server pool must create strongly isolated VM environments with enforceable CPU/RAM/disk policy, quotas and per-tenant network rules | P1 | DEPENDENCY |
| FR-042 | Do not market simple containers as independent VPS; explicitly label tenancy/isolation model | P0 | PROPOSED truthfulness/security |
| FR-043 | Default subdomain provisioning and custom-domain DNS ownership checks for Workspace, n8n, OpenClaw as supported; never include CLI-only URL selector | P0 | CONFIRMED + PROPOSED safety |
| FR-044 | Service actions: status, supported restart, password reset/rotation, renewal, upgrade, support; destructive actions permission-gated | P1 | PROPOSED |
| FR-045 | Pre-check and enforce per-product minimum resources; a bundle must accommodate multiple applications with headroom | P0 | PROPOSED |
| FR-046 | Separate OpenClaw Gateway/trust boundary per unrelated customer, ideally per VPS or isolated OS host/user; no untrusted shared Gateway | P0 | DEPENDENCY |

**Open question:** whether "automatic VPS parts" means provider-created individual VPS or Proxmox/KVM VMs on an owned dedicated host. These are different cost/security models. Both can fit the same storefront API if an infrastructure-adapter layer is designed.

## 11. Functional requirements — admin, staff and support

| ID | Requirement | Priority | State |
|---|---|---|---|
| FR-047 | Super-admin dashboard: revenue, unpaid/paid orders, deployment jobs, active clients, failures, tickets and renewal alerts | P0 | CONFIRMED |
| FR-048 | Admin CRUD for product plans, tiers, pricing, terms, resource profiles, add-ons and publishing status | P0 | CONFIRMED |
| FR-049 | Support roles with per-module and per-action permissions; custom staff roles supported | P0 | CONFIRMED |
| FR-050 | Backend authorization on each resource/action independent of sidebar/menu visibility | P0 | PROPOSED security baseline |
| FR-051 | Ticket system with user, order/service link, assigned staff, attachments, status, priority and internal notes | P0 | CONFIRMED |
| FR-052 | Telegram/email configurable events: order, payment success/failure, wallet action, deploy success/failure, host capacity alert, renewal | P0 | CONFIRMED |
| FR-053 | Notification templates and recipients configurable; sensitive credentials never sent in plain text messaging | P0 | PROPOSED security baseline |
| FR-054 | Admin audit log with actor, action, object, before/after policy-safe diff, IP/time and approval chain | P0 | PROPOSED |
| FR-055 | High-risk actions (refund, credential edit, auto-provision toggle, server deletion) require stronger permissions and optional secondary approval | P1 | PROPOSED |
| FR-056 | Staff can view assigned workload only; customer data isolation and least-privilege exports | P0 | PROPOSED security baseline |

## 12. Functional requirements — professional services, projects and SEO

| ID | Requirement | Priority | State |
|---|---|---|---|
| FR-057 | WordPress/Laravel/web/AI app hosting/SEO packages have self-contained descriptions, scope, exclusions, pricing/quote policy | P0 | CONFIRMED |
| FR-058 | Request form captures website type, goals, pages, hosting, budget, deadline, reference URLs, attachments and special requirements | P0 | CONFIRMED intention; detailed fields PROPOSED |
| FR-059 | Fixed-scope service: standard checkout; custom scope: lead → discovery → estimate/quote → approval → invoice/payment → project | P0 | PROPOSED workflow |
| FR-060 | Projects include assigned team, milestones, tasks, comments, uploads, revisions and approval records | P1 | CONFIRMED intention + PROPOSED structure |
| FR-061 | Customer portal shows project progress and deliverables; manual review gates before publishing to production | P1 | PROPOSED |
| FR-062 | SEO module can import Search Console data and schedule technical site checks/reports subject to account authorization | P1 | PROPOSED |
| FR-063 | SEO service never promises rank #1 or indexing; show work performed, observations and uncertain outcomes | P0 | PROPOSED marketing guardrail |
| FR-064 | Care/maintenance renewal, backup policy and support entitlements displayed per plan | P1 | PROPOSED |

**Automation boundary:** order creation, staff assignment, reminders, progress tracking, report collection and deployment checklists can be automated; bespoke code quality, SEO strategy, legal copy, creative sign-off, production promotion and client approval remain reviewable human activities.

## 13. Customer and administrator UX specification

### 13.1 Visual direction (CONFIRMED objective; implementation PROPOSED)

- Premium developer-platform identity: refined typography, deliberate spacing, restrained gradients, thoughtful dark/light treatment, subtle depth, fast microinteractions and clear navigation.
- Homepage should explain value in under one viewport, then provide product cards, credible specifications, comparisons, onboarding sequence and proof/trust signals. Do **not** fabricate testimonials, metrics, logos or uptime figures.
- Accessible interfaces with keyboard navigation, visible focus, scalable text, readable contrast, actionable form errors and mobile-first responsive layouts.
- Preserve usable existing brand/frontend components after a real repository audit; redesign only where quality or correctness warrants it.
- Shared system for public pages, checkout, customer portal, staff admin and documentation. Empty, loading, error, refund, failure and deployment states receive first-class design.

### 13.2 Critical screen inventory

| Public / checkout | Customer | Admin / staff |
|---|---|---|
| Home, solutions, product comparison, product detail | Overview, active services, service detail/status | Executive overview, role-specific dashboard |
| Configuration wizard, cart, login/register, OTP | Orders, payment activity, invoice download | Product/plan/resource configuration |
| Checkout, success/failure/retry, billing info | Wallet, add funds, transactions | Order queue, refunds, reconciliation |
| Docs, service comparison, FAQ, legal, contact | Projects, milestones, tickets, docs | VPS inventory, deployment queue, automation policy |
| SEO/WordPress/Laravel service request | Profile, security, domain/API config | Roles/permissions, tickets, notifications, content manager |

### 13.3 UX rules

- A guest must not lose configured cart by signing in or verifying email.
- Dynamic price breakdown must show term, tax estimate, discounts, add-ons, recurring charges, third-party API costs excluded and total **before payment**.
- Admin cannot activate a product with missing mandatory delivery prerequisites (e.g., no eligible deployment recipe).
- Service details must accurately distinguish "awaiting VPS", "deploying", "active", "attention required" and "failed"; never show fake success.
- Docs must be searchable, versioned and linked from product pages, onboarding and support tickets.

## 14. Documentation and customer education

**Customer help center:** `developerjet.com/docs` is the proposed public URL (validate app routing). Organize by Getting Started, Plans & Comparison, Hermes CLI, Hermes Workspace, n8n, OpenClaw, AI API Keys/BYOK, domains/DNS, security, billing/wallet/refunds, renewal, troubleshooting and support. Include a feature matrix: browser UI, CLI, Telegram, automation, domain need, AI API responsibility, supported installations, isolation and limits.

**Service-specific onboarding checklists:** what the service is; what customer receives; how access is delivered securely; setup/verification steps; unsupported use cases; how to connect providers or Telegram; how support/renewals work. Documentation must be updated with software versions and proven setup screenshots (not fictional UI captures).

**Internal runbooks:** provision/rollback, secret rotation, DNS/SSL incidents, gateway webhook failures, refunds, worker backlog, compromised customer instance, storage full, capacity exhausted and backup restoration.

## 15. Core data and integration architecture

### 15.1 Logical bounded modules

| Module | Suggested core entities |
|---|---|
| Accounts & Identity | users, email_verifications, password_resets, sessions, customer_profiles |
| Catalogue | products, plans, plan_prices, resource_profiles, addons, plan_addons, plan_versions |
| Commerce | carts, cart_items, orders, order_items, order_config_snapshots, discounts |
| Payments | payment_attempts, gateway_configs, webhook_events, refunds, reconciliation_records |
| Accounting | wallets, wallet_entries, wallet_topups, invoices, invoice_lines, invoice_sequences, credit_notes |
| Services | services, service_credentials_refs, subscriptions, renewals, domains, dns_validations |
| Infrastructure | infrastructure_providers, servers, capacity_pools, allocations, deployment_jobs, deployment_steps, health_checks |
| Operations | notification_templates, notifications, delivery_attempts, ticket_threads, attachments, audit_events |
| Staff | roles, permissions, role_permissions, user_roles, staff_assignments |
| Professional services | leads, quotations, projects, milestones, tasks, deliverables, approvals, seo_reports |
| Docs | docs_sections, docs_articles, docs_revisions, content_locales |

These names are **conceptual proposals, not tables confirmed in the repository**. A database design must validate against existing migrations and avoid destructive renames until code and real data have been reviewed.

### 15.2 Core lifecycle state machines

**Order:** Draft → Pending Payment → Paid → Awaiting Assignment/Provisioning → Deploying → Active/Completed. Side paths: Payment Failed; Cancelled; Refunded; Deployment Failed; Suspended; Expired. Transition rules must prevent backwards/duplicate state changes.

**Deployment:** Queued → Preflight → Reserved → Installing → Configuring → Validating → Delivered or Failed/Requires Review. Each step records safe logs and a retry strategy.

**Project:** Requested → Discovery → Quote Sent → Approved → Payment Due/Paid → In Progress → Client Review → Revisions → Delivered → Closed. Rejection loops require auditable reasons.

**Wallet funding:** Created → Awaiting Evidence/Payment → Verification → Credited or Rejected; duplicate credits prohibited.

### 15.3 Recommended integration pattern (PROPOSED)

Public Laravel application serves catalogue, identities, order/billing APIs and admin portal. Separate queue workers process email, webhooks, PDFs, renewals and notifications. A hardened provisioning service/worker executes approved SSH, Docker, OS tasks with a limited API and strict audit; public requests **never** run root commands synchronously. CPanel compatibility (PHP/CLI queue/cron/Redis availability) needs real hosting checks; no decision has been finalized for production app hosting.

### 15.4 Example internal interfaces (illustrative, not existing APIs)

- `POST /api/auth/register`, `POST /api/auth/verify-email`, `POST /api/auth/login`
- `GET /api/catalog/plans`, `POST /api/cart/items`, `POST /api/checkout/quote`
- `POST /api/checkout/orders`, `POST /api/payments/{gateway}/start`
- `POST /api/webhooks/{gateway}` (provider-signed endpoint)
- `GET /api/me/orders`, `GET /api/me/services`, `GET /api/me/invoices`
- `POST /api/admin/orders/{id}/assign-server`, `POST /api/admin/deployments/{id}/execute`

Real routing will follow repository conventions after code inspection, and all admin endpoints require staff role authorization.

## 16. Security, privacy and compliance baseline

| Control ID | Requirement |
|---|---|
| SEC-01 | Enforce server-side RBAC and object-level access checks for every customer, staff action, upload and download |
| SEC-02 | Password hashing using framework-supported modern algorithm; email OTP hashing, expiry, attempt limits and session security |
| SEC-03 | CSRF protection for session requests, output escaping, input validation, security headers and dependency patch policy |
| SEC-04 | Store gateway/API/SSH secrets in encrypted secret store; never logs, source control, PDF docs, Telegram messages or client-readable pages |
| SEC-05 | Webhook HMAC/signature validation; replay detection; idempotent ledgers, invoice generation and fulfilment; reconciliation jobs |
| SEC-06 | Tenant/service isolation: separate OpenClaw trust boundary, safe n8n tenancy, unique credentials, least-privileged file access |
| SEC-07 | Backups with tested restoration, separate admin credentials, encryption in transit and at rest where appropriate |
| SEC-08 | Malware/upload restrictions, content-type/size checks, storage outside public root, malicious file scanning where feasible |
| SEC-09 | Immutable high-risk audit events and alerting for abnormal refunds, login attempts, root changes and failed deployments |
| SEC-10 | Use staged releases, versioned provisioning recipes, health checks and recovery plan; protect production credentials |
| SEC-11 | Personal data retention/deletion, privacy policy and consent/contact preferences appropriate to customer jurisdictions |
| SEC-12 | Secure domain ownership verification and HTTPS issuance; no unauthorized DNS/certificate issuance |

**OpenClaw-specific:** Official documentation describes a trust boundary per Gateway and warns against mixing mutually untrusted users under one shared agent/Gateway. Treat separate customer instances as a gating architecture requirement, not a cosmetic tenancy setting.

**n8n-specific:** Before selling hosted access, confirm licence/commercial permissions for this exact managed-hosting/resale model; the website can collect interest but paid provisioning should be gated until cleared.

**AI API keys:** Customer-supplied provider keys are optional at ordering but needed for operation unless plan explicitly includes managed model capacity. Avoid echoing keys and prefer customers entering them via an authenticated secure settings form after deployment.

## 17. Non-functional requirements and service reliability

| ID | Requirement | Target/verification |
|---|---|---|
| NFR-01 | Responsive UI on mobile, tablet and desktop | Automated viewport checks and manual QA across key journeys; performance budget to be agreed |
| NFR-02 | Accessible interactions | WCAG-oriented AA criteria as design target; keyboard/screen-reader test |
| NFR-03 | Security-first deployments | Automated vulnerability/dependency checks, code review, secret scan and penetration-style test of P0 endpoints |
| NFR-04 | Financial integrity | Zero duplicate capture/credit/invoice from repeated callbacks; transactional concurrency tests |
| NFR-05 | Tenant confidentiality | Cross-account access denied; negative tests for every ID-bearing resource |
| NFR-06 | Recovery | Backup schedule, restore drill and RPO/RTO **PENDING** hosting budget and service tier |
| NFR-07 | Availability | No advertised SLA until monitoring, capacity, provider contract and operating process are in place |
| NFR-08 | Observability | Structured logs, job traces, failed-job queue, latency/errors, capacity alarms and operator dashboard |
| NFR-09 | Maintainability | Documented build/test/deploy, environment separation, migrations, coding standards, tests and changelog |
| NFR-10 | Growth | Resource reservations and rate limits; test realistic peak checkouts and worker load before auto mode |

## 18. Required acceptance scenarios (initial QA)

| Test ID | Scenario and pass condition |
|---|---|
| AC-01 | Guest adds configured product, visits checkout, is sent to login/register, verifies OTP, then recovers identical cart |
| AC-02 | Incorrect/expired OTP cannot activate account; brute-force and resend abuse are limited |
| AC-03 | Unverified or unauthenticated user cannot create payable completed checkout or access other accounts |
| AC-04 | Admin edits visible plan price and feature; public card, configurator and checkout quote update consistently |
| AC-05 | Hermes CLI-only checkout has **no subdomain input**; Workspace/n8n domain choice appears where permitted |
| AC-06 | CLI n8n add-on changes quote and provisioning plan correctly; optional AI key may be skipped with accurate onboarding warning |
| AC-07 | Payment failure does not mark paid, create fulfilment or generate false success Telegram alert |
| AC-08 | Repeated valid webhook calls create only one payment capture, invoice sequence assignment and provisioning job |
| AC-09 | Wallet double-spend race, duplicate manual credit and overdraw are rejected |
| AC-10 | Sequential invoice numbers under concurrency remain unique and match configured prefix/start as legally approved |
| AC-11 | Paid hosting order awaits assignment; admin matching VPS + Deploy launches unique traceable job and delivers only after health check |
| AC-12 | Deployment error safely stops, shows non-secret diagnostic, alerts admin and allows controlled retry |
| AC-13 | Auto mode with insufficient host capacity routes to manual queue and emits an alert; no oversold VM or container posing as VPS |
| AC-14 | Staff without `refund.execute` / `server.deploy` cannot access those APIs even by entering URL directly |
| AC-15 | OpenClaw customer A cannot access customer B's gateway files, sessions, tokens or messages |
| AC-16 | Client project order produces appropriate quote/project milestones; SEO report never asserts guaranteed ranking |
| AC-17 | Refund, wallet, invoice and service status reconcile without missing financial event linkage |
| AC-18 | Subscription renewal reminder, cancellation and support tickets produce expected state and event log |
| AC-19 | Documentation accurately states how Hermes CLI, Workspace, n8n, OpenClaw and Bundle differ |
| AC-20 | Existing live/public pages continue to function after redesign or migration; regression suite and rollback documented |

## 19. Prioritized implementation roadmap

### Phase 0 — Discovery and repository truth (P0)

Inspect Git branch/remote, framework versions, route/controller mappings, frontend design tokens, database migrations, tests, `.env.example` (not `.env` secrets), active hosting configuration and current production deployment. Inventory existing page states and compare to this SRS; record gaps. Produce architecture decision records and a migration-safe implementation plan. **Do not begin destructive changes or deploy to production.**

### Phase 1 — Identity, catalogue and UX foundation (P0)

Reusable design system, pages, admin-managed 13 AI plans and digital service products, OTP registration, login/reset, verified checkout requirement, guest cart and customer/admin shell with RBAC.

### Phase 2 — Commerce, payment and billing (P0)

Pricing/configuration engine, orders, Razorpay, PTSure conditional integration, wallet ledger/manual top-ups, legal-approved invoices/receipts, webhook verification, reconciliation, notifications and support desk.

### Phase 3 — Hosting provisioning v1 (P0)

Server inventory, resource profiles, order-to-VPS assignment, secure worker, Hermes CLI/Workspace/n8n/bundle recipes, service health and delivery, tenant boundaries, rollback, domain/SSL and customer service dashboard. OpenClaw only after hardened gateway isolation validation and provider configuration.

### Phase 4 — Professional services (P1)

Service request forms, quoting, WordPress/Laravel/project milestones, client approvals, deliverables and scoped SEO reporting.

### Phase 5 — Auto provisioning and growth (P1/P2)

Capacity manager, provider adapters or dedicated-host hypervisor, guarded toggle, resource accounting, reminders/renewals, advanced monitoring, backup restoration tests, analytics and conversion optimization.

**Dependency gates:** tax treatment approved; n8n commercial permission verified; gateway merchant account and real webhook schema validated; domain automation credentials scoped; OpenClaw trust boundary demonstrated; actual hardware/provider capabilities inspected.

## 20. Decisions register — confirmed vs unresolved

### Confirmed product decisions

| Ref | Decision | Owner / basis |
|---|---|---|
| D-001 | Brand: DeveloperJet — Powered by AmbitionHost | Owner |
| D-002 | DeveloperJet **does not integrate WHMCS** | Prior explicit requirement |
| D-003 | Existing frontend may be redesigned professionally; deliver impressive complete user journey | Owner |
| D-004 | Email OTP verification is required for new account access | Owner |
| D-005 | No final order checkout without verified account; guest cart retained | Owner |
| D-006 | 3 CLI + 3 Workspace + 3 n8n + 1 Bundle = 10 base AI plans | Owner |
| D-007 | Bundle contains Hermes CLI, Workspace and n8n | Owner |
| D-008 | Add 3 standalone OpenClaw plans; 13 AI hosting plans total | Owner accepted recommendation |
| D-009 | No domain selector for CLI only; domain for browser products | Owner |
| D-010 | CLI optional n8n add-on and optional BYOK AI key | Owner |
| D-011 | Payments: Razorpay, PTSure, internal wallet; admin configurable | Owner |
| D-012 | Configurable invoice prefix and starting sequence, separate order ID | Owner |
| D-013 | Manual VPS assignment + single-click install to start; future auto mode toggle | Owner |
| D-014 | Telegram/email alerts and granular support staff access | Owner |
| D-015 | WordPress, Laravel, general web dev, SEO and related digital services included | Owner |
| D-016 | Customer docs and clear product differences required | Owner |
| D-017 | Maintain a persistent project SRS/context so future AI agents need not re-ask everything | Owner |

### Open decisions and external dependencies

| Ref | Open item | Why it matters | Priority |
|---|---|---|---|
| Q-001 | Actual 10 VPS configurations and 13 plan price/resource mapping | Avoid invented costs, incompatible deployments | Before pricing |
| Q-002 | Final resource sizes, plan names, billing intervals, setup fees, regions | Admin catalogue and checkout | Before pricing |
| Q-003 | Production hosting layout: cPanel Laravel + external worker or VPS app | Background queues and safe SSH execution | Before build |
| Q-004 | Automatic mode: provider-managed VPS API vs Proxmox/KVM dedicated host | Isolation and cost model | Before auto mode |
| Q-005 | Whether bundle is one VPS (recommended earlier) or split instance | Resource sizing and tenant separation | Before bundle provisioning |
| Q-006 | OpenClaw exact capabilities, supported channels, API providers and minimum resources | Reliable delivery and support scope | Before launch |
| Q-007 | n8n resale/managed-hosting licence and approved commercial terms | Legal launch gate | Before n8n sales |
| Q-008 | PTSure actual merchant API, currencies, KYC and settlement capabilities | Working gateway | Before integration |
| Q-009 | Tax classification of wallet deposits/advances, invoice series and seller GST identity | Legal/accounting correctness | Before billing launch |
| Q-010 | Wallet funding process: manual transfer only or gateway top-up; evidence and refund rules | Fraud prevention | Before wallet launch |
| Q-011 | Default subdomain scheme, DNS provider API and custom-domain onboarding | TLS automation | Before hosting launch |
| Q-012 | Provisioning recipes and rollback tests for Hermes, n8n, OpenClaw | Predictable install | Before provisioning launch |
| Q-013 | Service renewal/suspension, backup, support and cancellation policies | Contract and automation | Before subscriptions |
| Q-014 | Email provider and authentication/OTP deliverability | Registration success | Before signup launch |
| Q-015 | Exact staff roles and high-risk approval thresholds | Security/RBAC | Before admin launch |
| Q-016 | Development/SEO service scopes, pricing, revision limits and SLAs | Fair quoting and delivery | Before services launch |
| Q-017 | Target launch date, marketing locale/currency and legal pages | Priorities and design | Before production launch |
| Q-018 | Original repo audit, Git branch, deployed environment and data migration | Preserve user work | Immediately |

### Change control rule

Owner-approved changes must be logged with decision ID, date, old vs new requirement, impact and status. Do not silently rewrite prior confirmed decisions. Newly suggested AI features remain PROPOSED until explicitly approved. File updates are part of the acceptance criteria for each milestone.

## 21. Persistent AI-agent documentation protocol

**Intended repository location:** `C:\\laravel\\developerjet.com` (observed via screenshot; no filesystem access was performed). Preserve existing application files; add the accompanying docs only after checking for filename collisions. Recommended structure:

```text
developerjet.com/
  README.md                       # Existing README: preserve
  PROJECT_CONTEXT.md              # New short AI entry point
  docs/
    DEVELOPERJET_SRS.md           # This full specification
    DECISIONS_LOG.md              # Confirmed/open/revised decisions
    HANDOFF.md                    # Last known working state and next step
    CHANGELOG_PROJECT.md          # Completed, tested feature changes
```

**Every new AI session should:** (1) inspect Git status and project root; (2) read PROJECT_CONTEXT, SRS, DECISIONS_LOG and HANDOFF; (3) inspect relevant code/tests; (4) distinguish confirmed requirements from proposals; (5) present a scoped implementation plan for approval, except for explicitly authorized tasks; (6) protect `.env` and customer data; (7) update only docs affected by verified work; (8) finish with changed files, tests, unresolved issues and next action. Do not push/deploy without permission.

**After each completed implementation milestone:** record date, commit hash if known, changed files, tested commands/results, deployed environment (if applicable), new risks, feature status, next step and SRS decision IDs. Documentation should describe reality rather than planned success. Never insert credentials or secrets.

**Import guidance:** download ZIP, inspect its contents, copy files to the repository root/docs folder, and commit only documentation as a separately reviewable change. This SRS does **not** itself execute any terminal prompt, edit the GitHub repository or deploy to cPanel.

## 22. Risks, exclusions and delivery assumptions

| Risk | Treatment |
|---|---|
| Premature promise of 100% automated builds/deploys | Separate manual v1 and conditional auto v2; keep acceptance gates visible |
| Unverified VPS pricing | Don't publish unverified specs; admin draft mode and margin validation |
| Incorrect wallet tax assumptions | Mandatory CA review before enabling taxable transactions |
| n8n resale constraints | Legal/commercial gate; do not assume open source allows all hosting resale |
| Shared gateway/container cross-tenant leakage | Per-tenant strong isolation, penetration-style tests and secret vaults |
| Root credentials in web app | Isolated worker, ephemeral keys/secure vault, strict authorization and audit |
| Existing website changes break site | Repository audit, staging environment, automated regression and rollback |
| AI API provider unavailable | BYOK onboarding, capability matrix, health notices and no fake guarantee |
| Payment retries cause duplicate invoices/orders | Unique constraints, idempotency, verified event replay handling |
| SEO rank guarantees or invented testimonials | Explicit service scope, objective reporting, honest marketing |

**Not automatically included:** provider hardware procurement, unrestricted AI-model usage, guaranteed Google ranking, 24/7 SLA before support capacity, unapproved n8n resale, automatic custom app coding without review, or editing the user's local files merely by producing this document.

## 23. External technical references and notes

The following are **background engineering references**, not proof of DeveloperJet's existing implementation:

1. OpenClaw trust boundaries and gateway isolation: https://docs.openclaw.ai/gateway/security/trust-model
2. OpenClaw Docker / VPS installation guidance: https://docs.openclaw.ai/install/docker and https://docs.openclaw.ai/vps
3. Razorpay security and signed payments/webhooks: https://razorpay.com/security/checklist and https://razorpay.com/blog/payment-gateway-webhooks-india/
4. n8n documentation portal: https://docs.n8n.io/ — verify current commercial/licensing terms independently before hosted resale.
5. Indian GST portal's documentation and invoice materials: https://tutorial.gst.gov.in/ — tax/legal determination requires qualified review.
6. DeveloperJet website: https://developerjet.com
7. AmbitionHost marketing page: https://ambitionhost.in/agent-hosting

**Evidence limitation:** This document was built from the owner conversation and a screenshot. No live Git remote, repository source code, cPanel deployment, exact plan specifications, merchant API credentials or existing database were inspected.

---

**End of DeveloperJet SRS v0.1.** Next recommended owner discussion: validate actual VPS resource profiles, hosting architecture, bundle placement and product price/margin policy **before** authorizing coding.
