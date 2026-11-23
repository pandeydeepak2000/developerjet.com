---
title: Claude Code Hosting
excerpt: Run Claude Code on an always-on managed VPS, using your own eligible Claude subscription or API credentials.
order: 5
---
Claude Code Hosting gives you a managed VPS with the Claude Code CLI installed, so your coding sessions can run on an always-on server instead of your laptop.

> **Important:** You must bring your **own eligible Claude subscription or API credentials**. DeveloperJet does not provide, include or resell Claude access, and is not affiliated with Anthropic.

## Who it is for

Developers comfortable with a terminal, SSH keys and basic Git. You do not need to administer the server.

## What is included

- A VPS assigned to your order (size depends on the plan once confirmed).
- Claude Code CLI installed by the deployment process.
- A security baseline applied during setup.
- Access instructions delivered through your dashboard.

**Not included:** a Claude subscription or API credits, help writing or reviewing your code, and unrestricted root access — for full root access see [Docker VPS](/docs/docker-vps).

## Getting started

1. Order a Claude Code plan and wait for the service to become **Active**.
2. Connect over SSH using the access instructions on the service page. See [Service access](/docs/service-access).
3. Sign in to Claude Code with your own account, or provide your API credentials — either on the service page (**AI provider** → Anthropic) or as described in your access instructions.
4. Clone your repository and start working.

```bash
ssh your-username@your-server-ip
git clone git@github.com:your-org/your-repo.git
cd your-repo
```

## Keeping sessions running

Because the server is always on, you can disconnect and reconnect. Use a terminal multiplexer such as `tmux` if it is available in your environment, so a session survives a dropped connection:

```bash
tmux new -s work      # start a named session
tmux attach -t work   # reconnect later
```

## Security tips

- Use a deploy key or a fine-grained token for Git with the minimum access needed.
- Never commit API keys to your repository.
- Review changes before you push them.

## Troubleshooting

- **Claude Code says you are not signed in.** Sign in again with your own account or check your API credentials.
- **Git asks for a password.** Use SSH keys or a token; see [SSH and security](/docs/ssh-and-security).
- **The plan shows Pricing to be confirmed.** Plan sizes and prices are published only after they are approved.
