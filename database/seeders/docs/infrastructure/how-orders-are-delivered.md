---
title: How Orders Are Delivered
excerpt: What happens between payment and access, why delivery is not instant, and how to follow progress.
order: 1
---
After checkout, our team prepares a dedicated environment for your order, then the software is installed and checked. Preparing the environment is done by people, so **delivery is not instant**.

[diagram: order-flow]

## Step by step

1. **You pay.** The order is created with status *Pending payment*.
2. **We verify the payment on our servers.** The order moves to *Paid*. Your invoice is issued.
3. **Awaiting VPS assignment.** Our team prepares a server that matches your plan and access model.
4. **Provisioning.** The application (or Docker, for Docker VPS) is installed and checked.
5. **Active.** Access instructions appear on your service page. We never email passwords.

## Following progress

- **Dashboard → Orders → your order** shows the status and a timeline of each change.
- **Dashboard → Services → your service** shows **Server status** (*Awaiting VPS assignment*, *Server assigned — setup pending* or *Active — setup complete*) and **Deployment progress**.
- You receive email updates at key steps.

## What you can do while you wait

- Add your AI provider key on the service page. See [API provider configuration](/docs/api-provider-configuration).
- For supported apps, save your domain and add the TXT record so it is ready. See [Custom Domain Setup & HTTPS](/docs/custom-domain-setup).
- Prepare an SSH key if your product uses SSH. See [SSH and security](/docs/ssh-and-security).

## If something fails

If a setup step fails, your service stays inactive, our team is notified and the step is retried safely. Nothing is half-delivered to you. The service page shows that our team is handling it.

> **Note:** Deployment progress may show an *internal readiness check*. This is a pre-installation check, not the installation itself.

## Troubleshooting

- **My order has been awaiting VPS assignment for a long time.** Open a **Technical Support** ticket and select the service; include the order number.
- **The order is Paid but no service appears.** Refresh the Services page; if it is still missing after a few minutes, open a ticket.
