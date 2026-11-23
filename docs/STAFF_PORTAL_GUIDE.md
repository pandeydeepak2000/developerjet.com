# Staff Portal Guide

Last verified: 2026-11-20 (automated tests + headless-Edge browser checks on an isolated QA database). Nothing here
has been verified in production.

## 1. One sign-in for every staff role

All staff — Super Admin, Administrator, Billing Staff, Technical Support, Sales Staff, Developer, SEO Manager and any
custom role — sign in at the same address:

```
https://<your-domain>/staff/<slug>/login        (default slug: developerjet)
```

There is no per-role URL. After sign-in each person lands on the first page their role may open (Dashboard if
permitted, otherwise e.g. Support tickets). The customer sign-in (`/login`) is separate and never opens the admin area.

| Page | Path |
|---|---|
| Sign in | `/staff/<slug>/login` |
| Forgot password | `/staff/<slug>/forgot-password` |
| Reset password (from email) | `/staff/<slug>/reset-password/<token>` |
| Two-factor check | `/staff/<slug>/two-factor` |
| Email ownership check (first sign-in) | `/staff/<slug>/verify-email` |
| Invitation (from email) | `/staff/<slug>/invitation/<token>` |
| My profile (name, password, sessions, sign-in history) | `/admin/profile` |
| My security (authenticator, recovery codes) | `/admin/security` |
| Sign out | button in the admin top bar (`POST /admin/logout`) |

`/admin/login` no longer exists. Visiting `/admin` without a staff session returns **404**, except in a browser that
has completed a staff sign-in before (a `dj_staff_portal` cookie) — that browser is sent to the current login URL.

## 2. How sign-in works

1. Email + password. Only active staff accounts are accepted; the error never says whether the email exists.
   5 failures per email+IP lock that pair for 5 minutes (plus a route limit of 20/minute). Every attempt is audited.
2. If an authenticator is enrolled: a 6-digit code **or** a recovery code (`XXXXX-XXXXX`). 5 failures → 5-minute lock.
3. If the account's email has never been verified (e.g. created by the CLI or directly by a manager): a 6-digit code
   is emailed and must be entered. This proves ownership of the mailbox.
4. A staff session starts on the separate `staff` guard. Customer sessions are not touched.
5. Then, before any admin page opens:
   - **Password change required** (new accounts, reset access) → `/admin/profile` until changed.
   - **Authenticator required** (all Super Admins; everyone when *Settings → Staff security → Require two-factor* is on)
     → `/admin/security` until enrolled.

Sessions are revocable: "Sign out other sessions", password changes/resets, suspension, MFA reset and "Reset access"
all end existing staff sessions immediately (per-account session epoch).

## 3. Changing the login URL (Super Admin only)

Admin → Account → **Staff portal URL** (`/admin/settings/staff-portal`).

- Shows the current URL, a live preview of the new URL and an availability check as you type.
- Rules: 4–40 characters, lowercase letters/numbers, single hyphens, at least one letter, not reserved
  (`admin`, `login`, `staff`, `api`, `wp-admin`, …), not the current slug, no clash with an existing route.
- Requires your password **and** an authenticator code (when enrolled) in the same form. Throttled.
- Saved atomically in the database (`settings.staff_portal.slug`) and audited; the page shows the change history.
- Takes effect immediately. The old URL returns 404 (no redirect, so no loop and nothing to probe).
  People already signed in stay signed in. **Tell your staff the new URL** — it is not emailed automatically.
- Works with `php artisan route:cache`: the route pattern never changes; only the stored value does.

Example: slug `ambition-control` → `https://<your-domain>/staff/ambition-control/login`.

> A hidden URL is only an extra layer. Passwords, throttling, two-factor, email ownership checks, permissions and
> secure sessions protect the portal independently.

### Lock-out recovery (server access required)

```
php artisan staff:portal                 # show the current login URL
php artisan staff:portal --set=new-slug  # change it (same validation; asks to confirm)
php artisan staff:portal --reset         # back to the default slug from config/staff.php
```

Changes made here are audited with source `cli`. An invalid stored value can never lock staff out: the default slug is
used instead.

## 4. First Super Admin

There are no default credentials. On the server, run (interactively — the password is typed at a hidden prompt twice
and never shown, logged or stored in shell history):

```
php artisan staff:create-super ambitionmanagements@gmail.com "Full Name"
```

- New account: created with the Super Admin role, email **unverified**, **password change required**. First sign-in:
  email code → choose a new password → enrol an authenticator → recovery codes are shown once.
- Existing account with that email: refused unless `--promote` is given; then you must retype the email **and** enter
  that account's current password (proof of control, 3 tries). It is never promoted just because the email matches.
  If the owner cannot sign in, they first reset their password through the normal email flow.
- Inactive/suspended accounts are refused (no silent reactivation).

Status on 2026-11-20: the designated email does **not** exist in the local database and no staff accounts exist
locally; the command has not been run (it needs the owner's own password).

## 5. Two-factor and recovery codes

- Authenticator apps (TOTP, RFC 6238). The setup key is shown only during enrolment; after confirmation it is never
  displayed again.
- 10 recovery codes are shown **once** after enrolment; stored only as HMAC-SHA256 hashes; each works once.
  "Generate new recovery codes" (password + code) invalidates the old set.
- Super Admins cannot turn two-factor off. Lost device and codes → another manager with `staff.edit` resets it
  (a Super Admin for Super Admin accounts); the person's sessions end and they enrol again.

## 6. Sensitive actions

Suspending/reactivating staff, resetting access, revoking sessions, resetting someone's two-factor and adding priced
add-ons to subscriptions ask for your password (and code, when enrolled) if you have not confirmed in the last 10
minutes (`/admin/confirm-access`).

## 7. Environment flags

| Key | Default | Meaning |
|---|---|---|
| `STAFF_PORTAL_DEFAULT_SLUG` | `developerjet` | Fallback slug when none is saved |
| `STAFF_ENFORCE_PRIVILEGED_MFA` | `true` | Super Admins must enrol an authenticator. Set to `false` only in automated tests / isolated QA. |
