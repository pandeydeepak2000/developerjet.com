<?php

namespace App\Support;

/**
 * Sanitising for free text submitted by the public. Output is always escaped by Blade as well; this removes markup,
 * control characters and anything that looks like a credential before it is stored or forwarded (email/Telegram).
 * UTF-8 safe (multilingual text and Unicode punctuation are kept).
 */
class SafeText
{
    /** Patterns for things customers must never send us: passwords, API keys, bot tokens, private keys. */
    private const SECRET_PATTERNS = [
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?(-----END [A-Z ]*PRIVATE KEY-----|$)/su',
        '/\b(sk|pk|rk)[-_](live|test|proj|ant)?[-_]?[A-Za-z0-9_\-]{16,}/u',       // OpenAI / Stripe / Anthropic style keys
        '/\bAKIA[0-9A-Z]{16}\b/u',                                                 // AWS access key id
        '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/u',                                        // GitHub tokens
        '/\bxox[abposr]-[A-Za-z0-9-]{10,}/u',                                       // Slack tokens
        '/\bAIza[0-9A-Za-z_\-]{30,}/u',                                             // Google API keys
        '/\b\d{6,12}:[A-Za-z0-9_\-]{30,}\b/u',                                      // Telegram bot tokens
        // "password: x", "api key = x", "my token is x" — a separator is required so ordinary prose is kept.
        '/\b(password|passwd|pwd|passcode|api[ _-]?key|secret[ _-]?key|client[ _-]?secret|access[ _-]?token|auth[ _-]?token|bot[ _-]?token|otp)\b\s*(?:=|:|\bis\b)\s*[^\s,;]{4,}/iu',
    ];

    public static function line(?string $value, int $max = 200): ?string
    {
        $clean = self::clean($value, false);

        return $clean === null ? null : mb_substr($clean, 0, $max);
    }

    public static function block(?string $value, int $max = 5000): ?string
    {
        $clean = self::clean($value, true);

        return $clean === null ? null : mb_substr($clean, 0, $max);
    }

    /** @return array{0: ?string, 1: bool} [text with secrets replaced, whether anything was redacted] */
    public static function redactSecrets(?string $value): array
    {
        if ($value === null) {
            return [null, false];
        }
        $count = 0;
        foreach (self::SECRET_PATTERNS as $pattern) {
            $value = preg_replace($pattern, '[removed for your security]', $value, -1, $n) ?? $value;
            $count += $n;
        }

        return [$value, $count > 0];
    }

    private static function clean(?string $value, bool $multiline): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        // Control characters (keep newlines/tabs in multi-line text), zero-width and bidi-override characters.
        $value = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value) ?? '';
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = $multiline
            ? preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/u', ' ', $value) ?? '') ?? ''
            : preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
