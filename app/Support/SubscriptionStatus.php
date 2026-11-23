<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Subscription (per service) lifecycle. Transitions are validated; anything else throws.
 *
 *   active → renewal_due → (paid) active
 *   renewal_due → grace_period → overdue → suspension_pending → suspended → reactivation_pending → active
 *   any non-terminal → cancelled; suspended/overdue → expired (manual)
 *
 * "suspended" means staff (or a verified adapter) actually suspended the service. Nothing reaches "suspended"
 * or leaves "reactivation_pending" for "active" without that action succeeding.
 */
class SubscriptionStatus
{
    public const ACTIVE = 'active';

    public const RENEWAL_DUE = 'renewal_due';

    public const OVERDUE = 'overdue';

    public const GRACE = 'grace_period';

    public const SUSPENSION_PENDING = 'suspension_pending';

    public const SUSPENDED = 'suspended';

    public const REACTIVATION_PENDING = 'reactivation_pending';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    /** Legacy value written before this engine existed; treated as overdue. */
    public const LEGACY_PAST_DUE = 'past_due';

    public const LABELS = [
        self::ACTIVE => 'Active', self::RENEWAL_DUE => 'Renewal due', self::OVERDUE => 'Overdue', self::GRACE => 'Grace period',
        self::SUSPENSION_PENDING => 'Suspension pending', self::SUSPENDED => 'Suspended', self::REACTIVATION_PENDING => 'Reactivation pending',
        self::CANCELLED => 'Cancelled', self::EXPIRED => 'Expired', self::LEGACY_PAST_DUE => 'Overdue',
    ];

    private const TRANSITIONS = [
        self::ACTIVE => [self::RENEWAL_DUE, self::GRACE, self::OVERDUE, self::CANCELLED],
        self::RENEWAL_DUE => [self::ACTIVE, self::GRACE, self::OVERDUE, self::CANCELLED],
        self::GRACE => [self::ACTIVE, self::OVERDUE, self::SUSPENSION_PENDING, self::CANCELLED],
        self::OVERDUE => [self::ACTIVE, self::SUSPENSION_PENDING, self::SUSPENDED, self::CANCELLED, self::EXPIRED],
        self::LEGACY_PAST_DUE => [self::ACTIVE, self::GRACE, self::OVERDUE, self::SUSPENSION_PENDING, self::CANCELLED],
        self::SUSPENSION_PENDING => [self::ACTIVE, self::SUSPENDED, self::OVERDUE, self::CANCELLED],
        self::SUSPENDED => [self::REACTIVATION_PENDING, self::CANCELLED, self::EXPIRED],
        self::REACTIVATION_PENDING => [self::ACTIVE, self::SUSPENDED, self::CANCELLED],
        self::CANCELLED => [],
        self::EXPIRED => [],
    ];

    /** States in which the renewal invoice is (or should be) outstanding. */
    public const UNPAID_STATES = [self::RENEWAL_DUE, self::GRACE, self::OVERDUE, self::LEGACY_PAST_DUE, self::SUSPENSION_PENDING, self::SUSPENDED];

    /** States the daily billing run must look at. */
    public const OPEN_STATES = [self::ACTIVE, self::RENEWAL_DUE, self::GRACE, self::OVERDUE, self::LEGACY_PAST_DUE, self::SUSPENSION_PENDING];

    public static function canTransition(string $from, string $to): bool
    {
        return $from === $to || in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assert(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new InvalidArgumentException("Invalid subscription transition {$from} → {$to}.");
        }
    }

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }
}
