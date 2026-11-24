---
title: n8n Hosting (self-hosted)
excerpt: Self-hosted n8n on your own VPS — what is included, your responsibilities and how to get started.
order: 3
---
n8n is a workflow-automation tool. DeveloperJet provides a VPS dedicated to you, installs n8n on it and hands the server over with **full root access**. It is your own self-hosted n8n instance; you build and run the workflows.

> **Important:** n8n is used under its own licence (the Sustainable Use License), with you as the owner of the instance. If a plan shows *Contact Sales* instead of *Add to cart*, it is not yet open for online purchase.

## Who it is for

Teams and individuals who want n8n installed for them on a server they control. Familiarity with the apps you want to connect helps.

## What is included

- A VPS dedicated to you, sized by your plan.
- n8n installed and checked, with HTTPS on a DeveloperJet address or your own domain.
- Full SSH/root access to the server.

**Not included:** designing or building your workflows, paid third-party services your workflows connect to, n8n updates and server administration after handover, and **backups** (contact our sales team for a backup plan).

## Getting started

1. Order an n8n plan and wait for the service to become **Active**.
2. Open **Dashboard → Services → your n8n service** and use the **Service URL**.
3. Create your n8n owner account on first visit, using a strong unique password.
4. Build your first workflow and test it with a manual trigger before enabling it.

## Credentials in n8n

Store third-party credentials in n8n's own **Credentials** section, not inside workflow nodes as plain text. Never share them in a support ticket.

## Custom domain

n8n supports connecting a domain you already own. See [Custom domain setup](/docs/custom-domain-setup). Update any webhook URLs in other services after switching to the new address.

## Troubleshooting

- **A webhook is not firing.** Check the workflow is **active**, the webhook URL in the other service matches exactly, and you are using the production (not test) URL.
- **Workflows are slow or queue up.** Your server may need more resources. Open a ticket to discuss a larger plan.
- **I cannot reach the editor.** Confirm the service is Active and try the default address.
