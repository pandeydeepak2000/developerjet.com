---
title: Hermes CLI
excerpt: Run the Hermes AI agent from a terminal on your own VPS, connected over SSH or PuTTY with root access.
order: 1
---
Hermes CLI is the Hermes AI agent installed on a VPS dedicated to you and used from a terminal. We install it with the official installer and hand the server over with full root access.

## Who it is for

You are comfortable opening an SSH or PuTTY session and working in a terminal. After handover the server is yours to look after — see [Self-hosted: who does what](/docs/managed-vs-unmanaged).

## What is included

- A VPS assigned to your order, sized by your plan.
- Hermes CLI installed and checked.
- A security baseline applied during setup.
- Your root login, live usage and a Reboot button in the service panel.

**Not included:** AI model usage (you bring your own provider key), backups (ask our sales team for a backup plan) and a browser interface — for that, see [Hermes Workspace](/docs/hermes-workspace).

## Getting started

1. Order a Hermes CLI plan. See [Cart and checkout](/docs/cart-and-checkout).
2. Wait for the order to move from **Awaiting VPS assignment** to **Active**.
3. Open **Dashboard → My services → your Hermes CLI service**.
4. On the **Login & SSH** tab, copy your login and connect. See [Service access](/docs/service-access).
5. Add your AI key with the command shown under **Add your AI API key** on the Overview. See [API provider configuration](/docs/api-provider-configuration).

## Connecting

From macOS, Linux or Windows Terminal/PowerShell:

```bash
ssh your-username@your-server-ip
```

From Windows with PuTTY, enter the server IP as the **Host Name**, port **22**, and open the session. Log in with the username and password from **Login & SSH**.

> **Tip:** An SSH key is safer than a password. See [SSH and security](/docs/ssh-and-security).

## Using Hermes

Once connected, save your key and start Hermes:

```bash
hermes config set OPENROUTER_API_KEY your-key-here
hermes model    # optional: pick another provider or model
hermes
```

> **Warning:** Do not paste API keys into a support ticket or chat. Your key stays on your server.

## Troubleshooting

- **"Connection refused" or timeout.** Check the IP address and that the service is **Active**. Your network may block outbound port 22 — try another network.
- **"Permission denied (publickey)".** The server expects a key that your computer is not offering. See [SSH and security](/docs/ssh-and-security).
- **Hermes says no provider is configured.** Run `hermes config set OPENROUTER_API_KEY your-key-here` again, or `hermes model`.
- **Hermes stopped responding.** Use **Reboot** in the service panel, or **Reinstall fresh** on the Manage tab to start again (this deletes Hermes and its data).
- **Still stuck?** Open a **Technical Support** ticket and select the service.
