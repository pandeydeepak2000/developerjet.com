<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Supported billing periods and calendar maths. Keys are stored in plan_prices / order_items / subscriptions;
 * "yearly" is the historical key for the annual period and is kept for existing data.
 *
 * Month-end rule: a cycle remembers the day of month it started on (anchor). Each period end is anchor-day of the
 * target month, clamped to that month's length — so a subscription started on 31 Jan renews 28/29 Feb, then
 * 31 Mar (it does not drift to the 28th). Leap years follow from the real calendar (29 Feb 2028).
 */
class BillingCycle
{
    public const MONTHS = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'yearly' => 12];

    public const LABELS = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly (3 months)', 'semiannual' => 'Semiannual (6 months)', 'yearly' => 'Annual (12 months)'];

    public const SHORT = ['monthly' => 'month', 'quarterly' => '3 months', 'semiannual' => '6 months', 'yearly' => 'year'];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::MONTHS);
    }

    public static function months(string $interval): int
    {
        return self::MONTHS[$interval] ?? throw new InvalidArgumentException("Unsupported billing interval [{$interval}].");
    }

    public static function label(?string $interval): string
    {
        return self::LABELS[$interval] ?? ucfirst((string) $interval);
    }

    /** "Billed every 3 months" style phrase for carts, invoices and emails. */
    public static function billedPhrase(?string $interval): string
    {
        return match ($interval) {
            'monthly' => 'Billed monthly',
            'yearly' => 'Billed yearly',
            null, '' => '',
            default => 'Billed every '.(self::SHORT[$interval] ?? $interval),
        };
    }

    /**
     * End of the period that starts at $from, keeping the anchor day.
     */
    public static function advance(CarbonInterface $from, string $interval, ?int $anchorDay = null): Carbon
    {
        $months = self::months($interval);
        $anchorDay = $anchorDay ?: (int) $from->day;
        $from = Carbon::instance($from);

        $target = $from->copy()->startOfMonth()->addMonthsNoOverflow($months);
        $day = min($anchorDay, $target->daysInMonth);

        return $target->setDay($day)->setTime($from->hour, $from->minute, $from->second);
    }
}
