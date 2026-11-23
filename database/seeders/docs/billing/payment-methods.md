---
title: Payment Methods
excerpt: Which payment methods are available, how payments are confirmed, and what to do if a payment looks stuck.
order: 3
---
## Available methods

Checkout lists only the payment methods that are switched on at the time you order. The methods DeveloperJet supports are:

| Method | What it covers | Notes |
| --- | --- | --- |
| **Razorpay** | UPI, cards and netbanking | Shown when enabled by DeveloperJet. |
| **Wallet balance** | Funds already in your DeveloperJet wallet | Tick *Apply wallet balance* at checkout. |
| **PTSure** | Online payments | Listed only after the integration has been verified. |

If no online method is enabled, checkout tells you so. The order can still be placed and paid later.

## Paying with the wallet

- If your wallet covers the full total, the order is paid immediately.
- If it covers only part, that amount is reserved for the order and you pay the rest with an online method.
- If you cancel an unpaid order, the reserved wallet funds return to your wallet.

See [Invoices and wallet](/docs/invoices-and-wallet) to add funds.

## How a payment is confirmed

1. You complete payment in the payment window.
2. Our server verifies the payment directly — using the gateway's signature and, where available, its webhook.
3. Only then does the order change to **Paid** and the invoice get issued.

Returning to the website from the payment page is never enough on its own to mark an order paid. This protects you and us from tampered or incomplete payments.

> **Note:** A background job reconciles captured payments every 15 minutes, so a payment that was captured but not confirmed in your browser is still picked up.

## Security

- DeveloperJet does not store your card number, UPI PIN or bank password. They are entered on the payment provider's page.
- DeveloperJet does not charge saved payment details automatically. Renewals are paid by you. See [Billing and renewals](/docs/billing-and-renewals).

## Troubleshooting

- **Money was debited but the order says Pending payment.** Wait 15–30 minutes for reconciliation. If it still has not changed, open a **Billing** ticket with the order number and the payment reference (UTR / payment ID). Do not pay a second time.
- **The payment window closed.** Open the order in **Dashboard → Orders** and press **Pay** again.
- **The payment failed.** Nothing is charged for a failed payment. Try again or use another method; if your bank shows a debit, it is normally reversed by the bank.
