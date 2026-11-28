---
title: n8n Hosting (self-hosted)
excerpt: Self-hosted n8n on your own VPS — what is included, your responsibilities and how to get started.
order: 3
---
n8n is a workflow-automation tool. DeveloperJet provides a VPS dedicated to you, installs n8n on it and hands the server over with **full root access**. It is your own self-hosted n8n instance; you build and run the workflows.

> **Important:** n8n is used under its own licence (the Sustainable Use License), with you as the owner of the instance.

## Who it is for

Teams and individuals who want n8n installed for them on a server they control. Familiarity with the apps you want to connect helps.

## What is included

- A VPS dedicated to you, sized by your plan.
- n8n installed and checked, with HTTPS on a DeveloperJet address or your own domain.
- Full SSH/root access to the server, with your login, live usage and a Reboot button in the service panel.

**Not included:** designing or building your workflows, paid third-party services your workflows connect to, n8n updates and server administration after handover, and **backups** (contact our sales team for a backup plan).

## Getting started

1. Order an n8n plan and wait for the service to become **Active**.
2. Open **Dashboard → My services → your n8n service** and press **Open n8n**.
3. Create your n8n owner account straight away — the first account created becomes the owner. Use a strong unique password.
4. Add AI or app keys under **Credentials → Add credential** in n8n.
5. Build your first workflow and test it with a manual trigger before enabling it.

## Manage tab

- **Restart n8n** — fixes most hangs; n8n is unavailable for a few seconds.
- **Forgot n8n password? Reset login** — you create the owner account again; your workflows and credentials are kept.
- **Reinstall fresh** — deletes n8n and all its data, then installs it again. Type `REINSTALL` to confirm. This cannot be undone.

## Credentials in n8n

Store third-party credentials in n8n's own **Credentials** section, not inside workflow nodes as plain text. Never share them in a support ticket.

## Custom domain

n8n supports connecting a domain you already own. See [Custom domain setup](/docs/custom-domain-setup). Update any webhook URLs in other services after switching to the new address.

## Troubleshooting

- **A webhook is not firing.** Check the workflow is **active**, the webhook URL in the other service matches exactly, and you are using the production (not test) URL.
- **Workflows are slow or queue up.** Your server may need more resources. Open a ticket to discuss a larger plan.
- **I cannot reach the editor.** Confirm the service is Active, then use **Restart n8n** on the Manage tab.
