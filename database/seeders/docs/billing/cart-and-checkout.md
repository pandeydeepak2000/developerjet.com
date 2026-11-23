---
title: Cart and Checkout
excerpt: Adding plans to your cart, completing billing details and placing an order safely.
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
   - **Managed hosting** — DeveloperJet sets up and secures the server; root access is restricted.
   - **Unmanaged VPS** — you receive SSH/root access and are responsible for updates, security and your workloads.
3. Complete **Billing details**: *Billing name*, optional *GSTIN* (15 characters) and *Billing address*. These appear on your invoice.
4. Choose a **Payment method**. Only methods that are enabled are listed. See [Payment methods](/docs/payment-methods).
5. If you have wallet funds, tick **Apply wallet balance**.
6. Check the **Review** panel: subtotal, any discount, tax and total.
7. Tick the box to accept the Terms and Refund Policy, then press **Place order**.

After you place the order, it appears under **Dashboard → Orders** with status **Pending payment** until the payment is verified.

## Delivery is not instant

Hosting orders follow manual VPS assignment by our team. After payment the order moves to **Awaiting VPS assignment**. See [How orders are delivered](/docs/how-orders-are-delivered).

## Paying or cancelling later

Open the order from **Dashboard → Orders**:

- **Pay** — complete payment for an unpaid order.
- **Cancel order** — cancel an order that has not been paid. Any wallet funds reserved for it are returned to your wallet.

> **Warning:** A paid order cannot be cancelled from the dashboard. Open a Billing ticket to request a refund. See the Refund Policy for eligibility.

## Troubleshooting

- **"Order unavailable" instead of Place order.** A plan in your cart is not purchasable, or its price is pending approval. Remove it or use *Talk to Sales*.
- **"No online payment method is enabled yet."** You can still place the order; it stays unpaid until a payment method is available or you pay with wallet funds.
- **Tax shows "Not configured".** Tax rules are still being set up. The final tax is shown before you pay.
- **GSTIN rejected.** Enter exactly 15 capital letters and digits, or leave it empty.
