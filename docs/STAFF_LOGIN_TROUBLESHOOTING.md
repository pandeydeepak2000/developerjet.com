# Staff login troubleshooting

Last verified: 2026-11-21 (automated tests on an isolated SQLite database; no production verification).
Targeted suite: `StaffOnboardingTest` 4 passed (full onboarding, read-only status command, no-overwrite rule, one login URL for every role). Full suite at that check: **391 passed / 3310 assertions**.

## 1. The login address

All staff roles — Super Admin, Administrator, Sales, Technical Support, Billing, Developer, SEO, custom staff — use the same page:

```
http://127.0.0.1:8000/staff/developerjet/login   (local default slug: developerjet)
```

Related pages use the same slug: `/staff/<slug>/forgot-password`, `/staff/<slug>/reset-password/<token>`, `/staff/<slug>/two-factor`, `/staff/<slug>/verify-email`, `/staff/<slug>/invitation/<token>`. `/admin/login` no longer exists. Visiting `/admin` without a staff session returns **404** (except a browser that completed a staff sign-in before, which is sent to the current login URL via the `dj_staff_portal` hint cookie). The customer sign-in (`/login`) is separate and never opens the admin area.

A Super Admin can change the slug at Admin → Account → Staff portal URL. The old URL then returns a plain 404 (no redirect). If the slug is lost, recover it from the server with `php artisan staff:portal` (see section 6).

## 2. The error message

```
These credentials do not match an active staff account.
```

This message is deliberately generic: it never says whether the email exists, whether the account is a customer account, suspended, or unverified, or whether the password was wrong. Do not try to infer the cause from the message. Use the read-only diagnostics command below.

## 3. Diagnose first (read-only, server access)

```
php artisan staff:status ambitionmanagements@gmail.com
```

`staff:status` never changes data and never prints password hashes, MFA secrets, recovery codes or tokens. It prints the database connection, the current staff login URL, the counts of staff accounts and active Super Admins, and for the given email: account type (customer / staff / legacy owner), staff role, whether it is a Super Admin, whether it is active, whether the email is verified, whether a password change is required, and whether an authenticator is enrolled — plus the safe next step.

| What it reports | Meaning | Next step |
|---|---|---|
| No account exists for the email | Nothing to sign in with; this alone causes the error. | Create the Super Admin interactively (section 4). |
| Customer account | The staff login rejects customer accounts. It is never promoted automatically. | Only if this really is the owner and they know the account's current password: `staff:create-super <email> --promote` (asks for email confirmation + current password). Otherwise the owner resets the password through the normal email flow first. |
| Suspended / inactive | The account exists but is not active. | Another Super Admin reactivates it in Admin → Staff (with password re-confirmation). It is not reactivated automatically. |
| Staff account, active, email unverified | First staff sign-in will email a 6-digit ownership code. | Sign in; enter the emailed code (with `MAIL_MAILER=log` locally the code is written to `storage/logs/laravel.log`). |
| Password change required | The account must choose a new password before any admin page opens. | Sign in, then change the password at `/admin/profile`. |
| Authenticator not enrolled (Super Admin) | MFA is mandatory for Super Admins; enrolment is requested after sign-in. | Sign in, then enrol at `/admin/security` and store the 10 recovery codes shown once. |
| Account can sign in, password unknown | Nothing is wrong with the account. | Use "Forgot password?" on the staff login page; the reset link is emailed. |

## 4. First Super Admin (owner only, interactive)

There are no default credentials. On the server, run interactively — the password is typed at a hidden prompt twice and is never shown, logged, stored in shell history or passed as an argument:

```
php artisan staff:create-super ambitionmanagements@gmail.com "Full Name"
```

Rules enforced by the command (`staff:create-super`):

- It refuses to run non-interactively.
- New account: created with the Super Admin role, email **unverified**, **password change required**. Password must be 12+ characters with upper and lower case, a number and a symbol.
- Existing account: refused without `--promote`. With `--promote` it requires the email to be retyped exactly and the account's current password (proof of control, 3 tries). It is never promoted just because the email matches. Inactive accounts are refused.
- The command never overwrites an existing account's password (tested: hash unchanged when creation is refused).

First sign-in, in order: (1) email + password, (2) emailed 6-digit ownership code if the email was never verified, (3) forced password change, (4) mandatory authenticator enrolment (TOTP), with 10 recovery codes shown once (stored only as HMAC-SHA256 hashes, single use). The next sign-in then requires the authenticator code or a recovery code; MFA cannot be turned off for a Super Admin.

## 5. Sign-in flow and lockouts

1. Email + password. Only active staff accounts are accepted. 5 failures per email+IP lock that pair for 5 minutes (plus a route limit of 20/minute). Every attempt is audited.
2. If an authenticator is enrolled: a 6-digit TOTP code **or** a recovery code (`XXXXX-XXXXX`). 5 failures → 5-minute lock.
3. If the email was never verified: a 6-digit code is emailed and must be entered.
4. A staff session starts on the separate `staff` guard; customer sessions are not touched.
5. Password-change and authenticator gates (section 4) run before any admin page opens.

Sessions are revocable: "Sign out other sessions", password changes/resets, suspension, MFA reset and "Reset access" all end existing staff sessions immediately (per-account session epoch). Sensitive actions (suspend/reactivate, reset access, revoke sessions, reset someone's two-factor, adding priced add-ons) ask for your password (and authenticator code, when enrolled) if you have not confirmed recently — the window is `staff.reauth_minutes` in `config/staff.php` (default 480 minutes = 8 hours; it is set at sign-in, so you normally confirm once per day, not per page).

**Staying signed in:** a staff session lasts as long as you are active. Every authorised admin request renews the inactivity window, so normal navigation between admin pages never asks you to sign in again. An idle session expires after the configured lifetime — `STAFF_SESSION_LIFETIME` minutes (default 480), with the underlying `SESSION_LIFETIME` raised to match (480). To shorten or lengthen it, set both values in `.env` (e.g. `STAFF_SESSION_LIFETIME=120` + `SESSION_LIFETIME=120` for 2 hours); `SESSION_LIFETIME` must always be at least as long as `STAFF_SESSION_LIFETIME`. Logout, session revocation, forced password change and mandatory MFA always apply regardless of lifetime.

## 6. Login URL recovery

```
php artisan staff:portal                 # show the current login URL
php artisan staff:portal --set=new-slug  # change it (same validation; asks to confirm)
php artisan staff:portal --reset         # back to the default slug from config/staff.php
```

Changes are audited with source `cli`. An invalid stored slug can never lock staff out: the default slug is used instead. Slug rules: 4–40 characters, lowercase letters/numbers, single hyphens, at least one letter, not reserved (`admin`, `login`, `staff`, `api`, `wp-admin`, …), not the current slug, no clash with an existing route.

## 7. What never to do

- Never hardcode a password, copy one from conversation history, or create an account with a known shared password.
- Never disable password verification, OTP or MFA to "fix" a login.
- Never promote an unverified existing account, and never promote by email match alone.
- Never edit the database directly to set a password hash or clear an MFA secret; use the interactive command and the normal reset / MFA-reset flows.
- Never share the staff login slug publicly; it is an extra layer only — passwords, throttling, two-factor, email ownership checks, permissions and secure sessions protect the portal independently.

## 8. Current status (2026-11-21)

- The full Super Admin onboarding flow is verified end-to-end on a disposable test database with isolated test accounts only (`StaffOnboardingTest`): create → wrong-password rejection → email code → forced password change → MFA enrolment → dashboard opens → next sign-in requires the authenticator code.
- The designated owner email is `ambitionmanagements@gmail.com`. The earlier verified finding (2026-11-20) was that no Super Admin account existed in the local database and no staff accounts existed locally; the command has not been run for the real account because it needs the owner's own password.
- **Live check blocked in this session:** `php artisan staff:status ambitionmanagements@gmail.com` could not reach the local database — MySQL refused the connection on `127.0.0.1:3306` (`SQLSTATE[HY000] [2002]`, database `developerjet_local`). Start MySQL/XAMPP and re-run `php artisan staff:status ambitionmanagements@gmail.com` before concluding anything about the real account. Do not create, reset or modify any account until that check succeeds.
