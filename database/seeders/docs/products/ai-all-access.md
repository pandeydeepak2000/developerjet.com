---
title: AI All-Access
excerpt: One API key for GPT, Claude, Gemini, DeepSeek and more, with a monthly usage credit.
order: 7
---
AI All-Access gives you **one API key** that works with models from many AI providers — GPT, Claude, Gemini, DeepSeek, Qwen, Grok and more — with a **monthly usage credit** set by your plan. The key is OpenAI- and Anthropic-compatible, so it works in the tools you already use.

## What it does not include

- It does **not** include ChatGPT Plus, Claude Pro, Gemini Advanced, Grok or any other consumer chat subscription. It is API access for tools and code.
- It is **not unlimited**: requests stop when your monthly credit is used up. Ask us for a top-up or a bigger plan at any time.
- It does not include a server. For an AI agent on your own VPS, choose a hosting product — the same key works there.

## Plans

| Plan | Price per month | Monthly usage credit | API keys |
| --- | --- | --- | --- |
| AI Starter | ₹999 | $8 | 1 |
| AI Pro | ₹2,499 | $20 | 2 |
| AI Team | ₹5,999 | $50 | 5 |
| AI Business | ₹14,999 | $125 | 15 |

Prices exclude GST. Yearly billing is 12 × the monthly price. Credit is shown in US dollars because model usage is priced in dollars; bigger models and longer answers use more credit.

## Getting your key

1. Choose a plan on the **AI All-Access** page and complete checkout.
2. Our team creates your key with your plan's credit. This is not instant — usually within a few business hours.
3. When it is ready we email you, and **Dashboard → My services → your AI plan** shows the **Base URL** and your **API key** (hidden until you press *Show*).

> **Tip:** Treat the key like a password. Anyone who has it can use your credit. If it leaks, open a support ticket and we will replace it.

## Using it in your tools

**Claude Code**

```
export ANTHROPIC_BASE_URL=<Anthropic base URL from your dashboard>
export ANTHROPIC_AUTH_TOKEN=<your key>
claude
```

**OpenAI SDK, Cursor and other OpenAI-compatible apps**

```
export OPENAI_BASE_URL=<base URL from your dashboard>
export OPENAI_API_KEY=<your key>
```

**n8n** — Credentials → Add credential → OpenAI → paste the key and set *Base URL* to the base URL from your dashboard.

Your dashboard shows these steps with your own base URL filled in.

## Renewals and cancellation

Plans renew monthly or yearly like every DeveloperJet plan. You can cancel future renewals from your dashboard. Credit that has already been used is not refundable.
