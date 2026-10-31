# DeveloperJet

A Laravel 12 and Blade marketing site with a standalone homepage and dedicated Hermes and n8n product pages. The responsive interface uses Tailwind CSS, a custom neumorphic visual system, and minimal vanilla JavaScript for the mobile menu and monthly/yearly pricing switch.

## Run locally

Requirements: PHP 8.2+, Composer, and Node.js/npm.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Pages

- `/` — standalone DeveloperJet homepage and product links
- `/hermes` — Hermes CLI and Workspace overview
- `/hermes-cli` — Hermes CLI installed on a VPS for SSH or PuTTY access
- `/hermes-workspace` — Hermes Workspace installed with Docker on a VPS
- `/n8n` — n8n plans installed on a managed VPS
- `/login` and `/register` — visual-only account screens that direct users to sales on WhatsApp
- `/forgot-password` — visual-only password help screen; no reset email is sent
- `/about` — company story and principles
- `/contact` — phone and WhatsApp contact options
- `/terms` — terms of service
- `/privacy` — privacy policy
- `/refund` — refund policy

Prices shown on the product pages are illustrative INR amounts; confirm final pricing, plan inclusions, VPS configuration, and applicable taxes with sales at **+91 76888 55600**. Activated digital services and provisioned VPS plans are non-refundable.

## Tests and production assets

```powershell
php artisan test
npm run build
```
