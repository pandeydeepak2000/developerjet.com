# Admin Operations Guide

Day-to-day operations in the staff portal. Paths are relative to `/admin`. Everything below was exercised by automated
tests; items marked *(browser-checked)* were also clicked through in headless Edge on an isolated QA database.

## Daily routine

| When | What | Where |
|---|---|---|
| Daily 08:00 (scheduler) | `billing:run`: period-end cancellations, optional expiry, renewal invoices, reminders, grace → overdue → suspension queue | automatic (`php artisan schedule:work` + `queue:work` must run) |
| Every 15 min | `payments:reconcile`, `support:sla-check` | automatic |
| Each morning | Review **Billing & renewals** tabs: Overdue & grace, Suspension queue, Reactivation queue, Cancellation requests, Failed renewals | `/admin/billing` |
| Each morning | **Billing operations** → Overdue invoices; Payment reconciliation (should be empty) | `/admin/billing-ops` |
| Continuously | Support queue (default view = not closed; filters for unassigned, escalated, SLA missed) | `/admin/tickets` |

## Staff management *(browser-checked: sign-in, URL change, sidebar by role)*

- **Invite** (recommended): Staff & roles → *Invite staff member*. The invitee chooses their own password through a
  single-use link valid 72 hours; their email is verified by the link. Existing customer emails cannot be invited.
- **Add directly**: a temporary password you choose; the person must verify their email with a code and change the
  password at first sign-in.
- **Staff detail** (click a name): status, role, departments, two-factor, last sign-in, login history, security events.
  Actions (password re-confirmation required): *Suspend* (reason; signs them out), *Reactivate*, *Sign out all
  sessions*, *Reset access* (forces a new password; optional reset email and two-factor reset), *Reset two-factor only*.
- Staff are never deleted (audit trail); suspend instead.

## Support

- Departments (`/admin/r/departments`, needs `tickets.assign`): name, description, team alert inbox (optional),
  accepts new tickets, SLA first-response/resolution hours (blank = use the priority defaults in *Settings → Support &
  SLA*, which are blank = no SLA until you set them).
- Ticket page: assign (only staff who can see the department; the assignee is emailed), move department, status
  (open, answered, customer reply, on hold, closed), priority, escalate (reason, optional priority raise; 3 levels),
  reply / private note / attachments, activity log, the customer's other tickets. *(browser-checked: private note hidden
  from the customer.)*
- `support:sla-check` marks a ticket once when a target is missed; with *Escalate automatically* on, it also escalates.

## Billing

- **Cancellation requests** (Billing & renewals → Cancellation requests) *(browser-checked)*: *Approve* (end of period:
  the service keeps working until the paid-through date, no renewal invoice is issued, an already issued unpaid renewal
  invoice is voided; immediate: cancelled now) or *Reject* (a note is required and shown to the customer). Customers
  can withdraw. Policy: *Settings → Cancellation, expiry & add-on policy*.
- **Ended services** create a *retention_review* staff task. Nothing is deleted automatically — de-provision and handle
  data manually under your retention policy.
- **Automatic expiry** is OFF until you set "Expire … days after its due date".
- **Add-ons on a subscription** (subscription page; needs `plans.approve` + password re-confirmation): agreed price per
  period, billed from the next renewal. No proration is calculated; under the *manual invoice* policy staff raise any
  part-period charge themselves.
- **Failed renewals**: the tab lists subscriptions whose renewal processing threw an error (count, last error).
  Fix the cause, then *Run renewal processing now* on the subscription.
- **Payment reconciliation**: stale attempts, amount mismatches (held, never auto-paid), verified-but-unpaid orders,
  paid orders without invoice. *Run reconciliation now* uses the same idempotent settle path as webhooks.

## Exports

Admin → Exports. CSV only. Each type needs its own `*.export` permission; every download is audited; no secrets,
ticket bodies, phone numbers or addresses are included.

## Notifications

- Settings → Email (SMTP) / Telegram alerts / Email templates / per-event toggles; secrets are stored encrypted and never
  shown again.
- Notification log (`/admin/r/notifications`): failed Telegram alerts can be **retried** (needs `notifications.edit`);
  the stored alert text is encrypted; the bot token is never logged.
- Real SMTP and Telegram delivery have **not** been verified against live services.

## Things that are deliberately not automatic

Live payments, gateway refunds, supplier purchasing, supplier/provider API calls, SSH/root commands on servers,
suspension through a provider, deletion of customer data. Each needs verified documentation, credentials, an isolated
test environment and explicit owner approval.
