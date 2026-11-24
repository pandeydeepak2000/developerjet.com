---
title: Hermes CLI
excerpt: Run the Hermes AI agent from a terminal on your own VPS, connected over SSH or PuTTY with root access.
order: 1
---
Hermes CLI is the Hermes AI agent installed on a VPS assigned to you and used from a terminal. DeveloperJet manages the server; you use Hermes.

## Who it is for

You are comfortable opening an SSH or PuTTY session and working in a terminal. You do not need to administer the server.

## What is included

- A VPS assigned to your order, sized by your plan.
- Hermes CLI installed and configured.
- A security baseline applied during setup.
- Connection details delivered through your dashboard.

**Not included:** AI model usage (you bring your own provider key), backups (ask our sales team for a backup plan) and a browser interface — for that, see [Hermes Workspace](/docs/hermes-workspace).

## Getting started

1. Order a Hermes CLI plan. See [Cart and checkout](/docs/cart-and-checkout).
2. Wait for the order to move from **Awaiting VPS assignment** to **Active**.
3. Open **Dashboard → Services → your Hermes CLI service**.
4. In **AI provider**, choose your provider and paste your API key, then press **Save securely**. See [API provider configuration](/docs/api-provider-configuration).
5. Follow the access instructions on the service page to connect. See [Service access](/docs/service-access).

## Connecting

From macOS, Linux or Windows Terminal/PowerShell:

```bash
ssh your-username@your-server-ip
```

From Windows with PuTTY, enter the server IP as the **Host Name**, port **22**, and open the session. The username and how to authenticate are given in your access instructions.

> **Tip:** Use an SSH key instead of a password where your access instructions allow it. See [SSH and security](/docs/ssh-and-security).

## Using Hermes

Once connected, start Hermes from the shell as described in your access instructions. Hermes uses the AI provider key saved on your service page; if you skipped that step, the service shows **Configuration incomplete** and Hermes cannot answer requests until a key is added.

> **Warning:** Do not paste API keys into a support ticket or chat. Add or replace them only on the service page.

## Troubleshooting

- **"Connection refused" or timeout.** Check the IP address and that the service is **Active**. Your network may block outbound port 22 — try another network.
- **"Permission denied (publickey)".** The server expects a key that your computer is not offering. See [SSH and security](/docs/ssh-and-security).
- **Hermes says no provider is configured.** Add your key in **AI provider** on the service page.
- **Still stuck?** Open a **Technical Support** ticket and select the service.
