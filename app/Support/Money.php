<?php

namespace App\Support;

class Money
{
    /** Format an amount stored in minor units (paise) as INR, e.g. 249900 => ₹2,499. */
    public static function inr(?int $minor): string
    {
        if ($minor === null) {
            return '—';
        }

        $rupees = intdiv($minor, 100);
        $paise = $minor % 100;

        return '₹'.number_format($rupees).($paise ? sprintf('.%02d', $paise) : '');
    }
}
