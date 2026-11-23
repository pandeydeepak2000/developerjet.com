<?php

namespace App\Support;

/**
 * Typed accessors for Admin → Settings → Billing & renewals. Defaults are engineering proposals (owner may change):
 * invoice 7 days before due, reminders 7 and 3 days before, on the due date and 1 day after, 3 grace days,
 * auto-suspension OFF, manual approval ON, live (adapter) suspension OFF.
 */
class BillingSettings
{
    public static function invoiceDaysBefore(): int
    {
        return max(0, min(60, (int) Setting::get('billing.invoice_days_before', Setting::get('renewal.reminder_days', 7))));
    }

    public static function remindersEnabled(): bool
    {
        return Setting::bool('billing.reminders_enabled', true);
    }

    /** @return list<int> days before the due date, largest first */
    public static function beforeOffsets(): array
    {
        return self::offsets((string) Setting::get('billing.reminder_offsets', '7,3'));
    }

    public static function dueDayReminder(): bool
    {
        return Setting::bool('billing.reminder_on_due_date', true);
    }

    /** @return list<int> days after the due date, largest first */
    public static function overdueOffsets(): array
    {
        return self::offsets((string) Setting::get('billing.overdue_offsets', '1'));
    }

    public static function graceDays(): int
    {
        return max(0, min(60, (int) Setting::get('billing.grace_days', 3)));
    }

    public static function autoSuspend(): bool
    {
        return Setting::bool('billing.auto_suspend', false);
    }

    public static function suspensionNeedsApproval(): bool
    {
        return Setting::bool('billing.suspension_requires_approval', true);
    }

    /** Even with a verified adapter, live suspension stays off until the owner turns this on. */
    public static function liveSuspension(): bool
    {
        return Setting::bool('billing.live_suspension_enabled', false);
    }

    /** "auto" = use a verified adapter after payment when one exists; "manual" = always queue for staff. */
    public static function reactivationPolicy(): string
    {
        return Setting::get('billing.reactivation_policy', 'auto') === 'manual' ? 'manual' : 'auto';
    }

    public static function cancellationEnabled(): bool
    {
        return Setting::bool('billing.cancellation_enabled', true);
    }

    public static function cancellationNeedsApproval(): bool
    {
        return Setting::bool('billing.cancellation_requires_approval', true);
    }

    public static function immediateCancellationAllowed(): bool
    {
        return Setting::bool('billing.cancellation_allow_immediate', false);
    }

    /** Days after the due date before an unpaid subscription expires automatically; null = never (default). */
    public static function expireAfterDays(): ?int
    {
        $v = Setting::get('billing.expire_after_days');

        return $v === null || (int) $v < 1 ? null : min(365, (int) $v);
    }

    /** "next_renewal" (default) or "manual_invoice". No automatic proration exists. */
    public static function addonMidcyclePolicy(): string
    {
        return Setting::get('billing.addon_midcycle_policy', 'next_renewal') === 'manual_invoice' ? 'manual_invoice' : 'next_renewal';
    }

    public static function renewalFailureAlertAfter(): int
    {
        return max(1, min(20, (int) Setting::get('billing.renewal_failure_alert_after', 3)));
    }

    public static function paymentInstructions(): string
    {
        return (string) Setting::get('billing.payment_instructions', 'Pay online from your dashboard (Invoices → Renew now) or use your wallet balance. For bank transfer, contact our team before paying.');
    }

    /** @return list<int> */
    public static function offsets(string $csv): array
    {
        $values = array_unique(array_filter(array_map('intval', preg_split('/[\s,]+/', $csv) ?: []), fn ($d) => $d >= 1 && $d <= 60));
        rsort($values);

        return array_values($values);
    }
}
