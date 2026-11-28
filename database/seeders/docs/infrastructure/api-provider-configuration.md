---
title: API Provider Configuration
excerpt: Adding, replacing and protecting your own AI provider API key on your server after delivery.
order: 3
legacy: ai-api-providers
---
AI products on DeveloperJet use **your own AI provider account** (for example OpenRouter, OpenAI or Anthropic). You add your key on your own server after delivery, and the provider bills your account directly for usage. We do not ask for your key at checkout.

## Where to find the steps

1. Go to **Dashboard → My services** and open the service.
2. On the **Overview** tab, find **Add your AI API key** (for n8n: **Getting started**).
3. Connect to your server over SSH (see [Service access](/docs/service-access)) and run the commands shown. Each command has a **Copy** button. Replace `your-key-here` with your own key.

## Commands by product

| Product | What to run |
| --- | --- |
| Hermes CLI | `hermes config set OPENROUTER_API_KEY your-key-here`, or `hermes model` to pick another provider |
| Hermes Workspace | The same two commands, then `hermes gateway restart` |
| OpenClaw | `openclaw onboard --auth-choice openrouter-api-key` (it asks for the key), then `openclaw models status` |
| Claude Code | `claude auth login` to sign in with your Claude account, or `export ANTHROPIC_API_KEY=your-key-here` |
| AI Complete Bundle | The Hermes commands above; for n8n add keys in the n8n editor |
| n8n | In n8n go to **Credentials → Add credential** and choose OpenAI, OpenRouter or another provider |

The service page always shows the current commands for your product.

## How your key is protected

- The key stays on **your** server. DeveloperJet does not see it.
- The optional **AI API keys** page in your dashboard can keep a copy of your keys, encrypted. Only the last 4 characters are ever shown, and keys are never emailed.

> **Warning:** Never paste API keys into a support ticket, chat or email. If a key may have been exposed, revoke it at the provider immediately and add a new one.

## Replace or rotate a key

Run the same command again with the new key, then revoke the old key at the provider.

## Do not have a provider account?

[AI All-Access](/docs/ai-all-access) gives you one API key for many models with a monthly credit, delivered by our team.

## Controlling cost

- Set a monthly budget or hard limit in your provider's console.
- Use separate keys per service so you can see usage and revoke one without affecting others.

## Troubleshooting

- **The app says no provider is configured.** Run the command from the service Overview again and check for typos in the key.
- **The app answers with an authentication error.** The key was revoked or belongs to a different provider.
- **Requests fail with a quota or billing error.** Add credit or raise the limit at your provider.
