# Staff Roles and Permissions

Source of truth: `config/permissions.php` (catalogue + default role templates) and the `roles` / `role_permissions`
tables. Every permission is enforced **on the server** in each controller or route middleware; the sidebar only hides
what a role cannot open (verified by `AdminCompletenessTest`, `AdminAccessTest`, browser checks).

## Default roles (seeded once; a Super Admin may edit them)

| Role (slug) | Summary of default grants |
|---|---|
| Super Admin (`super-admin`) | Everything, always. Cannot be edited. |
| Administrator (`admin`) | Customers view/edit, catalogue (no price approval), orders view/edit/assign, payments/wallet/invoices view, subscriptions, servers, deployments, projects, SEO, tickets, docs, notifications view. **No** staff, settings, price approval, refunds, exports. |
| Billing Staff (`billing-staff`) | Customers/orders view, payments view/export, wallet view/create/approve, invoices view/create/export, subscriptions view. |
| Technical Support (`technical-support`) | Customers/orders/servers/deployments view, tickets view/reply/edit, docs view. |
| Sales Staff (`sales-staff`) | Customers/orders/wallet view, projects view/create, tickets view. |
| Developer (`developer`) | Projects view/edit, servers/deployments view, deploy, tickets view. |
| SEO Manager (`seo-manager`) | Projects view/edit, SEO all, docs view, tickets view. |
| Custom roles | Any combination, created by a Super Admin. |

Note: roles are seeded only when missing. Permissions added in this sprint (`tickets.reply`, `*.export` additions)
are **not** added to roles that already exist in a database — a Super Admin grants them in Staff & roles.
`tickets.edit` continues to allow replying, so existing support roles keep working.

> Owner decision needed: Technical Support can see supplier records and supplier cost prices through `servers.view`
> (Providers, Provider offers, Servers). No credentials are visible (providers store none; SSH keys are write-only),
> but you may want a narrower custom role for support.

## Requested permission names → implemented permissions

| Requested | Implemented as |
|---|---|
| customers.view / customers.edit | `customers.view`, `customers.edit` |
| orders.view / orders.manage | `orders.view`; manage = `orders.edit` (+ `orders.assign`, `orders.approve`) |
| payments.view / payments.refund | `payments.view`, `payments.refund` (refunds are recorded manually; no gateway refund API) |
| wallet.manage | `wallet.create` (adjustments) + `wallet.approve` (top-up approval) |
| invoices.manage | `invoices.create` (+ `invoices.refund` for credit notes) |
| tickets.view / tickets.reply / tickets.assign | `tickets.view`, `tickets.reply`, `tickets.assign` (+ `tickets.edit` for status/priority) |
| servers.view / servers.assign | `servers.view`, `servers.assign` |
| deployments.run | `deployments.deploy` |
| plans.manage | `plans.create/edit/delete`; price approval = `plans.approve` |
| staff.manage | `staff.view/create/edit` (role configuration is Super Admin only) |
| settings.manage | `settings.edit` (staff portal URL is Super Admin only) |
| exports | `<module>.export` per data type (see Admin → Exports) |

## Guard rails

- Only a Super Admin can configure roles, assign the Super Admin role, or manage (edit, suspend, reset, revoke) a Super
  Admin account. At least one active Super Admin must remain; nobody can suspend or demote themselves.
- A non-super manager can only assign a role whose permissions they themselves hold. The requested role is
  authorised **before** any other validation.
- Ticket access = `tickets.view` **and** membership of the ticket's department (Super Admin: all). Assignment only to
  staff who can see that department. Ticket exports follow the same scope.
- Department configuration (SLA, alert inbox, active) needs `tickets.assign`.
- Add-on prices (catalogue and per-subscription) and agreed-price changes need `plans.approve`.
- Support roles get no settings, payment-gateway secrets, provider credentials, customer passwords, root credentials,
  staff management or wallet adjustments (asserted in `SupportWorkflowTest`).
