---
title: API Provider Configuration
excerpt: Adding, replacing and protecting your own AI provider API key on supported services.
order: 3
legacy: ai-api-providers
---
AI products on DeveloperJet use **your own AI provider account**. You add an API key to the service, and the provider bills your account directly for usage.

## Supported services and providers

The **AI provider** panel appears on these services: Hermes CLI, Hermes Workspace, OpenClaw, AI Complete Bundle and Claude Code.

You can choose one of: **OpenAI**, **Anthropic**, **Google Gemini** or **OpenRouter**.

## Add a key

1. Create an API key in your provider's console. Give it a recognisable name such as `developerjet-hermes`.
2. Go to **Dashboard → Services** and open the service.
3. In **AI provider**, choose the **Provider**.
4. Paste the key into **API key (write-only)**.
5. Press **Save securely**. The panel shows **Configured** with the provider name.

## How your key is protected

- The key is **encrypted** before it is stored.
- It is **write-only**: it is never displayed again, never emailed and never written to logs.
- Only the provider name is recorded in the audit trail — never the key.

> **Warning:** Never paste API keys into a support ticket, chat or email. If a key may have been exposed, revoke it at the provider immediately, create a new one and save the new key here.

## Replace or rotate a key

Enter the new key in the same form and press **Save securely**. The new key replaces the old one. Then revoke the old key at the provider.

## Skip for now

If you do not have a key yet, press **Skip for now**. The service is still delivered, but marked **Configuration incomplete** and cannot answer AI requests until you add a key.

> **Note:** *Skip for now* also removes any key already saved on the service.

## Controlling cost

- Set a monthly budget or hard limit in your provider's console.
- Use separate keys per service so you can see usage and revoke one without affecting others.

## Troubleshooting

- **"The ai api key field must be at least 8 characters."** You pasted only part of the key. Copy it again from the provider.
- **The service answers with an authentication error.** The key was revoked or is for a different provider than the one selected.
- **Requests fail with a quota or billing error.** Add credit or raise the limit at your provider.
