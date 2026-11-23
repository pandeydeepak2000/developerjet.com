---
title: n8n Managed
excerpt: Workflow automation with n8n on a managed VPS — availability, what is included and how to get started.
order: 3
---
n8n is a workflow-automation tool. DeveloperJet installs it on a VPS set up for your plan and manages the server; you build the workflows.

> **Important:** Managed n8n is offered only after licensing for this use has been verified. If a plan shows *Contact Sales* instead of *Add to cart*, it is not yet open for online purchase.

## Who it is for

Teams and individuals who want to connect apps and automate tasks without running a server. Familiarity with the apps you want to connect helps.

## What is included

- A VPS set up for your plan. Plans are sized by active-workflow capacity.
- n8n installed and checked.
- Access details delivered through your dashboard.

**Not included:** designing or building your workflows, paid third-party services your workflows connect to, and unrestricted root access.

## Getting started

1. Order an n8n plan (or ask Sales) and wait for the service to become **Active**.
2. Open **Dashboard → Services → your n8n service** and use the **Service URL**.
3. Create your n8n owner account on first visit, using a strong unique password.
4. Build your first workflow and test it with a manual trigger before enabling it.

## Credentials in n8n

Store third-party credentials in n8n's own **Credentials** section, not inside workflow nodes as plain text. Never share them in a support ticket.

## Custom domain

n8n supports connecting a domain you already own. See [Custom domain setup](/docs/custom-domain-setup). Update any webhook URLs in other services after switching to the new address.

## Troubleshooting

- **A webhook is not firing.** Check the workflow is **active**, the webhook URL in the other service matches exactly, and you are using the production (not test) URL.
- **Workflows are slow or queue up.** You may be above your plan's workflow capacity. Open a ticket to discuss a larger plan.
- **I cannot reach the editor.** Confirm the service is Active and try the default address.
