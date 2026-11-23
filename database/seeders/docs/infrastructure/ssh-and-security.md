---
title: SSH and Security
excerpt: Creating SSH keys, connecting safely, and the security practices that protect your account and server.
order: 5
---
## Create an SSH key

An SSH key pair is safer than a password. The **private** key stays on your computer; the **public** key (ending in `.pub`) is placed on the server.

```bash
# macOS, Linux, or Windows 10/11 PowerShell
ssh-keygen -t ed25519 -C "you@example.com"
```

Press Enter to accept the default location and choose a passphrase. Your public key is then at `~/.ssh/id_ed25519.pub`.

```bash
# Show the public key so you can copy it
cat ~/.ssh/id_ed25519.pub
```

> **Warning:** Never share the private key (`id_ed25519` without `.pub`) with anyone, including DeveloperJet staff.

## Give us your public key

Open a ticket for the service and paste the contents of your `.pub` file. Our team adds it to the server as part of the secure handover.

## Connect

```bash
ssh your-username@your-server-ip
```

On first connection you are asked to confirm the server's fingerprint. Compare it with the fingerprint given in your access instructions if one was provided, then type `yes`.

### Windows with PuTTY

1. Convert your key with **PuTTYgen** (*Conversions → Import key*, then *Save private key* as `.ppk`), or generate a new key in PuTTYgen.
2. In PuTTY set **Host Name** to the server IP and **Port** to `22`.
3. Under *Connection → SSH → Auth → Credentials*, select your `.ppk` file.
4. Open the session and log in with your username.

## Unmanaged servers: harden SSH

On a Docker VPS you are responsible for SSH settings. After confirming key login works **in a second terminal**:

```bash
sudo nano /etc/ssh/sshd_config
# Set:
#   PasswordAuthentication no
#   PermitRootLogin prohibit-password
sudo systemctl reload ssh   # the service may be called sshd on some systems
```

> **Warning:** Keep your current session open until you have tested a new login. A mistake in `sshd_config` can lock you out.

## Account security checklist

- Use a unique, long password for your DeveloperJet account.
- Never share one-time codes, reset links, passwords or private keys.
- Store API keys only in the service page's **AI provider** form.
- DeveloperJet staff will never ask for your password, OTP or private key.
- Report anything suspicious in a ticket straight away.

## Troubleshooting

- **"Permission denied (publickey)".** Your public key is not on the server, or SSH is using a different key. Try `ssh -i ~/.ssh/id_ed25519 user@ip`.
- **"WARNING: REMOTE HOST IDENTIFICATION HAS CHANGED".** Stop and open a ticket before connecting. If we confirm the server was rebuilt, remove the old entry with `ssh-keygen -R your-server-ip`.
- **"Unprotected private key file".** On macOS/Linux run `chmod 600 ~/.ssh/id_ed25519`.
