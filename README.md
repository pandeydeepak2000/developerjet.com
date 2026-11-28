# DeveloperJet

DeveloperJet (powered by AmbitionHost) sells self-hosted AI and developer hosting (Hermes CLI, Hermes Workspace, n8n, OpenClaw, AI Complete Bundle, Claude Code, Docker VPS, Node.js Hosting), AI All-Access API credit plans and quote-based digital services. It is one Laravel 12 app with three parts:

- **Website**: product pages, pricing, cart and checkout (Razorpay or wallet), help centre at `/docs`.
- **Customer dashboard** (`/dashboard`): services with a server panel (live usage, root login, password change, reboot, reinstall), orders, invoices, wallet, subscriptions, tickets, quotes and projects.
- **Admin panel** (`/staff/{slug}/login` → `/admin`): orders with server setup and live install over SSH, customers, servers, billing, support, products, sales and settings, with role-based permissions.

## Run locally

Requirements: PHP 8.2+, Composer, Node.js/npm, MySQL (tests use SQLite in memory).

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000`. Local mail uses the `log` mailer: verification codes appear in `storage/logs/laravel.log`. The first staff account is created with `php artisan staff:create-super <email> "Name"` (interactive).

## Tests and assets

```powershell
php artisan test
vendor/bin/pint --test
npm run build
```

`public/build` is committed, because the production server has no npm.

## Documentation

| File | For | What it covers |
|---|---|---|
| `CLAUDE.md` | AI sessions | Rules and owner decisions that must not be broken |
| `DeveloperJet_SRS_v0.1.md` / `.pdf` | Everyone | Original requirements (later owner decisions win) |
| `docs/01_DEVELOPER_GUIDE.md` | Developers | Stack, rules, folder map, core patterns, change guide, decisions |
| `docs/02_PROJECT_STATUS.md` | Everyone | What is built, what was verified, what is pending |
| `docs/03_COMPLETE_WORKFLOW.md` | Developers, owner | Customer to admin workflow end to end (PDF in `docs/generated/`) |
| `docs/04_STAFF_ACCESS.md` | Owner, admins | Staff login, roles, permissions, login troubleshooting |
| `docs/05_CPANEL_DEPLOYMENT.md` | Owner / DevOps | First deploy, `.env`, cron, SMTP, updates, health checks, go-live checklist |
| `docs/06_ADMIN_GUIDE.md` | Owner, staff | Hinglish admin panel guide with screenshots (PDF in `docs/generated/`) |
| `database/seeders/docs/` | Customers | Help-centre articles shown at `/docs` |

After editing `docs/*.md`, rebuild the PDFs: `php scripts/build_diagrams.php && php scripts/build_docs_pdf.php`.
