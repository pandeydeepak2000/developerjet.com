---
title: How Orders Are Delivered
excerpt: What happens between payment and access, why delivery is not instant, and how to follow progress.
order: 1
---
After checkout, our team adds a server dedicated to your order, then your software is installed and checked automatically. Adding the server is done by people, so **delivery is not instant**.

[diagram: order-flow]

## Step by step

1. **You pay.** The order is created with status *Pending payment*.
2. **We verify the payment on our servers.** The order moves to *Paid*. Your invoice is issued.
3. **Awaiting VPS assignment.** Our team adds a server that matches your plan.
4. **Provisioning.** Your software is installed and checked on that server.
5. **Active.** We email you that the service is ready. Your login details are in the service panel. We never email passwords.

## Following progress

- **Dashboard → Orders → your order** shows the status and a timeline of each change.
- **Dashboard → My services → your service** shows the setup progress in six stages, from *Order confirmed* to *Ready to use*. The page updates by itself while you watch.
- You receive email updates at key steps.

## What you can do while you wait

- For web apps, save your own domain and add the TXT record so it is ready. See [Custom Domain Setup & HTTPS](/docs/custom-domain-setup). You can also keep the DeveloperJet address.
- Have your AI provider key ready. After delivery the service Overview shows the exact command to add it. See [API provider configuration](/docs/api-provider-configuration).
- Read [Service access](/docs/service-access) to see how you will log in.

## If something fails

If a setup step fails, your service stays inactive, our team is notified and the step is retried safely. Nothing is half-delivered to you. The service page shows that our team is handling it.

## Troubleshooting

- **My order has been awaiting VPS assignment for a long time.** Open a **Technical Support** ticket and select the service; include the order number.
- **The order is Paid but no service appears.** Refresh the My services page; if it is still missing after a few minutes, open a ticket.
