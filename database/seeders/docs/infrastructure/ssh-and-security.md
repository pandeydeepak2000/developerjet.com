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
# Replace your-username and your-server-ip with the values from your service page
ssh your-username@your-server-ip
```

On first connection you are asked to confirm the server's fingerprint. Compare it with the fingerprint given in your access instructions if one was provided, then type `yes`.

## Everyday commands on your server

Once connected, these are the commands you will use most. They are safe to run — none of them change anything on the server.

| Command | What it does | Example |
| --- | --- | --- |
| `pwd` | Shows which folder you are in | `pwd` |
| `ls -lh` | Lists files with sizes | `ls -lh /var/www` |
| `cd` | Moves into a folder | `cd my-project`, `cd ..` (one level up) |
| `cat` | Prints a file | `cat /etc/os-release` |
| `less` | Reads a long file page by page (press `q` to exit) | `less /var/log/syslog` |
| `df -h` | Shows free disk space | `df -h /` |
| `free -h` | Shows memory usage | `free -h` |
| `uptime` | Shows how long the server has been running | `uptime` |

Commands that **change** things need more care:

| Command | What it does | Example |
| --- | --- | --- |
| `cp` | Copies a file | `cp config.php config.php.bak` |
| `mv` | Moves or renames a file | `mv old.txt new.txt` |
| `mkdir` | Creates a folder | `mkdir backups` |
| `nano` | Edits a file (Ctrl+O saves, Ctrl+X exits) | `nano compose.yaml` |

> **Warning:** Be very careful with `rm`, especially `rm -rf`. It deletes permanently, without a recycle bin. Always make a copy (`cp`) first, and never run a delete command you found online without understanding it. If you are unsure, open a ticket and ask.

### Windows with PuTTY

1. Convert your key with **PuTTYgen** (*Conversions → Import key*, then *Save private key* as `.ppk`), or generate a new key in PuTTYgen.
2. In PuTTY set **Host Name** to the server IP and **Port** to `22`.
3. Under *Connection → SSH → Auth → Credentials*, select your `.ppk` file.
4. Open the session and log in with your username.

## Harden SSH on your server

On a Docker VPS you are responsible for SSH settings. After confirming key login works **in a second terminal**:

```bash
sudo nano /etc/ssh/sshd_config
# Set:
#   PasswordAuthentication no
#   PermitRootLogin prohibit-password
sudo systemctl reload ssh   # the service may be called sshd on some systems
```

> **Warning:** Keep your current session open until you have tested a new login. A mistake in `sshd_config` can lock you out.

## Firewall

A firewall closes every port except the ones you allow. After handover this is your job, and it is the single most effective protection you can add.

```bash
# Allow SSH FIRST - if you skip this step you will lock yourself out
sudo ufw allow OpenSSH

# Allow web traffic if you run a website or web app
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Turn the firewall on, then confirm it is active
sudo ufw enable
sudo ufw status verbose
```

> **Warning:** Docker can publish ports that bypass `ufw` rules. Bind a container to `127.0.0.1` unless it must be reachable from the internet.

If you are locked out after changing SSH or firewall settings, open a **Technical Support** ticket — we can restore access.

## Docker commands (Docker VPS)

On a Docker VPS you run your own containers. The full walkthrough — updating the system, a `compose.yaml` example and backups — is in [Docker VPS](/docs/docker-vps). The commands you will use most:

```bash
docker ps                     # what is running
docker compose up -d          # start your application
docker compose down           # stop and remove it
docker compose ps             # status of your services
docker compose logs -f web    # follow the logs (Ctrl+C to stop)
docker image prune            # free disk space from unused images
```

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
- **Locked out after a firewall or SSH change.** Open a Technical Support ticket; we can restore access.
