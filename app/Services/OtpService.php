<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email OTP generation and verification.
 *
 * Defaults (10 min expiry, 5 attempts, 60s resend cooldown, 5 sends/hour) are engineering proposals
 * (SRS section 7), not owner-approved business terms. Codes are stored only as HMAC-SHA256 hashes.
 */
class OtpService
{
    public const PURPOSE = 'email_verification';

    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_SENDS_PER_HOUR = 5;

    public const OK = 'ok';

    public const SENT = 'sent';

    public const LOGGED_ONLY = 'logged_only';

    public const FAILED = 'failed';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const LOCKED = 'locked';

    public const MISSING = 'missing';

    /**
     * Returns seconds to wait before another code may be sent, or 0 when a code may be sent now.
     */
    public function waitSeconds(User $user): int
    {
        $latest = $user->otps()->where('purpose', self::PURPOSE)->latest('id')->first();

        if ($latest) {
            $remaining = self::RESEND_COOLDOWN_SECONDS - (int) $latest->created_at->diffInSeconds(now(), true);
            if ($remaining > 0) {
                return $remaining;
            }
        }

        $recent = $user->otps()->where('purpose', self::PURPOSE)->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= self::MAX_SENDS_PER_HOUR) {
            return 3600;
        }

        return 0;
    }

    /**
     * Issue a fresh code (invalidating older ones) and email it.
     * Returns false if the mailer failed; the code is never logged by this class.
     */
    public function issue(User $user, ?string $ip = null): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->otps()->where('purpose', self::PURPOSE)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $user->otps()->create([
            'purpose' => self::PURPOSE,
            'code_hash' => $this->hash($user, $code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => $ip,
        ]);

        return $this->issueWithStatus($user, $ip, $code) !== self::FAILED;
    }

    /**
     * Send a code issued by issue() and report what really happened: SENT only when an SMTP
     * transport accepted it, LOGGED_ONLY when the log mailer merely recorded it (never
     * describe that as sent), FAILED when the transport threw. The code is never logged here.
     * The array mailer is Laravel's in-memory test transport, captured by Mail::fake() in
     * tests, so it is not treated as log-only; it is never configured for real delivery.
     */
    public function issueWithStatus(User $user, ?string $ip = null, ?string $code = null): string
    {
        if ($code === null) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->otps()->where('purpose', self::PURPOSE)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $user->otps()->create([
                'purpose' => self::PURPOSE,
                'code_hash' => $this->hash($user, $code),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'ip_address' => $ip,
            ]);
        }
        $mailer = app(MailDeliveryService::class)->effectiveMailer();
        try {
            Mail::to($user->email)->send(new OtpMail($user, $code, self::TTL_MINUTES));
        } catch (Throwable $e) {
            Log::error('OTP email could not be sent.', ['user_id' => $user->id, 'error' => $e::class]);

            return self::FAILED;
        }

        return $mailer === 'log' ? self::LOGGED_ONLY : self::SENT;
    }

    public function verify(User $user, string $code): string
    {
        $otp = $user->otps()->where('purpose', self::PURPOSE)->whereNull('consumed_at')->latest('id')->first();

        if (! $otp) {
            return self::MISSING;
        }

        if ($otp->expires_at->isPast()) {
            return self::EXPIRED;
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return self::LOCKED;
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, $this->hash($user, trim($code)))) {
            return self::INVALID;
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return self::OK;
    }

    private function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$code, (string) config('app.key'));
    }
}
