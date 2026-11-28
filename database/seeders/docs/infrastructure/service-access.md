---
title: Service Access
excerpt: Where to find your server login, how to connect over SSH or in a browser, and how to change your root password.
order: 2
legacy: server-access
---
When your service becomes **Active**, its login details appear in the service panel in your dashboard. We send you an email when it is ready; for your security the email never contains a password.

## Where to find your login

1. Go to **Dashboard → My services**.
2. Open the service and select the **Login & SSH** tab.
3. **Server login** shows the IP address, the username (usually `root`), the password (press **Show** or **Copy**) and the SSH port.
4. **How to connect** gives the exact `ssh` command and the PuTTY settings.

For web apps (Hermes Workspace, n8n, OpenClaw, AI Complete Bundle) the **Open** button at the top of the panel opens the app in your browser.

## How each product is accessed

| Product | How you connect |
| --- | --- |
| Hermes CLI | SSH or PuTTY |
| Hermes Workspace | Web browser (DeveloperJet address or your verified domain) |
| n8n | Web browser |
| OpenClaw | Secured web gateway |
| AI Complete Bundle | Browser for Workspace and n8n, SSH for Hermes CLI |
| Claude Code | SSH |
| Node.js Hosting | SSH, then your app in the browser |
| Docker VPS | SSH with root access |

Every hosting plan includes full SSH/root access. See [Self-hosted: who does what](/docs/managed-vs-unmanaged).

## Change your root password

On the **Login & SSH** tab, use the **Root password** box:

- type your own new password twice (12–64 characters, letters and numbers, no spaces) and press **Change password**, or
- press **Generate a strong password for me**.

The new password works at once and is shown under **Server login**. The old password stops working immediately.

> **Important:** Change the root password in the panel, not with `passwd` on the server. If you change it on the server, the panel can no longer show usage, reboot the server or show your login. If that happens, open a ticket.

If your server uses an SSH key instead of a password, the panel says so and our team handles access through a ticket.

## Use an SSH key (optional)

A key is safer than a password. Once you can log in, add your public key yourself with `ssh-copy-id root@your-server-ip`. See [SSH and security](/docs/ssh-and-security).

## Troubleshooting

- **The Login & SSH tab is missing.** Setup is not finished yet. See [How orders are delivered](/docs/how-orders-are-delivered).
- **"Permission denied" with the password.** Copy it again with **Copy**, or set a new one in the Root password box.
- **Usage says "We could not reach your server just now".** The server may be rebooting — wait a minute. If it stays, the root password may have been changed on the server; open a ticket.
- **I lost access.** Open a Technical Support ticket for the service. We verify your identity through your account before restoring access.
