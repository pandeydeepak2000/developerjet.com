---
title: Node.js Hosting
excerpt: A VPS of your own with Node.js 22 LTS, PM2, Git and Caddy installed, handed over with full root access.
order: 6
---
Node.js Hosting gives you a VPS dedicated to you with **Node.js 22 LTS**, **npm**, the **PM2** process manager, **Git** and the **Caddy** web server already installed. A small starter app is running when you receive the server; you replace it with your own app.

Plans, sizes and prices are on the [Node.js Hosting page](/nodejs-hosting).

## Who it is for

Developers shipping Next.js, Express, NestJS, Nuxt or any other Node.js app or API who are comfortable with Git, npm and basic Linux commands over SSH. After handover the server is yours to look after — see [Self-hosted: who does what](/docs/managed-vs-unmanaged).

## What is included

- A VPS dedicated to you, sized by your plan.
- Node.js 22 LTS, npm, PM2 (starts your app again after a crash or reboot), Git and Caddy installed and checked.
- A starter app in `/var/www/app`, listening on `127.0.0.1:3000`.
- Full root access, with your login, live usage and a Reboot button in the service panel.

**Not included:** writing, debugging or deploying your code for you, server updates after handover, and backups (contact our sales team for a backup plan).

## Deploy your app

Connect over SSH with the login from **Login & SSH** (see [Service access](/docs/service-access)), then:

```bash
cd /var/www/app && git clone https://github.com/you/your-app.git .
npm ci && npm run build --if-present
pm2 delete app; PORT=3000 pm2 start npm --name app -- start && pm2 save
```

The service Overview shows the same commands with **Copy** buttons. If the folder is not empty, remove the starter files first (`rm -rf /var/www/app/*`).

## Connect your domain with HTTPS

1. At your DNS provider, point an `A` record for your domain (for example `app.example.com`) to your server's IP.
2. On the server, edit `/etc/caddy/Caddyfile` so it contains:

```
app.example.com {
    reverse_proxy 127.0.0.1:3000
}
```

3. Run `systemctl reload caddy`. Caddy gets and renews the HTTPS certificate automatically.

## Manage tab

- **Restart** — restarts your PM2 apps.
- **Reboot** — restarts the whole server; PM2 starts your app again.
- **Reinstall fresh** — deletes all PM2 apps and `/var/www/app`, then puts the starter app back. Node.js, PM2 and Caddy stay installed. Type `REINSTALL` to confirm. This cannot be undone.

## Troubleshooting

- **The app is not running.** Run `pm2 ls` and `pm2 logs app` to see why.
- **The domain shows a certificate error.** Check the `A` record points to your server IP and that ports 80 and 443 are open, then `systemctl reload caddy`.
- **Still stuck?** Open a **Technical Support** ticket and select the service.
