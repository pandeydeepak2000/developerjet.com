# CLAUDE.md — instructions for AI sessions

Read first: `PROJECT_CONTEXT.md`, `DeveloperJet_SRS_v0.1.md`, `docs/01_DEVELOPER_GUIDE.md`, `docs/02_PROJECT_STATUS.md`, `docs/03_COMPLETE_WORKFLOW.md`.

## Rules
- WHMCS must never be integrated.
- Do not implement live payments or VPS provisioning without explicit approval.
- Never invent prices or resources; keep `pending`/`illustrative` statuses until the owner approves.
- Do not push to GitHub, deploy to cPanel, or touch production. Ask before destructive/irreversible actions
  (dropping tables, `migrate:fresh`, deleting data).
- Never reveal `.env` contents. Do not mark a feature complete without passing tests.
- Preserve uncommitted SRS files (`DeveloperJet_SRS_v0.1.md/.pdf`).

## Commands
- Serve: `php artisan serve --host=127.0.0.1 --port=8000`
- Assets: `npm run dev` (hot) or `npm run build`
- Tests: `php artisan test` (SQLite in-memory; safe)
- Migrate local DB: `php artisan migrate` then `php artisan db:seed --class=CatalogSeeder`
- Format: `vendor/bin/pint`

## Conventions
- Money is stored as integer paise; format with `App\Support\Money::inr()`.
- New catalogue values go through the DB/seeder, never hard-coded in Blade.
- Keep existing marketing page structure (section counts are asserted in `MarketingPagesTest`).
- After each milestone update `docs/02_PROJECT_STATUS.md` with what is actually verified.

## Confirmed owner decisions (v0.2)
- Hybrid KVM infrastructure: initial on-demand manual VPS purchase and manual assignment, then auto-install; future provider APIs and dedicated Proxmox KVM (OVH etc.).
- Initial suppliers: DesiVPS, EricHost, Contabo Core/Performance Plus, Server Broker. Never assume a supplier API; verify with official docs and authorised credentials.
- Separate managed and unmanaged access models. No WHMCS. Never store plaintext root passwords.
- Live install commands, supplier adapters and Proxmox stay gated until explicitly approved. Do not claim full automation is live.
- Docs: rebuild PDFs with `php scripts/build_diagrams.php && php scripts/build_docs_pdf.php` after editing `docs/*.md`.
- Shell tip: very large heredocs can fail in this environment; create big files with the editor tools.


## v0.3 notes
- Mobile drawer lives OUTSIDE `<header>` (header uses backdrop-filter; a fixed child would collapse). Keep it that way (`MobileNavigationTest`).
- Billing: never auto-charge; never call a supplier API to suspend/reactivate unless a verified adapter exists AND `billing.live_suspension_enabled` is on (owner approval). Renewals use the subscription's agreed price snapshot.
- Run `php artisan schedule:work` + `php artisan queue:work` locally to exercise renewals/reminders.
- Browser QA uses an isolated SQLite DB served by `php -S` (not `artisan serve`, which drops env overrides and would read `.env`/MySQL).
- In this environment the Bash tool can collapse backslashes: edit PHP namespaces with the editor tools.

## v0.5 notes (Business Automation)
- Business Automation is implemented and tested (391 passed / 3310 assertions): /business-automation, 3 draft quote-only plans, requirement form, queued ack/sales/Telegram notifications (fakes only), 10-status quotation lifecycle, lead ownership permissions. See docs/03_COMPLETE_WORKFLOW.md.
- Super Admin onboarding is verified on a disposable test database only. Do not create the real Super Admin account without the owner running staff:create-super interactively; check first with the read-only php artisan staff:status command (live MySQL check was blocked on 2026-11-21: connection refused on 127.0.0.1:3306).
- Browser QA at 320-1440px was not re-run in this session.

## v0.4 notes (completion sprint)
- Staff sign in at `/staff/{slug}/login` (DB setting `staff_portal.slug`, default `developerjet`) on the separate `staff` guard. `/admin/login` no longer exists; `/admin` is 404 for outsiders. Recovery: `php artisan staff:portal --show|--set=|--reset`.
- Super Admin: only via `php artisan staff:create-super <email> <name>` run interactively by the owner. Never create staff with known passwords, never promote an account by email match.
- Tests: `actingAs($staffUser)` signs in on both guards (see `tests/TestCase.php`); use `actingAs($user, 'web')` for customer-only isolation. `STAFF_ENFORCE_PRIVILEGED_MFA=false` in phpunit only.
- Sensitive staff routes use `staff.reauth`; the admin group uses the named limiter `throttle:staff-area` (do not go back to a plain `throttle:N,M` on the group).
- No proration/refund engine, no automatic data deletion: ended services open a `retention_review` staff task.
- Exports are CSV (`App\Services\ExportService`) — keep the column allow-lists free of secrets/PII.
- Browser QA: `C:\tmp\djet-qa\sprint-setup.sh` builds `qa-sprint.sqlite`; `serve-sprint.sh` serves it on :8001; run scripts with `MSYS_NO_PATHCONV=1` in Git Bash.

## v0.6 notes (live install)
- Owner approved live SSH install (2026-11-25): staff enter IP + root password on the order page → Deploy → install → auto Active.
- Install steps are reviewed, idempotent scripts in `resources/provisioning/*.sh`, referenced as `@path` in `config/provisioning.php`. Only add/approve a script after running it on a real test VPS; keep unapproved steps `null` (Deploy stops safely there).
- Host keys use trust-on-first-use (pinned on first connect). Never log the server password (SshWorker redacts it).
- Production needs `composer install` (phpseclib), `PROVISIONING_LIVE_ENABLED=true`, `PROVISIONING_WORKER=ssh`, and outbound port 22 from the host.
