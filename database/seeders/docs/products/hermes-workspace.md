---
title: Hermes Workspace
excerpt: Use Hermes from your browser, installed with Docker on your own VPS, on a DeveloperJet address or your own domain.
order: 2
---
Hermes Workspace is a browser interface for the Hermes AI agent. It is installed with Docker on a VPS dedicated to you, which is handed over with full root access.

## Who it is for

Anyone who prefers a graphical workspace to a terminal. No server skills are needed. If you want your own address, you need to be able to add DNS records at your domain's DNS provider.

## What is included

- A VPS assigned to your order, sized by your plan.
- Hermes installed with Docker and the Workspace configured.
- Domain verification for a domain you already own (DNS TXT record).
- Access details delivered through your dashboard.

**Not included:** AI model usage (bring your own key), unrestricted root access, custom development or integrations, and domain registration — DeveloperJet does not sell domains.

## Getting started

1. Order a Hermes Workspace plan and wait for the service to become **Active**.
2. Open **Dashboard → Services → your Workspace service**.
3. Add your AI provider key in **AI provider**. See [API provider configuration](/docs/api-provider-configuration).
4. Open the **Service URL** shown in the Overview panel in a modern browser.
5. Sign in to the Workspace using the access details on the service page.

## Using your own domain

You can serve the Workspace from a subdomain you own, for example `ai.example.com`:

1. In the **Domain** panel enter the domain and press **Save domain**.
2. Add the TXT record shown at your DNS provider.
3. Press **Verify domain**.

The full walkthrough, with a diagram of the records, is in [Custom domain setup](/docs/custom-domain-setup).

## Good practice

- Bookmark the Service URL rather than searching for it.
- Sign out of the Workspace on shared computers.
- Keep your provider key's spending limits set at the provider.

## Troubleshooting

- **The Service URL says "Available once your service is active".** Setup is still in progress — see [How orders are delivered](/docs/how-orders-are-delivered).
- **The browser shows a certificate warning on my domain.** HTTPS for a custom domain is configured after verification. Use the default address until our team confirms the domain is live.
- **Replies fail or say no provider is set.** Add or replace the key on the service page.
