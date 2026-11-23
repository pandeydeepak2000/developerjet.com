<?php

namespace App\Services;

use App\Support\Setting;

/**
 * Tax is entirely configuration-driven: rates are stored per tax class in settings ("tax.rate.<class>",
 * a percentage such as 18 or 12.5). If no rate is configured the tax is 0 and `configured()` is false, so the UI
 * says tax rules are pending instead of silently implying "zero GST". Pending professional tax review.
 */
class TaxService
{
    public const CLASSES = ['standard' => 'Standard rate', 'reduced' => 'Reduced rate', 'exempt' => 'Exempt / zero-rated', 'wallet_topup' => 'Wallet deposit (pending tax review)'];

    public function configured(string $class): bool
    {
        return $class === 'exempt' || Setting::has('tax.rate.'.$class);
    }

    /** Rate in basis points (18% = 1800). */
    public function basisPoints(string $class): int
    {
        if ($class === 'exempt') {
            return 0;
        }

        return (int) round(((float) Setting::get('tax.rate.'.$class, 0)) * 100);
    }

    public function compute(int $netMinor, string $class): int
    {
        return intdiv($netMinor * $this->basisPoints($class) + 5000, 10000);
    }
}
