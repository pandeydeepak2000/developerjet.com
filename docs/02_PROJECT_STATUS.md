# Project status - DeveloperJet

Last updated: 2026-11-28. "Verified live" = ran on the owner's test VPS or the live site. Everything else is verified by automated tests on SQLite and, where noted, by browser screenshots on an isolated QA database.

## Current verified baseline

- Full suite (2026-11-28, after the docs cleanup): 495 passed / 4584 assertions.
- Checks run at each milestone: `php artisan test`, `npm run build`, `vendor/bin/pint --test`; `composer audit` had no advisories at the v0.4 and v0.5 checkpoints.
- Production (cPanel) is deployed by the owner with `git merge origin/main`; live install over SSH is switched on (owner confirmed 2026-11-25). Nothing is deployed by AI sessions.

## Module status

Legend: **Live** = built, tested and used in production. **Built** = code + automated tests. **Partial** = usable with the listed gaps. **Gated** = built, switched off until the owner approves. **Not built**.

| Module | Status | Notes |
|---|---|---|
| Customer auth, email OTP, phone | Built | Real SMTP depends on production `.env`. No customer 2FA |
| Website, product pages, carousel, Node.js page | Built | Prices only from DB |
| Catalogue | Built | Owner-approved monthly prices for Hermes CLI, Hermes Workspace, n8n, OpenClaw, Bundle, Docker VPS, Node.js, AI All-Access. Yearly = 12 × monthly. Claude Code, quarterly and half-yearly prices pending |
| Cart, checkout, GST by payment route, billing details | Built | Razorpay orders +GST (default 18%), full-wallet orders GST-free |
| Razorpay | Built | Signature + webhook + reconciliation tested with fakes; no real-money end-to-end test recorded. Refunds recorded manually. PTSure removed |
| Wallet | Built | Append-only ledger, staff-verified top-ups, admin customer picker |
| Orders, invoices, credit notes | Built | State machine, timeline, idempotent placement, dompdf invoices |
| Subscriptions, renewals, cancellation | Built | `billing:run` daily; price snapshots; no proration engine by design; automatic expiry off |
| Server setup on the order page | Live | Server dropdown or new VPS (IP + root password/key), app address, size checks, Reset setup |
| Live install over SSH | Live | phpseclib, trust-on-first-use host key, streamed terminal, resume. Verified live: n8n (with HTTPS), Docker VPS, Hermes CLI, Claude Code. Node.js verified in a clean container only |
| Hermes Workspace, OpenClaw, AI Complete Bundle install | Gated | No approved script; Deploy stops safely, staff use "mark delivered" |
| Server actions (admin) | Partial | Restart/stop/start verified live on the test VPS. Reboot, n8n reset login, reinstall/wipe not run live |
| Customer VPS panel | Partial | Tabs, live usage (stats.sh verified live, read-only), own/generated root password (chpasswd verified in a throwaway container), reboot/restart/reset login, reinstall. Password change and reinstall not run on a real server yet |
| AI All-Access delivery | Built | Staff paste the gateway key; customer page shows base URL + key + snippets. ModelGo reseller permission still to confirm |
| Customer dashboard | Built | Standalone app shell, overview, services, orders, invoices, payments, wallet, subscriptions, AI API keys, tickets, quotes, projects, profile; light/dark theme |
| Admin panel | Built | 9-section sidebar with tabs (`AdminNav`), white-blue theme with dark mode, dashboard KPIs, User Manager with view-as-client (read-only) |
| RBAC and staff security | Built | 7 roles + custom, separate staff guard, dynamic login slug, MFA with recovery codes, re-auth, session epoch |
| Support tickets | Built | Departments, SLA (optional), escalation, private notes, attachments |
| Business Automation, digital services, projects | Built | Quote-only; acceptance never charges; notifications tested with fakes |
| Notifications | Partial | Queued, deduped, retryable. Real Telegram delivery not verified |
| Infrastructure inventory | Built | Providers, offers, servers, cost/margin guards, supplier renewal alerts. All suppliers manual |
| Supplier APIs, Proxmox | Not built | ManualAdapter only; ProxmoxAdapter hard-disabled |
| Exports | Built | CSV only (9 types), allow-listed columns. No XLSX |
| Help centre (`/docs`) | Built | Markdown articles from `database/seeders/docs/`, ranked search, upgrade-safe seeder |
| Backups, monitoring | Not built | Backups are the customer's responsibility (self-hosted); no app-level DB backup |

## Pending and blocked

**Owner decisions**
- Claude Code plan prices; quarterly and half-yearly prices.
- Confirm with ModelGo that reselling API keys is allowed before selling AI All-Access at scale.
- n8n Sustainable Use License restricts paid resale: the owner accepted this risk (2026-11-25).
- Company details, GSTIN, invoice prefix and start number; billing defaults; SLA targets.
- Which existing roles get newer permissions (`customers.impersonate`, `tickets.reply`, exports).

**Needs a test server or real credentials**
- Install scripts for Hermes Workspace, OpenClaw and the Bundle (run on a test VPS first).
- Run customer root password change, customer reinstall, reboot and n8n reset login on a test VPS (not on a customer server).
- Node.js through a full Deploy on a VPS.
- A real Razorpay payment end to end; real Telegram delivery; SMTP confirmation on production.
- Proxmox / own hypervisor for power off/on, browser console and OS reinstall from the panel (needs a dedicated server).
- Supplier API adapters (only from official docs + authorised credentials).

**Hygiene**
- Change the test VPS root password that was shared in chat.
- Rotate the Cloudflare API token used by the malicious worker incident (2026-11-24).
- Real-device (iOS/Android) and screen-reader testing; XLSX exports; per-plan renewal overrides.

## Security findings fixed

- v0.3: renewals re-charged the setup fee and used the current list price (fixed to the snapshot). Admin subscription actions check permissions server-side; customer subscription pages are owner-scoped.
- v0.4: shared admin throttle counter (named limiter `staff-area`); webhook event committed before settlement (one transaction); customer reset form could reset staff accounts (separate broker); role validated before authorisation; `is_active` cast.
- v0.4 independent review: owner-only customer policy; Super Admin accounts protected from non-super managers; re-auth on staff edit; security/SMTP/Telegram settings Super Admin only; TOTP/recovery code replay (compare-and-set); per-account TOTP limit; login timing equalised; staff audit on the staff guard; invitation revocation guard.
- 2026-11-24: SMTP settings read `env()` after `config:cache` (fixed to `config()`); malicious Cloudflare Worker injected a fake "verify you're human" popup (worker deleted by the owner, origin verified clean).
- 2026-11-27: one `throttle:N,M` counter shared across panel routes blocked password changes after usage polling (named limiters `vps-stats`, `vps-sensitive`).
- Documented, not changed: staff accounts can still sign in at `/login` and act only as customers; the portal hint cookie points former staff at the current URL; `Setting` uses a per-process cache (flush under Octane or long-lived workers).

## History

- 2026-11-19 Phases 2-4: 146 / 817. RBAC, catalogue, orders, Razorpay fakes, wallet, invoices, subscriptions, notifications, tickets, provisioning tables, projects, SEO, docs.
- 2026-11-20 resume pass 149 / 858; v0.2 hybrid infrastructure 203 / 1314 (providers, offers, inventory, cost guards, recipes, fake transport, Proxmox disabled, staff TOTP); docs centre rebuild 244 / 1876.
- 2026-11-20 v0.3 master execution, milestones A-P: 283 / 2201. Mobile drawer moved outside the header, 5-step customer flow, phone on registration, 4 billing periods, renewal engine, billing admin; two billing defects fixed.
- 2026-11-20 v0.4 completion sprint: 357 / 2942. Staff portal and dynamic login URL, Super Admin init, staff management, support workflow, MFA with recovery codes, renewal lifecycle, CSV exports, notification retry, headless QA 320-1440; independent security review (9 findings fixed).
- 2026-11-21 v0.5 Business Automation: 391 / 3310. Three product groups in navigation, `/business-automation`, requirement form, 10-status lead lifecycle.
- 2026-11-21 admin redesign 398 / 3330; staff session persistence (sliding 480-minute window) and responsive fixes 409 / 3425; User Manager with read-only impersonation; scale indexes.
- 2026-11-23 customer setup timeline and service page 414 / 3455; deployment automation coverage (14 plans through a fake transport, staff-confirmed size for profile-less plans, dry runs never execute) 429 / 3894; customer SSH guide and cPanel guide.
- 2026-11-24 SMTP config-cache fix 430; wallet customer picker 435; view as client without OTP 437; admin people UX 440; owner catalogue (AmbitionHost tiers, Docker VPS plans, PTSure removed, settings tabs) 444; per-product plan names and yearly = 12 × monthly 445; Bundle priced at ₹5,999; customer dashboard redesign and standalone app shell 446; checkout app address 451; GST by payment route 456; billing details on the profile 459; saved AI API keys 464; everything self-hosted (root access for every product) 464; Razorpay opens straight after checkout 465; one-step server setup on the order page 470.
- 2026-11-25 optional server name/size 471; server dropdown on the order page 472; simple delivery roadmap phase 1 (no AI key at checkout, Server login panel, mark delivered, n8n licence gate removed) 473; admin theme v2, step cards and install terminal 473 / 4264; deploy status and Reset setup 474.
- 2026-11-25 LIVE INSTALL approved and verified on the test VPS: 475 / 4309. phpseclib SshTransport with host-key pinning, streamed SshWorker, reviewed scripts. n8n (with HTTPS on client1.developerjet.com), Docker VPS, Hermes CLI and Claude Code installed through the app. Mark delivered only when automation cannot deliver 476; customer service page as a server panel 476; one-click server actions (restart/stop/start verified live) 481; `orders:purge` 484.
- 2026-11-27 Node.js Hosting (page, 3 plans, recipe verified in a container) 485; AI All-Access launched as API credit plans 484; customer VPS panel with live usage, own root password, reinstall 495 / 4571; Admin guide PDF (Hinglish, 23 screenshots).
- 2026-11-28 UI refresh: 9-section admin sidebar with tabs, white-blue theme with navy dark mode for admin and client, 495 / 4583.
- 2026-11-28 docs cleanup: deleted 9 outdated or duplicate docs (Developer Handover, Easy Guide, Simple Roadmap, Admin Operations Guide, Production Readiness Checklist, Sales Team Operations, the three Staff guides) plus `AI_ALL_ACCESS_TASK.md`, `PROJECT_CONTEXT.md` and two stale PDFs. Their still-true content moved into `docs/01`, `03`, `04_STAFF_ACCESS.md` (new) and `05_CPANEL_DEPLOYMENT.md` (renamed, with the go-live checklist). Admin guide renamed to `docs/06_ADMIN_GUIDE.md`. Developer guide, workflow, status, README, CLAUDE.md, diagrams and 20 customer help articles updated to match the code (old bodies registered in `DocsSeeder::SHIPPED_BODY_HASHES`), new help article `products/nodejs-hosting.md`. 495 / 4584. Production needs `php artisan db:seed --class=DocsSeeder --force` for the help articles.
