---
title: OpenClaw
excerpt: An isolated personal AI assistant on a VPS used only by you, behind authenticated access.
order: 4
---
OpenClaw is a personal AI assistant. Each customer gets an isolated deployment on a VPS used only by them, with gateway authentication and an isolated service user.

> **Note:** OpenClaw plan details are still being finalised. Plans that are not yet approved show *Pricing to be confirmed* and cannot be bought online.

## Who it is for

Anyone who wants a private assistant on a server of their own. You should know how to create an API key with an AI provider and run a command over SSH.

## What is included

- A VPS assigned to your order and used only by you.
- OpenClaw installed behind authenticated access.
- Full root access to the server, with your login in the service panel.

**Not included:** AI model usage (bring your own key), custom integrations and backups.

## Getting started

1. Order an OpenClaw plan and wait for the service to become **Active**.
2. Connect over SSH with the login from **Login & SSH** and run `openclaw onboard --auth-choice openrouter-api-key` (it asks for your key). See [API provider configuration](/docs/api-provider-configuration).
3. Press **Open** at the top of the service panel to reach OpenClaw through its secured gateway.

## Custom domain

OpenClaw supports connecting a domain you already own. See [Custom domain setup](/docs/custom-domain-setup).

## Privacy

Your server is used only by you. Your provider key stays on your server. Conversations are processed by the AI provider whose key you supply, under that provider's terms.

## Troubleshooting

- **The gateway asks for credentials I do not have.** Open a Technical Support ticket for the service — we never send credentials by email.
- **The assistant does not respond.** Check your provider key and your provider account's balance or limits.
