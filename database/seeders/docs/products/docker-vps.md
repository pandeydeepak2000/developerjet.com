---
title: Docker VPS
excerpt: An unmanaged VPS delivered with Docker Engine and Compose, with full SSH/root access.
order: 6
---
Docker VPS is an **unmanaged** server. We deliver it with Docker Engine and Docker Compose installed and checked; from then on you manage it.

[diagram: access-models]

## Who it is for

You are comfortable with Linux, SSH, Docker and Compose, and you accept responsibility for securing and updating the server.

## What is included

- A VPS assigned to your order (size depends on the plan once confirmed).
- Docker Engine and Docker Compose installed.
- SSH/root access details delivered securely.

**Not included:** server management, updates or hardening after handover; a hosted control panel, web console or one-click app catalogue; backups or snapshots until such a feature has been verified and announced; help with your containers or code.

## First steps after handover

1. Connect as described in [Service access](/docs/service-access).
2. Update the operating system packages.
3. Add your SSH key and disable password login. See [SSH and security](/docs/ssh-and-security).
4. Turn on a firewall that allows only the ports you need.
5. Deploy your first stack.

```bash
# Ubuntu / Debian
sudo apt update && sudo apt upgrade -y

# Allow SSH and web traffic, then enable the firewall
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Check Docker
docker --version
docker compose version
```

> **Warning:** Always allow SSH **before** enabling a firewall, or you can lock yourself out. Docker-published ports can bypass `ufw` rules; bind services to `127.0.0.1` unless they must be public.

## A minimal Compose example

```yaml
# compose.yaml
services:
  web:
    image: nginx:stable
    ports:
      - "80:80"
    restart: unless-stopped
```

```bash
docker compose up -d
docker compose ps
docker compose logs -f web
```

## Backups

Backups are your responsibility on an unmanaged server. Copy volumes and databases off the server regularly and test restoring them.

## Server controls

The **Controls** panel on your service page lists power, console, snapshots and other actions. Each is enabled only after it has been verified for your server; until then it shows *Unavailable* with a reason. Open a ticket if you need a restart.

## Troubleshooting

- **Locked out after enabling the firewall.** Open a Technical Support ticket — we can help restore access.
- **Container exits immediately.** Run `docker compose logs <service>` to see why.
- **Disk full.** Check with `df -h` and clean unused images with `docker image prune`.
