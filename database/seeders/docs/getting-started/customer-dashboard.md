---
title: Customer Dashboard
excerpt: A tour of every dashboard section — services, orders, billing, AI keys and support — and of the server panel for each service.
order: 5
---
The customer dashboard at `/dashboard` is where you manage everything after you sign in. It is available once your email is verified. On a phone, open the menu with the button at the top left.

## Sections

The menu on the left has three groups.

| Section | What you can do |
| --- | --- |
| **Overview** | Your services, quick actions, upcoming renewals and a getting-started checklist. |
| **My services** | Open the server panel of each service (see below). |
| **Orders** | See every order and its status, pay an unpaid order, cancel an unpaid order. |
| **Projects** | Follow professional-service projects: milestones, messages, files and reports. |
| **Quotes** | Track quote requests for professional services and accept or decline a quote. |
| **Automation requests** | Follow your Business Automation requests and quotations. |
| **AI API keys** | Keep your own AI provider keys in one place (only the last 4 characters are shown). |
| **Subscriptions & renewals** | See renewal dates, pay a renewal, or ask to cancel at the end of the period. |
| **Invoices** | Download PDF invoices for verified payments. |
| **Payments** | See every payment attempt and its result. |
| **Wallet** | See your balance and history, and request a top-up. |
| **Support tickets** | Open and follow support tickets. |
| **Profile & security** | Name, phone, password and the billing details printed on your invoices. |

The sun/moon button in the top bar switches between light and dark mode. Your choice is remembered in this browser.

## Order statuses explained

| Status | Meaning |
| --- | --- |
| Pending payment | The order is created but not paid yet. You can pay or cancel it. |
| Paid | Payment has been verified on our servers. |
| Awaiting VPS assignment | Our team is adding a server to your order. |
| Provisioning | Your software is being installed and checked. |
| Active | Your service is ready. Login details are in the service panel. |
| Failed | Something did not complete. Our team is told and retries it. |
| Cancelled | The order was cancelled before payment. |
| Refund pending / Refunded | A refund is being processed or has been completed. |

## The service panel

Open **My services** and select a service. The top shows its status, plan, renewal date, an **Open** button for web apps and a **Reboot** button. Below are four tabs.

- **Overview** — live server usage (CPU, memory, disk, network, uptime; refreshed every minute while the page is open), server details (IP, operating system, size, installed software, SSH), the steps to add your AI key or get started, health checks when recorded, and *Good to know*.
- **Login & SSH** — your server login: IP, username and root password (press **Show** or **Copy**), how to connect from Terminal or PuTTY, and **Root password** to set your own new password or generate one.
- **Manage** — reboot the server, restart the app or reset the app login (where the product supports it), request an upgrade, connect your own domain (web apps), and **Reinstall fresh**.
- **Billing** — plan, billing period, renewal date and the latest invoices for this service.

Until the service is active, the Overview shows the setup progress instead of usage and login details.

> **Note:** Backups are not included. Contact our sales team if you want a backup plan.

## Related

- [Service access](/docs/service-access)
- [Invoices and wallet](/docs/invoices-and-wallet)
- [Support tickets](/docs/support-tickets)
