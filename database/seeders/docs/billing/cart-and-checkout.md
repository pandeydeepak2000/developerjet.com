---
title: Cart and Checkout
excerpt: Adding plans to your cart, choosing your app address, your billing details and placing an order safely.
order: 2
---
## The cart

- Press **Add to cart** on a plan card. The plan, billing period and any add-ons are saved.
- Open the cart from the **Cart** icon in the header or at `/cart`.
- Use **Remove** to delete a line.
- You can use the cart without signing in. When you log in, your guest cart is merged into your account cart.

> **Note:** Prices are always recalculated on our servers at checkout. Changing a page in your browser cannot change what you pay.

## Checkout, step by step

You must be signed in with a verified email to check out.

1. From the cart, continue to **Checkout**.
2. Review each line: plan, **Billed monthly** or **Billed yearly**, and the access model:
   - **Self-hosted** — DeveloperJet sets up the server and installs your software, then you receive full SSH/root access. You are responsible for updates, security and your data.
   - **Backups** are not included; contact our sales team if you want a backup plan.
3. For web apps (Hermes Workspace, n8n, OpenClaw, AI Complete Bundle), **Setup details** asks for the app address: *Use a DeveloperJet address* (nothing to do) or *Use my own domain*, which you verify with one DNS record after ordering. This is optional and can be changed later.
4. Check **Billed to**. It shows the company or name, address and GSTIN saved in your profile. Use *Edit* or *Add billing address* to change them once; they are used on all your invoices.
5. Choose a **Payment method**: **Razorpay** (UPI, cards and netbanking, + 18% GST) or **Wallet balance** (no GST, available when your balance covers the whole order). See [Payment methods](/docs/payment-methods).
6. Check the **Review** panel: subtotal, any discount, tax and total.
7. Tick the box to accept the Terms and Refund Policy, then press **Place order**.

With Razorpay the payment window opens straight away. If you close it, the order waits under **Dashboard → Orders** with status **Pending payment** — press **Pay** when you are ready. A wallet order is paid at once.

## Delivery is not instant

After payment our team adds a server to your order (**Awaiting VPS assignment**), then your software is installed. See [How orders are delivered](/docs/how-orders-are-delivered).

## Paying or cancelling later

Open the order from **Dashboard → Orders**:

- **Pay** — complete payment for an unpaid order.
- **Cancel order** — cancel an order that has not been paid. Any wallet funds reserved for it are returned to your wallet.

> **Warning:** A paid order cannot be cancelled from the dashboard. Open a Billing ticket to request a refund. See the Refund Policy for eligibility.

## Troubleshooting

- **"Order unavailable" instead of Place order.** A plan in your cart is not purchasable, or its price is pending approval. Remove it or use *Talk to Sales*.
- **"Online payment is not enabled yet."** Pay from your wallet, or place the order and pay it later.
- **Wallet balance is greyed out.** Your balance does not cover the whole order. [Add funds](/docs/invoices-and-wallet) or pay with Razorpay.
- **GSTIN rejected.** Enter exactly 15 capital letters and digits in **Profile & security → Billing details**, or leave it empty.
