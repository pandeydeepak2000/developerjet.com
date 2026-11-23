# Complete workflow - DeveloperJet

Last verified: 2026-11-21. Local only, nothing production verified.
Supersedes: docs/DEVELOPERJET_WORKFLOW_GUIDE.md, docs/BUSINESS_AUTOMATION_WORKFLOW.md, docs/BILLING_RENEWAL_WORKFLOW.md, docs/ADMIN_RENEWAL_SETTINGS.md, docs/ADMIN_PROVISIONING_RUNBOOK.md and the workflow parts of docs/DEVELOPERJET_MASTER_EXECUTION.md, docs/DEVELOPERJET_COMPLETION_SPRINT.md and docs/DEVELOPERJET_BUSINESS_AUTOMATION.md. Operational guides kept separately: docs/ADMIN_OPERATIONS_GUIDE.md, docs/STAFF_PORTAL_GUIDE.md, docs/STAFF_ROLES_AND_PERMISSIONS.md, docs/SALES_TEAM_OPERATIONS.md, docs/STAFF_LOGIN_TROUBLESHOOTING.md, docs/PRODUCTION_READINESS_CHECKLIST.md. Backup: C:/tmp/djet-cleanup-backup-2026-11-21/docs.

Status words: Built and tested means code exists and automated tests pass locally. Gated means built but deliberately switched off. Planned means not built. Nothing is production verified.

## 1. Overview

DeveloperJet, powered by AmbitionHost Pvt. Ltd., sells AI hosting, developer VPS hosting and digital services. Customers register, pick a product, pay, then the team assigns a VPS and installs the software. Customers manage billing, renewals, support and service from one dashboard. Delivery follows manual VPS assignment and is not instant.

## 2. Products

- Hermes CLI, 3 plans, managed, illustrative price, awaiting owner approval.
- Hermes Workspace, 3 plans, managed, illustrative.
- n8n Managed, 3 plans, managed, illustrative, sales gated by n8n licence check.
- AI Complete Bundle, 1 plan, managed, pending, no price or resources.
- OpenClaw, 3 plans, managed, pending, gated by specification approval.
- Claude Code Hosting, 3 draft slots, managed, pending.
- Docker VPS Hosting, 3 draft slots, unmanaged, pending. This is the single canonical Docker link.
- AI All-Access, 3 draft plans, presentation only, all providers Planned, BYOK, never sellable.
- Digital services, quote-based, nothing charged automatically: WordPress, Laravel, Web, SEO, Custom AI Application, Website Maintenance, Website and Application Deployment, Business Automation.
- Business Automation, 3 draft quote-only plans: Automation Starter, Automation Growth, Automation Enterprise, price_status pending, editable in Admin Plans.

Managed versus unmanaged: managed means DeveloperJet handles server setup, security and OS updates, root and SSH are restricted by service policy, customer sees application dashboard, AI config, usage when available, billing and support. Unmanaged means DeveloperJet installs Docker, then the server is the customer's, root and SSH are provided, customer handles OS updates, customer sees server details, SSH access, billing and support. The system refuses to assign an unmanaged server to a managed plan and vice versa. Managed versus unmanaged is not a top-level sales product; it is explained on each product page and in comparisons.

## 3. Customer journey

1. Choose a plan. 2. Configure your service. 3. Complete checkout. 4. We prepare your environment. 5. Access your service.

Registration: customer enters name, email, phone and password. The system emails a 6-digit one-time code, expires, limited attempts, resend throttled. Until verified, checkout and dashboard stay locked. After verification the customer continues to checkout; the cart is preserved across login. Phone is required for new registrations, +91 default, E.164, no SMS. Existing accounts without a phone keep working and can complete it at /dashboard/profile.

Product selection: every product page answers what it is, who it is for, what you can do, benefits, what is included, what is not included, skills needed, whether a separate AI subscription is needed, resources, how deployment works, how access works, support limits and renewals. Plans without approved prices show Pricing and resources to be confirmed and cannot be ordered.

Cart and checkout: checkout shows managed or unmanaged, resources or to be confirmed, billing period, and the sentence that delivery follows manual VPS assignment and is not instant. Tax is shown only if configured, otherwise the page says tax not configured. A checkout token makes placement idempotent.

## 4. Payment verification

A plan is chargeable only when its price status is approved. Razorpay: server creates the order, browser result is never trusted, payment is verified only after a valid signature in checkout or webhook signature, amount match and idempotent event log. Reconciliation command payments:reconcile settles captured payments the browser missed. Status: built, tested with fakes, no real sandbox payment yet. Wallet: append-only ledger, balance can never go negative, reserved at placement, released on cancel. PTSure: gated, stays off until verified merchant API documentation exists. An order becomes paid only through the verified-payment path.

## 5. Order lifecycle

States: draft, pending_payment, paid, awaiting_provisioning shown as Awaiting VPS assignment, provisioning, active. Side paths: failed retryable, cancelled, refund_pending, refunded. Transitions are enforced centrally; an invalid jump is rejected. Every change is recorded in the order timeline. When a hosting order becomes paid, the admin team receives a VPS assignment required alert once per order.

## 6. Manual VPS purchase and assignment

Suppliers: DesiVPS, EricHost, Contabo Core, Contabo Performance Plus, Server Broker. None is assumed to have an API. Admin opens Admin Infrastructure to see paid orders waiting for a server, checks vCPU, RAM and storage and access model, reuses an unallocated fitting server or buys one, then records it in Admin Servers New with server ID or label, supplier, IP, region, OS, CPU, RAM, storage, purchase cost, billing period, supplier renewal date, supplier reference, pinned SSH host fingerprint and an encrypted temporary credential. Never store plaintext root passwords.

Cost versus selling price are separate. Provider offers hold supplier cost and a reference selling price; plans hold the approved customer price. Settings can set a maximum monthly server cost and a minimum margin percent; assignments that break either are rejected. If blank, nothing is enforced and nothing is invented. Yearly supplier cost is converted to monthly for comparison.

Assignment checks under a database lock: order is paid, server is available and unassigned, resources fit, access model matches, CPU architecture is supported, cost limits and margin hold. Only one server can be attached to an order. Customers see server IP and details only after the service is active.

## 7. Automated installation

Installation is described by versioned recipes. A queue worker runs the steps. The 17-stage design is: 1 validate assigned server, 2 verify resources, 3 verify connectivity and pinned SSH host identity, 4 check OS Ubuntu 22.04 or 24.04 or Debian 12, 5 check architecture x86_64 and free disk, 6 security baseline, 7 dependencies, 8 the application, 9 isolated services, 10 authentication, 11 domain when needed, 12 HTTPS when possible, 13 health monitoring, 14 application health check, 15 record final state, 16 deliver first-time access, 17 notify customer and admin.

What is real today: steps 1 to 5 use read-only approved commands. Every install and configure step has no approved command yet, so a live run stops safely at Security baseline. The live SSH connector is inert, so live deployment is blocked end to end. The whole run is tested using a fake connection, covering success, failures, host-key mismatch, unsupported OS, arch and disk, and resume after failure. Safety rules: only fixed command templates, only placeholder is a DNS-verified strictly validated domain quoted for a POSIX shell, customer text never reaches a command, raw output is never stored, completed steps are remembered so a retry resumes instead of repeating work.

Recipes: Hermes CLI 1.1.0, no subdomain. Hermes Workspace 1.1.0, Docker, default or verified custom domain, HTTPS step. n8n 1.1.0, persistence, backup, domain, gated until commercial managed-hosting licence is verified. OpenClaw 0.2.0, isolated service user, gateway authentication, gated until specification is approved. Claude Code 0.1.0, customer supplies eligible Claude subscription or API credentials, access is never resold. Docker VPS 0.1.0, Docker Engine and Compose, unmanaged. AI Complete Bundle 1.1.0, Hermes runtime installed once, Workspace plus n8n, network isolation, gated because it includes n8n. Customer AI keys are encrypted, write-only, never shown or emailed. A customer may skip AI configuration; the service then shows Configuration incomplete.

When deployment succeeds the service becomes active, the order becomes active, the customer is emailed never a password, and the dashboard shows access instructions and server details. If it fails, the service stays inactive, the order is marked failed, and the admin is alerted; the customer is told the team will retry.

## 8. Customer service panel

The service page shows service type, access model, server status, IP, region, CPU, RAM and storage, OS, renewal date, deployment progress, installed applications and support links. Then a Controls list. A control is Available only when the service is active and a verified provider integration exists. Today every control is shown as Unavailable with a reason, including web console, snapshots, backups, power, reinstall and firewall. We do not fake a KVM console and we never call a Docker container a dedicated KVM VPS.

Provider capability matrix: application status, restart and logs, SSH keys, power, web console, snapshots and backups, reinstall, firewall and resource usage are Unavailable for manual suppliers today, Possible or Planned only if a supplier API is verified or Proxmox is enabled in future. Each provider record has an API status none, unverified or verified and a list of verified controls that an admin fills in only after proving each one. A control appears for customers only if the provider says yes and a built adapter implements it.

Future Proxmox plan: OVHcloud or similar dedicated servers running Proxmox VE with KVM virtual machines. The interface for nodes, clusters, resource pools, templates, CPU, RAM and disk allocation, IP management, VM create, start, stop, reboot, console, snapshots, backups, migration and capacity monitoring is documented in code as a disabled adapter that fails closed and is hard-disabled regardless of configuration. It must use least-privilege API tokens and must not connect to any production node until dedicated hardware, networking, storage, API authorisation and security testing are approved.

## 9. Billing and renewals

Subscriptions track period and next due date. A daily job sends reminders, overdue notices and creates one renewal order per period. Stored payment details are not charged automatically. Paying a renewal order extends the existing subscription and service; no new service is created. Invoices are only for paid orders, atomic sequential numbering with configurable prefix, padding and first number, PDF, emailed, credit notes for refunds. GSTIN appears only if configured. No tax rate is assumed.

Renewal invoice timing default 7 days before due, admin-editable. Reminders default 7 and 3 days before, due date, 1 day overdue, all configurable, enable or disable, editable email templates, Telegram admin alerts, failure logs, retry controls. Reminder email includes customer name, service, invoice, amount, due date, renewal link and support information. Reminders are rows in subscription_reminders, deduped by subscription, kind and due date, sent by queued job SendRenewalReminder with 3 tries, retry from Admin Billing.

Subscription states: active, renewal_due, grace_period, overdue, suspension_pending, suspended, reactivation_pending, cancelled, expired. Grace default 3 days, auto-suspend OFF by default, approval ON, live adapter suspension OFF. With no verified adapter, the system creates a billing_actions staff task and Telegram alert and marks suspension_pending. Adapter failure keeps suspension_pending and alerts. Suspension is per service, never per account, nothing is deleted.

Reactivation: verified payment settles the invoice, extends the period from the paid-through date, continuous and month-end safe, then active, or for a suspended service reactivation_pending plus staff task until staff confirm or a verified adapter reactivates and passes a health check. Duplicate callbacks or wallet re-apply do not extend twice.

Renewal add-ons: recurring add-ons per subscription as price snapshots, snapshot from purchase, staff add with plans.approve and re-auth, billed from next renewal, removal stops at next renewal. Mid-cycle add-on policy is a setting, no automatic proration. Cancellation: customer requests end of period or immediate if allowed, approval setting, one live request, withdraw, cancellation at period end means no renewal invoice and unpaid future renewal invoice voided, cancelled by billing:run at period end. Automatic expiry is OFF by default with a days setting. Ended services open a retention_review task instead of deletion. Cancelled and expired history is shown to customer and admin. Failed renewals are tracked with count, last error, alert threshold, Failed renewals tab and retry.

Supplier renewals: each server has a renewal date; a daily job alerts admins once per renewal date, overdue too.

## 10. Notifications

Events: new order, payment success, payment failure, VPS assignment required, deployment started, failed and completed, server monitoring alert, server renewal due, renewal reminders, invoice generated and due, support ticket created. Channels: Telegram for admin, email for customer and templates, and the admin notification centre. Alerts are queued with retries and de-duplicated. In Admin Settings, Notification test sends a Telegram message to the admin chat or an email to the signed-in staff member only. Neither Telegram nor SMTP has been tested against real services yet.

## 11. Support

Customers open tickets from the dashboard with department, priority and safe attachments. Tickets are numbered and assigned; staff reply publicly or add private notes never visible to customers. Staff only see departments they belong to. Technical Support staff can view servers and deployments but cannot assign servers or start deployments. Departments have description, alert inbox, active flag and SLA hours. Queue filters include status, priority, department, mine, unassigned, escalated, SLA missed, search by number, subject or customer. Assignment only to staff who can see the department, plus assignee email. Statuses include on_hold. Priority changes recalculate SLA from open time. Transfer and escalation have 3 levels. SLA is optional hours per priority with department override, blank means no SLA, support:sla-check runs every 15 minutes, idempotent, optional auto-escalation. Ticket activity log, customer other tickets, dedicated tickets.reply permission, legacy tickets.edit still works.

## 12. Business Automation

Landing page: /business-automation. Hero, business problems, industry use cases, automation examples, three service plans, how it works, requirement form, FAQs, contact sales CTA. Use cases are possible service offerings, not integrations already deployed: lead capture, WhatsApp workflow, CRM, email marketing, customer support, appointment reminders, sales follow-up, invoice and payment notifications, e-commerce orders, business reporting, data synchronisation, AI chatbot, custom API integrations, n8n workflow development.

Requirement form: required customer name, email, phone, business type and automation requirements. Optional company name, existing website URL, business size, current software or tools, services to integrate, preferred timeline, estimated budget and additional notes. Phone uses safe validation. Never ask for passwords, private API keys or confidential credentials. Protection: CSRF, rate limiting, spam protection, honeypot, signed timing token, sanitisation, duplicate suppression, secret redaction. Each request has a unique reference number and a secure database record. Guests can submit without purchase or payment; logged-in customers are associated with their account. After submission the customer sees confirmation and reference, an admin lead is created, and acknowledgement, sales email and Telegram notifications are queued.

Notifications: sales destination is configurable from Admin Notifications or Sales Settings, preferred address support@developerjet.com, do not assume that mailbox exists or works. Customer acknowledgement contains name, reference, confirmation title, summary, contact details and next steps. Admin email contains request number, name, email, phone, business type, requirements summary and dashboard link. Telegram message contains new lead title, reference, name, phone, email, business type, short summary and admin link. Protect personal data and credentials; do not send sensitive secrets through Telegram. Bot token and chat ID are encrypted and configured using existing settings. Jobs are queued with retries, failure logging, event deduplication and admin test controls. Use fake transports in tests; never claim delivery if SMTP delivery has not been verified.

Sales lifecycle, 10 statuses: New Request, Reviewing, Assigned to Sales, Requirements Discussion, Quotation Prepared, Quotation Sent, Customer Accepted, Customer Declined, Converted to Project, Closed. Sales staff can view assigned leads, review requirements, add internal notes, contact the customer, request more information, prepare a quotation, assign follow-up owner, record follow-up dates, change permitted status and convert accepted requests into projects. Admin can assign sales or technical staff. Appropriate permissions are required. No quotation can automatically become a paid order without customer acceptance and verified payment. Audit logs record status changes. Accepted quotes link to the existing digital-services project workflow. Customer updates are sent for meaningful milestones. Private internal notes are never exposed.

Staff permissions for leads: Sales sees only owned leads. Support, Billing and SEO cannot work leads. Technical Support cannot see supplier costs or revenue and cannot overwrite costs through the form. See docs/SALES_TEAM_OPERATIONS.md and docs/STAFF_LOGIN_TROUBLESHOOTING.md.

## 13. Staff portal and admin

Staff sign in at /staff/{slug}/login, default developerjet, on the separate staff guard. All roles use the same login page: Super Admin, Administrator, Sales, Technical Support, Billing, Developer, SEO and custom staff. Super Admin can change the slug from Settings, with live preview, availability check, password and TOTP, atomic save, audit log and history; old URL becomes 404. CLI recovery: php artisan staff:portal --show, --set=, --reset. Super Admin init is only via interactive php artisan staff:create-super, with strong password twice, unverified email, forced password change and mandatory MFA. Staff management covers invite, suspend, reactivate, reset access, revoke sessions, login history and security events. Sensitive actions need re-authentication within 10 minutes. See docs/STAFF_PORTAL_GUIDE.md, docs/STAFF_ROLES_AND_PERMISSIONS.md and docs/ADMIN_OPERATIONS_GUIDE.md.

## 14. Failure recovery

- Payment captured but order unpaid: reconciliation command settles it; duplicate webhooks are ignored.
- Deployment failed: service stays inactive, order moves to failed, admin reads the deployment page for the failed step, fixes the cause, then retries; the order returns to Awaiting VPS assignment and a new attempt resumes after confirmed steps.
- Cannot connect or host key mismatch: run stops before any command; check pinned fingerprint and supplier.
- Unsupported OS, arch or disk: stop; ask supplier to reinstall OS or pick another server.
- Request suspension: admin with the right permissions records a suspension request, audited. Because suppliers are manual, a person carries it out with the supplier; nothing is suspended automatically.
- Wrong assignment: assign a different server before deploying; the first one is released.

## 15. Planned versus tested versus production-ready

Everything marked Built and tested above is tested locally only. Real Razorpay sandbox payment, live SSH install, supplier API adapters, Proxmox KVM, backups, snapshots and console are planned, not tested locally and not production-ready. Decisions needed from the owner: approve prices and resources for all families, name and specify Claude Code and Docker plans, OpenClaw specification, n8n commercial-hosting licence, tax configuration, invoice prefix and starting number, company details, SMTP and Telegram credentials, SSH library and approved install commands, cost limit and minimum margin, support scope wording. Next safe steps: run a Razorpay sandbox payment, choose and verify a disposable VPS, approve and test real install commands one recipe at a time, verify each supplier API before building an adapter, add recovery codes for staff 2FA where missing, add export endpoints and a capacity dashboard where still missing.
