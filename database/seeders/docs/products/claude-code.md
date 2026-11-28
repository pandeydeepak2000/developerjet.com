---
title: Claude Code Hosting
excerpt: Run Claude Code on an always-on VPS of your own, using your own eligible Claude subscription or API credentials.
order: 5
---
Claude Code Hosting gives you a VPS of your own with the Claude Code CLI installed and full root access, so your coding sessions can run on an always-on server instead of your laptop.

> **Important:** You must bring your **own eligible Claude subscription or API credentials**. DeveloperJet does not provide, include or resell Claude access, and is not affiliated with Anthropic.

## Who it is for

Developers comfortable with a terminal, SSH and basic Git. After handover the server is yours to look after — see [Self-hosted: who does what](/docs/managed-vs-unmanaged).

## What is included

- A VPS dedicated to you, sized by your plan.
- Claude Code CLI installed with the official installer.
- A security baseline applied during setup.
- Full root access, with your login, live usage and a Reboot button in the service panel.

**Not included:** a Claude subscription or API credits, help writing or reviewing your code, and backups.

## Getting started

1. Order a Claude Code plan and wait for the service to become **Active**.
2. Connect over SSH with the login from **Login & SSH**. See [Service access](/docs/service-access).
3. Sign in to Claude Code with your own account (`claude auth login`), or set your Anthropic API key.
4. Clone your repository and start working.

```bash
ssh root@your-server-ip
claude auth login                          # or: export ANTHROPIC_API_KEY=your-key-here
git clone git@github.com:your-org/your-repo.git
cd your-repo
claude
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
- **You want a clean start.** **Reinstall fresh** on the Manage tab removes Claude Code and its files, then installs it again.
