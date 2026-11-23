---
title: Managed vs Unmanaged
excerpt: Who is responsible for what on managed hosting and on an unmanaged Docker VPS.
order: 8
---
Every DeveloperJet hosting plan uses one of two **access models**. Checkout always shows which one you are buying.

[diagram: access-models]

## Responsibilities

| Task | Managed hosting | Unmanaged VPS |
| --- | --- | --- |
| Assign the VPS | DeveloperJet | DeveloperJet |
| Install the application / Docker | DeveloperJet | DeveloperJet |
| Security baseline at setup | DeveloperJet | DeveloperJet (initial install only) |
| Operating system updates after handover | DeveloperJet | **You** |
| Firewall rules | DeveloperJet | **You** |
| Root access | Restricted by service policy | **Full SSH/root** |
| Your data, prompts, workflows and containers | You | You |
| Backups | Shared once your service is configured | **You** |

## Which products use which model

- **Managed:** Hermes CLI, Hermes Workspace, n8n, OpenClaw, Claude Code, AI Complete Bundle.
- **Unmanaged:** Docker VPS.

## How to choose

Choose **managed** if you want to use an application and not look after a server. Choose **unmanaged** if you need full control — custom software, your own containers, your own security setup — and are comfortable administering Linux.

> **Warning:** On an unmanaged server, changes you make (for example firewall rules or SSH settings) can lock you out. Our support covers the infrastructure and handover, not your configuration.

## Related

- [Docker VPS](/docs/docker-vps)
- [SSH and security](/docs/ssh-and-security)
- [Service access](/docs/service-access)
