---
title: Login and Password Reset
excerpt: Signing in, staying secure, and resetting a forgotten password with an emailed link.
order: 4
---
## Sign in

1. Choose **Login** or go to `/login`.
2. Enter your email address and password.
3. Tick **Keep me signed in** only on a private device.
4. Press **Log in**. If your email is not verified yet, you are asked for your one-time code first.

> **Note:** Customer and staff sign-in are separate. Customers always use `/login`.

## Too many attempts

After several failed sign-in attempts, sign-in is paused for that account and connection. The message tells you how many seconds to wait. This protects you from password-guessing attacks.

## Reset a forgotten password

1. On the sign-in page choose **Forgot password?** next to the password field, or go to `/forgot-password`.
2. Enter your account email and press **Send reset link**.
3. You see the same confirmation message whether or not an account exists — this stops anyone from discovering which addresses are registered.
4. Open the email and follow the **Reset password** link. The link expires after **60 minutes** and works once.
5. Enter your email, a **New password** and **Confirm new password**, then press **Reset password**. You can now sign in.

> **Warning:** A reset link gives access to your account. Do not forward it. If you receive a reset email you did not request, you can ignore it — your password does not change unless the link is used.

## Logging out

Use **Log out** in the site header, especially on shared computers. Authenticated pages are sent with no-store headers so they are not kept in the browser cache.

## Troubleshooting

- **"These credentials do not match our records."** Check the email address for typos and that Caps Lock is off, or reset your password.
- **The reset link says it is invalid.** It has expired or was already used. Request a new one.
- **No reset email.** Check spam, and wait a minute — requests are rate-limited. Make sure you used the address the account was registered with.
