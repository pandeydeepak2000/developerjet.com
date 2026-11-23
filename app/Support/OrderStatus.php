<?php

namespace App\Support;

/** Order lifecycle. Transitions are validated centrally; `paid` is reachable only via verified payment. */
class OrderStatus
{
    public const DRAFT = 'draft';

    public const PENDING_PAYMENT = 'pending_payment';

    public const PAID = 'paid';

    public const AWAITING_PROVISIONING = 'awaiting_provisioning';

    public const PROVISIONING = 'provisioning';

    public const ACTIVE = 'active';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const REFUND_PENDING = 'refund_pending';

    public const REFUNDED = 'refunded';

    public const LABELS = [
        self::DRAFT => 'Draft',
        self::PENDING_PAYMENT => 'Pending payment',
        self::PAID => 'Paid',
        self::AWAITING_PROVISIONING => 'Awaiting VPS assignment',
        self::PROVISIONING => 'Provisioning',
        self::ACTIVE => 'Active',
        self::FAILED => 'Failed',
        self::CANCELLED => 'Cancelled',
        self::REFUND_PENDING => 'Refund pending',
        self::REFUNDED => 'Refunded',
    ];

    public const TRANSITIONS = [
        self::DRAFT => [self::PENDING_PAYMENT, self::CANCELLED],
        self::PENDING_PAYMENT => [self::PAID, self::FAILED, self::CANCELLED],
        self::PAID => [self::AWAITING_PROVISIONING, self::REFUND_PENDING],
        self::AWAITING_PROVISIONING => [self::PROVISIONING, self::REFUND_PENDING],
        self::PROVISIONING => [self::ACTIVE, self::FAILED, self::AWAITING_PROVISIONING],
        self::ACTIVE => [self::REFUND_PENDING],
        // `failed` is retryable: unpaid orders go back to payment, paid ones back to provisioning (guarded in OrderService).
        self::FAILED => [self::PENDING_PAYMENT, self::AWAITING_PROVISIONING, self::CANCELLED, self::REFUND_PENDING],
        self::REFUND_PENDING => [self::REFUNDED, self::ACTIVE, self::AWAITING_PROVISIONING],
        self::CANCELLED => [],
        self::REFUNDED => [],
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Statuses in which money has been verifiably received. */
    public static function isPaidState(string $status): bool
    {
        return in_array($status, [self::PAID, self::AWAITING_PROVISIONING, self::PROVISIONING, self::ACTIVE, self::REFUND_PENDING, self::REFUNDED], true);
    }
}
