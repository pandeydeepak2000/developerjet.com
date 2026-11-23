<?php

namespace App\Services;

/** RFC 6238 time-based one-time passwords (SHA-1, 6 digits, 30 s) — compatible with common authenticator apps. */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        $bits = '';
        foreach (str_split($raw) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public function code(string $secret, int $step): string
    {
        $key = $this->decode($secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Returns the matched time step (for replay protection) or null. Accepts ±1 step of clock drift. */
    public function verify(string $secret, string $code, ?int $now = null, ?int $lastStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $current = intdiv($now ?? time(), 30);
        for ($step = $current - 1; $step <= $current + 1; $step++) {
            if (hash_equals($this->code($secret, $step), $code) && ($lastStep === null || $step > $lastStep)) {
                return $step;
            }
        }

        return null;
    }

    public function uri(string $secret, string $account, string $issuer = 'DeveloperJet'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    private function decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper($secret)) as $c) {
            $pos = strpos(self::ALPHABET, $c);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
