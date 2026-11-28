# Staff access, roles and login help - DeveloperJet

Last updated: 2026-11-28. Replaces the old Staff Portal Guide, Staff Login Troubleshooting, Staff Roles and Permissions and the permission part of the Sales Team Operations guide. Sources of truth: `config/staff.php`, `config/permissions.php`, `App\Support\StaffSecurity`, `App\Support\AdminNav`.

## 1. One login page for every staff role

```
https://<your-domain>/staff/<slug>/login        (default slug: developerjet)
```

Every role (Super Admin, Administrator, Billing Staff, Technical Support, Sales Staff, Developer, SEO Manager, custom roles) uses this page on the separate `staff` guard. After sign-in each person lands on the first page their role may open. The customer sign-in (`/login`) never opens the admin area.

| Page | Path |
|---|---|
| Sign in / forgot password / reset | `/staff/<slug>/login`, `/forgot-password`, `/reset-password/<token>` |
| Two-factor check / email ownership check | `/staff/<slug>/two-factor`, `/staff/<slug>/verify-email` |
| Invitation | `/staff/<slug>/invitation/<token>` |
| My profile (name, password, sessions, sign-in history) | `/admin/profile` (name menu at the bottom of the sidebar) |
| My security (authenticator, recovery codes) | `/admin/security` |
| Staff portal URL (Super Admin only) | `/admin/settings/staff-portal` (name menu) |

`/admin/login` does not exist. `/admin` without a staff session returns 404, except in a browser that signed in as staff before (`dj_staff_portal` hint cookie), which is sent to the current login URL.

## 2. How sign-in works

1. Email + password. Only active staff accounts are accepted. The error is always "These credentials do not match an active staff account." and never says why. 5 failures per email + IP lock that pair for 5 minutes (route limit 20/minute). Every attempt is audited.
2. If an authenticator is enrolled: a 6-digit code or a recovery code (`XXXXX-XXXXX`). 5 failures lock for 5 minutes; 10 failures per account in 30 minutes also lock.
3. If the email was never verified: a 6-digit code is emailed and must be entered.
4. Before any admin page opens: a forced password change (new accounts, reset access) and, for Super Admins (or everyone when Settings → Security → Require two-factor is on), authenticator enrolment. 10 recovery codes are shown once, stored as HMAC-SHA256 hashes, single use.

**Sessions.** The staff session is a sliding window of `STAFF_SESSION_LIFETIME` minutes (default 480); `SESSION_LIFETIME` must be at least as long. Password change/reset, suspension, MFA reset, "Reset access" and "Sign out other sessions" end existing sessions at once (per-account session epoch).

**Re-authentication.** Sensitive actions (suspend/reactivate staff, reset access, revoke sessions, reset someone's two-factor, priced add-ons, email change, view as client, changing the portal URL) need a recent password (+ code) confirmation. The window is `staff.reauth_minutes` (480) and is set at sign-in, so normally you confirm once per working day.

## 3. Default roles

Roles are seeded once (`RbacSeeder`, create-only) and a Super Admin may edit them in Settings → Staff & roles → Roles & access.

| Role | Default grants (summary) |
|---|---|
| Super Admin | Everything. Cannot be edited. Only role that can configure roles, change the portal URL, and edit security/SMTP/Telegram settings |
| Administrator | Customers (view/edit/impersonate), catalogue without price approval, orders view/edit/assign, payments/wallet/invoices view, subscriptions, servers, deployments, projects, SEO, tickets, docs, leads, supplier costs. No staff, settings, price approval, refunds or exports |
| Billing Staff | Customers/orders view, payments view/export, wallet view/create/approve, invoices view/create/export, subscriptions view |
| Technical Support | Customers/orders/servers/deployments view, tickets view/reply/edit, docs view, leads view (only leads where they are technical owner) |
| Sales Staff | Customers/orders/wallet view, projects view/create, tickets view, leads view/edit (only their own leads) |
| Developer | Projects view/edit, servers/deployments view, deploy, tickets view, leads view |
| SEO Manager | Projects view/edit, SEO, docs view, tickets view |

A new permission is not added to roles that already exist in a database (`customers.impersonate`, `tickets.reply`, `*.export` were such cases): grant it in Roles & access. The role editor shows which sidebar sections a role unlocks.

## 4. Guard rails

- Only a Super Admin can assign the Super Admin role or manage a Super Admin account. At least one active Super Admin must remain; nobody can suspend or demote themselves.
- A non-super manager can only assign a role whose permissions they hold; the requested role is authorised before other validation.
- Tickets: `tickets.view` and membership of the ticket's department (Super Admin sees all). Assignment only to staff who can see that department.
- Prices of add-ons and agreed-price changes need `plans.approve`.
- Supplier cost columns need `supplier_costs.view`; without it they are hidden and a submitted cost is ignored.
- Support roles never get settings, gateway secrets, customer passwords, staff management or wallet adjustments (`SupportWorkflowTest`).
- View as client (`customers.impersonate`): read-only, every write is blocked except Exit and log out; no OTP or email goes to the customer.
- Staff are never deleted (audit trail); suspend instead.

## 5. Business Automation leads

| Role | Leads permission | What they see |
|---|---|---|
| Super Admin, Administrator | `leads.*` | Every lead; assign sales and technical owners; export |
| Sales Staff | `leads.view`, `leads.edit` | Only leads they own (sales, technical or follow-up owner); cannot assign others |
| Technical Support, Developer | `leads.view` | Read-only, only leads assigned to them; no supplier costs |
| Billing Staff, SEO Manager | none | 403 |

`leads.assign` is the dividing line: with it a member sees every lead. Converting a lead also needs `projects.create`. The leads CSV (`leads.export`) excludes phone numbers. Never put passwords or keys in a lead note: notes are stored as written.

## 6. Diagnose a login problem (server access)

```
php artisan staff:status <email>
```

Read-only. Prints the database connection, the current login URL, staff and Super Admin counts, and for the email: account type, role, active, email verified, password change required, authenticator enrolled, plus the next safe step.

| It reports | Next step |
|---|---|
| No account | Create the Super Admin interactively (section 7) or invite the person |
| Customer account | The staff login rejects it and it is never promoted automatically. Only the real owner who knows its password may run `staff:create-super <email> "Name" --promote` |
| Suspended / inactive | Another Super Admin reactivates it in Staff & roles |
| Email unverified | Sign in and enter the emailed code (with `MAIL_MAILER=log` it is in `storage/logs/laravel.log`) |
| Password change required | Sign in, then change it at `/admin/profile` |
| Authenticator not enrolled (Super Admin) | Sign in, enrol at `/admin/security`, store the recovery codes |
| Can sign in, password unknown | "Forgot password?" on the staff login page |

Lost authenticator and recovery codes: another manager with `staff.edit` (a Super Admin for Super Admin accounts) uses "Reset two-factor"; the person enrols again.

## 7. First Super Admin

There are no default credentials. The owner runs, interactively on the server:

```
php artisan staff:create-super <email> "Full Name"
```

The password is typed twice at a hidden prompt (12+ characters with upper and lower case, a number and a symbol) and never shown, logged or put in shell history. The account starts with an unverified email and a forced password change; first sign-in asks for the email code, a new password and an authenticator. The command refuses to run non-interactively, refuses inactive accounts, never overwrites an existing password, and promotes an existing account only with `--promote` plus the retyped email and that account's current password.

## 8. Login URL

A Super Admin changes the slug at `/admin/settings/staff-portal` (live preview, availability check, password + code, audited history). Rules: 4–40 characters, lowercase letters/numbers, single hyphens, at least one letter, not reserved (`admin`, `login`, `staff`, `api`, `wp-admin`, …), no clash with a route. The change is immediate; the old URL returns 404 and staff must be told the new one. It works with `route:cache`.

Recovery from the server:

```
php artisan staff:portal                 # show the current login URL
php artisan staff:portal --set=new-slug  # change it
php artisan staff:portal --reset         # back to the default slug
```

An invalid stored slug falls back to the default, so staff can never be locked out by it. The hidden URL is only an extra layer; passwords, throttling, two-factor, email checks, permissions and sessions protect the portal on their own.

## 9. Never do

- Never create staff with a known or shared password, or copy one from chat history.
- Never disable password checks, OTP or MFA to "fix" a login, or edit password hashes / MFA secrets in the database.
- Never promote an account just because its email matches.
- Never share the staff login slug publicly.

## 10. Environment flags

| Key | Default | Meaning |
|---|---|---|
| `STAFF_PORTAL_DEFAULT_SLUG` | `developerjet` | Fallback slug when none is saved |
| `STAFF_ENFORCE_PRIVILEGED_MFA` | `true` | Super Admins must enrol an authenticator. `false` only in tests and isolated QA |
| `STAFF_SESSION_LIFETIME` / `SESSION_LIFETIME` | `480` | Staff idle timeout in minutes; keep `SESSION_LIFETIME` ≥ the staff value |
