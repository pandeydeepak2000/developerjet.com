---
title: Service Access
excerpt: How you receive access to your service, browser and SSH access, and why passwords are never emailed.
order: 2
legacy: server-access
---
When your service becomes **Active**, its access details appear on the service page in your dashboard.

## Where to find your access details

1. Go to **Dashboard → Services**.
2. Open the service.
3. Check:
   - **Overview → Service URL** — the browser address for web-based products.
   - **Server → IP address** — shown once the service is active.
   - **Server → Access** — whether the service is managed (root restricted) or unmanaged (SSH/root).
   - The **Help** panel and any access instructions provided by our team.

## How each product is accessed

| Product | How you connect |
| --- | --- |
| Hermes CLI | SSH or PuTTY |
| Hermes Workspace | Web browser (default address or your verified domain) |
| n8n | Web browser |
| OpenClaw | Secured web gateway |
| AI Complete Bundle | Browser links for Workspace and n8n, SSH for Hermes CLI |
| Claude Code | SSH |
| Docker VPS | SSH with root access |

## First-time access

For your security:

- Root and server passwords are **never emailed or displayed** in the dashboard.
- First-time access is arranged securely by our team after your service is ready — typically by adding your **SSH public key** or by a one-time secure handover.
- DeveloperJet never stores plaintext root passwords.

> **Tip:** The fastest way to get SSH access is to send us your **public** key (the file ending in `.pub`) in a ticket for that service. Never send a private key. See [SSH and security](/docs/ssh-and-security).

## Server controls

The **Controls** panel lists actions such as power, console, snapshots and backups. A control is enabled only when it has been verified for your server; otherwise it shows **Unavailable** with a reason. For anything unavailable, open a ticket and our team will help.

## Troubleshooting

- **IP address says "Shown once your service is active".** Setup is not finished. See [How orders are delivered](/docs/how-orders-are-delivered).
- **I lost access.** Open a Technical Support ticket for the service. We will verify your identity through your account before restoring access.
