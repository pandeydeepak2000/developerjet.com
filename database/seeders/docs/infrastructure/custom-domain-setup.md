---
title: Custom Domain Setup & HTTPS
excerpt: Connect a domain you already own to Hermes Workspace, n8n, OpenClaw or the AI Complete Bundle.
order: 4
legacy: domains-and-ssl
---
Supported applications can be served from a domain you already own instead of the default DeveloperJet address.

> **Important:** DeveloperJet does **not** sell, register, renew or transfer domains. You keep your domain with your current registrar and DNS provider; you only add DNS records there.

## Which services support a custom domain

| Service | Custom domain |
| --- | --- |
| Hermes Workspace | Yes |
| n8n | Yes |
| OpenClaw | Yes |
| AI Complete Bundle | Yes |
| Hermes CLI, Claude Code | Not needed (SSH access) |
| Docker VPS | You configure it yourself on your server |

## Before you start

- You own the domain and can edit its DNS records.
- We recommend a **subdomain** such as `ai.example.com` or `n8n.example.com`, so your main website is unaffected.

## How it works

[diagram: domain-dns]

## Step 1 — Save the domain

1. Go to **Dashboard → My services**, open the service and select the **Manage** tab.
2. In the **Domain** panel, enter the domain in **Your own domain**, for example `ai.example.com`.
3. Press **Save domain**. The panel now shows **Status: pending** and the TXT record to create.

## Step 2 — Add the verification TXT record

At your DNS provider, create a TXT record using the exact values shown on your service page:

| Field | Value |
| --- | --- |
| Type | `TXT` |
| Name / Host | `_developerjet-verify.ai.example.com` (some providers want only `_developerjet-verify.ai`) |
| Value / Content | the token shown, beginning `djet-verify-` |
| TTL | Default or 300 seconds |

> **Tip:** If your DNS provider adds your domain automatically, enter only the part before your main domain. Entering the full name can create `_developerjet-verify.ai.example.com.example.com` by mistake.

## Step 3 — Verify

Press **Verify domain**. If the record is found, the status changes to **verified**. If not, wait a few minutes and try again — DNS changes can take time to propagate.

You can check the record yourself:

```bash
# macOS / Linux
dig +short TXT _developerjet-verify.ai.example.com

# Windows PowerShell
Resolve-DnsName -Type TXT _developerjet-verify.ai.example.com
```

## Step 4 — Point the domain to your service

Verification proves ownership; it does not route traffic. After verification, our team configures the application and HTTPS for your domain and confirms the record you need to point the domain at your server (normally an `A` record to the **IP address** shown on your service page). Until then, keep using the default address.

## HTTPS for your domain

HTTPS certificates are configured by our team for a verified custom domain on a supported application. There is nothing to buy or upload.

1. Finish steps 1–3 so the domain shows **verified**.
2. Wait for our confirmation of the record to point at your server, and add it at your DNS provider.
3. Our team enables HTTPS for the domain and confirms in your ticket or dashboard when it is ready.
4. Open `https://` plus your domain and check the padlock in the browser.

> **Note:** HTTPS for custom domains is not automated yet. Until our team confirms it, a browser may show a certificate warning for the new address — keep using the default address in the meantime.

## Changing or removing a domain

Saving a different domain resets the status to **pending**; repeat steps 2–4. You can delete the old TXT record once the new domain is verified. To go back to the default address, open a ticket.

## Troubleshooting

- **"We could not find the TXT record yet."** Check the record name for a duplicated domain, check the value has no extra spaces or quotes, and wait for propagation.
- **"The custom domain field format is invalid."** Enter only the host name — no `https://`, no path and no trailing slash.
- **Certificate warning in the browser.** HTTPS is configured after verification and pointing. Wait for our confirmation.
- **My domain uses a proxy/CDN.** Tell us in a ticket; proxied records may need extra configuration.
