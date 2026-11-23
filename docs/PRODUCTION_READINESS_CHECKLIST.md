# Production Readiness Checklist

Status as of 2026-11-20. Nothing has been deployed or verified in production. "Owner" = decision or credential needed
from AmbitionHost. Confirmed business decisions are in `CLAUDE.md`; everything else here is a proposed default.

## Blockers (must be done before going live)

| # | Item | Owner / engineering | Status |
|---|---|---|---|
| 1 | Run `php artisan staff:create-super ambitionmanagements@gmail.com "<name>"` on the server; complete first sign-in (email code, new password, authenticator, store recovery codes) | Owner | Not done |
| 2 | Choose and set the production staff login slug; share it with staff | Owner | Default `developerjet` |
| 3 | Real SMTP credentials; send a test email from Settings; confirm reset/OTP/invitation emails arrive | Owner | Not verified |
| 4 | Razorpay: sandbox end-to-end (checkout, webhook signature, reconciliation) before enabling live keys | Owner + engineering | Fakes only |
| 5 | PTSure: official merchant API documentation and credentials | Owner | Disabled |
| 6 | Approved prices for every sellable plan/period and add-on; tax rates confirmed by a professional | Owner | Pending |
| 7 | Company legal details, GSTIN, invoice prefix/start number | Owner | Pending |
| 8 | Queue worker + scheduler running under a supervisor (`queue:work`, `schedule:run` cron) | Engineering | Local only |
| 9 | `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, trusted host = `APP_URL` | Engineering | Not set |
| 10 | Backups of the database and private attachments disk | Engineering | Not implemented |

## Recommended before launch

- Telegram bot token + chat ID, then *Send test* from Settings.
- Decide billing defaults (invoice 7 days before, reminders 7/3/due/+1, 3 grace days, auto-suspend OFF, cancellation
  approval ON, immediate cancellation OFF, automatic expiry OFF, add-on policy "next renewal").
- Decide SLA targets per priority/department (blank = no SLA promised).
- Grant `tickets.reply` / export permissions to existing roles as wanted (not added automatically).
- Review whether Technical Support should see supplier costs (`servers.view`).
- Real-device (iOS/Android) and screen-reader testing (only headless Edge emulation so far).
- Install an XLSX library only if spreadsheets beyond CSV are required (needs approval).

## Infrastructure (keep gated)

- Live SSH transport, approved install recipes, an isolated test VPS, and owner sign-off before any live deployment.
- Supplier adapters (DesiVPS, EricHost, Contabo, Server Broker) only from official docs + authorised credentials.
- `billing.live_suspension_enabled` stays OFF until a verified adapter exists. Proxmox stays disabled.

## Verified locally (2026-11-20)

`php artisan test`, `npm run build`, `vendor/bin/pint --test`, `composer audit` (see `docs/02_PROJECT_STATUS.md` for the exact
numbers), plus headless-Edge checks of the staff portal (incl. changing the login URL), cancellation, support replies
and responsive layouts at 320/390/671/768/1024/1280/1440 px on an isolated SQLite database.
