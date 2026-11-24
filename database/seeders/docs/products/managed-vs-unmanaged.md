---
title: Self-hosted: who does what
excerpt: Every DeveloperJet plan is self-hosted — we set up the server and hand it over with root access. Here is our part and yours.
order: 8
---
Every DeveloperJet hosting plan is **self-hosted**. We provide a VPS dedicated to you, install your software (Hermes, n8n, OpenClaw, Claude Code or Docker) and hand the server over with **full SSH/root access**. After that the server is yours.

[diagram: access-models]

## Responsibilities

| Task | DeveloperJet | You |
| --- | --- | --- |
| Provide the VPS | Yes | |
| Install and check your software | Yes | |
| Hand over SSH/root access | Yes | |
| Operating system and application updates after handover | | **You** |
| Firewall and security settings after handover | | **You** |
| Your data, prompts, workflows and containers | | **You** |
| Backups | Not included (separate backup plan on request) | **You** |

## Backups

Backups are **not included** in any plan. DeveloperJet is not responsible for changes, deletions or data loss on your VPS. If you want a backup plan, contact our sales team.

> **Warning:** With root access, changes you make (for example firewall rules or SSH settings) can lock you out or delete data. Keep your own backups before making big changes.

## Related

- [SSH and security](/docs/ssh-and-security)
- [Service access](/docs/service-access)
- [Docker VPS](/docs/docker-vps)
