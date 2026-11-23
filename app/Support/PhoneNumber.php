<?php

namespace App\Support;

/**
 * Small, dependency-free international phone helper. Stores numbers as E.164 ("+919876543210").
 * Validation is structural (country code from config/phone.php + national-number pattern), not a carrier lookup,
 * and no SMS verification is performed (owner decision: email OTP only).
 */
class PhoneNumber
{
    /** Strip spaces, dashes, dots and brackets; drop a single leading trunk "0". */
    public static function nationalDigits(string $input): string
    {
        $digits = preg_replace('/[\s\-().]/', '', trim($input)) ?? '';

        return ctype_digit($digits) ? ltrim($digits, '0') : $digits;
    }

    /** @return string|null E.164 number, or null when invalid */
    public static function normalise(?string $countryCode, ?string $national): ?string
    {
        $countries = config('phone.countries');
        if (! is_string($countryCode) || ! isset($countries[$countryCode]) || ! is_string($national)) {
            return null;
        }

        $digits = self::nationalDigits($national);
        // A pasted international number with the same code ("+91 98765…" / "91 98765…") is accepted.
        $codeDigits = substr($countryCode, 1);
        if (str_starts_with(preg_replace('/[\s\-().]/', '', $national) ?? '', '+')) {
            $raw = ltrim(preg_replace('/[\s\-().]/', '', $national) ?? '', '+');
            if (! str_starts_with($raw, $codeDigits)) {
                return null;
            }
            $digits = substr($raw, strlen($codeDigits));
        }

        if (! ctype_digit($digits) || strlen($digits) < 4 || strlen($digits) > 14 || strlen($codeDigits.$digits) > 15) {
            return null;
        }

        $pattern = $countries[$countryCode][1] ?? null;
        if ($pattern && ! preg_match($pattern, $digits)) {
            return null;
        }

        return $countryCode.$digits;
    }

    /** @return array{0: string, 1: string} [country code, national number] for pre-filling forms */
    public static function split(?string $e164): array
    {
        $default = (string) config('phone.default', '+91');
        if (! $e164) {
            return [$default, ''];
        }
        $codes = array_keys(config('phone.countries'));
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a)); // longest match first (+971 before +97…)
        foreach ($codes as $code) {
            if (str_starts_with($e164, $code)) {
                return [$code, substr($e164, strlen($code))];
            }
        }

        return [$default, ltrim($e164, '+')];
    }

    public static function display(?string $e164): string
    {
        if (! $e164) {
            return '';
        }
        [$code, $national] = self::split($e164);

        return $code === '+91' && strlen($national) === 10 ? '+91 '.substr($national, 0, 5).' '.substr($national, 5) : $code.' '.$national;
    }
}
