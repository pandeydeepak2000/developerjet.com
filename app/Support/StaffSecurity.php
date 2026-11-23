<?php

namespace App\Support;

use App\Models\User;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Staff session, MFA and re-authentication helpers shared by the staff portal and the admin area. */
class StaffSecurity
{
    public const EPOCH_KEY = 'staff_epoch';

    public const REAUTH_KEY = 'staff_reauth_at';

    public const ACTIVITY_KEY = 'staff_last_activity';

    /** Super Admins (and everyone, when the setting is on) must use an authenticator app. */
    public static function mfaRequired(User $user): bool
    {
        return Setting::bool('security.require_staff_mfa')
            || ($user->isSuper() && (bool) config('staff.enforce_privileged_mfa'));
    }

    public static function sessionIsCurrent(Request $request, User $user): bool
    {
        if (! $request->hasSession()) {
            return false;
        }
        $activity = $request->session()->get(self::ACTIVITY_KEY);
        $expired = $activity !== null && time() - (int) $activity > 60 * max(1, (int) config('staff.session_lifetime_minutes', 480));

        return ! $expired
            && (int) $request->session()->get(self::EPOCH_KEY, -1) === (int) $user->staff_session_epoch;
    }

    /** Complete a staff sign-in on the staff guard only (the customer session, if any, is left alone). */
    public static function startSession(Request $request, User $user, array $meta = []): void
    {
        Auth::guard('staff')->login($user);
        $request->session()->regenerate();
        $request->session()->put(self::EPOCH_KEY, (int) $user->staff_session_epoch);
        $request->session()->put(self::REAUTH_KEY, time());
        $request->session()->put(self::ACTIVITY_KEY, time());
        $user->forceFill(['last_staff_login_at' => now(), 'last_staff_login_ip' => $request->ip()])->save();
        Audit::log('admin.login', $user, $meta, $user->id);

        Cookie::queue(Cookie::make((string) config('staff.hint_cookie'), '1', 60 * 24 * 180, null, null, null, true, false, 'lax'));
    }

    public static function endStaffSession(Request $request): void
    {
        Auth::guard('staff')->logout();

        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget([self::EPOCH_KEY, self::REAUTH_KEY, self::ACTIVITY_KEY, 'staff_pending', 'staff_reauth_return']);

        if (Auth::guard('web')->guest()) {
            $request->session()->invalidate();
        } else {
            $request->session()->migrate(true);
        }

        $request->session()->regenerateToken();
    }

    /** Invalidate every staff session of $user (they must sign in again). Keeps the caller's own session if asked. */
    public static function revokeSessions(User $user, ?Request $keepFor = null): void
    {
        $user->forceFill(['staff_session_epoch' => (int) $user->staff_session_epoch + 1])->save();

        if ($keepFor && $keepFor->hasSession()) {
            $keepFor->session()->put(self::EPOCH_KEY, (int) $user->staff_session_epoch);
        }
    }

    public static function recentlyConfirmed(Request $request): bool
    {
        $at = (int) $request->session()->get(self::REAUTH_KEY, 0);

        return $at > 0 && time() - $at <= 60 * (int) config('staff.reauth_minutes');
    }

    public static function markConfirmed(Request $request): void
    {
        $request->session()->put(self::REAUTH_KEY, time());
    }

    /** Sliding expiration: every authorised admin request renews the inactivity window, so staff are not logged out mid-navigation, while an idle session still expires after the configured lifetime. */
    public static function touchSession(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::ACTIVITY_KEY, time());
        }
    }

    /** Password + (TOTP or recovery code, when enrolled). Used for sensitive-action confirmation. */
    public static function confirmIdentity(User $user, string $password, ?string $code): bool
    {
        if (! Hash::check($password, (string) $user->password)) {
            return false;
        }

        return ! $user->hasTwoFactor() || self::verifySecondFactor($user, (string) $code) !== null;
    }

    /**
     * Verify a TOTP code (with replay protection) or a single-use recovery code.
     * Returns 'totp', 'recovery' or null.
     */
    public static function verifySecondFactor(User $user, string $code): ?string
    {
        $code = trim($code);
        $step = app(Totp::class)->verify((string) $user->two_factor_secret, $code, null, $user->two_factor_last_step);

        if ($step !== null) {
            // Compare-and-set: of two parallel requests with the same code, only one can advance the step.
            $claimed = User::whereKey($user->id)
                ->where(fn ($q) => $q->whereNull('two_factor_last_step')->orWhere('two_factor_last_step', '<', $step))
                ->update(['two_factor_last_step' => $step]);
            if ($claimed !== 1) {
                return null;
            }
            $user->forceFill(['two_factor_last_step' => $step])->syncOriginalAttribute('two_factor_last_step');

            return 'totp';
        }

        return self::consumeRecoveryCode($user, $code) ? 'recovery' : null;
    }

    /** @return array<int, string> plain codes — show once, never store. */
    public static function generateRecoveryCodes(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < (int) config('staff.recovery_codes'); $i++) {
            $codes[] = Str::upper(Str::random(5).'-'.Str::random(5));
        }

        $user->forceFill(['two_factor_recovery_codes' => json_encode(array_map(self::hashCode(...), $codes))])->save();

        return $codes;
    }

    public static function remainingRecoveryCodes(User $user): int
    {
        return count(json_decode((string) $user->two_factor_recovery_codes, true) ?: []);
    }

    public static function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalised = Str::upper(trim($code));
        if (! preg_match('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $normalised)) {
            return false;
        }

        $stored = User::whereKey($user->id)->value('two_factor_recovery_codes');
        $hashes = json_decode((string) $stored, true) ?: [];
        $given = self::hashCode($normalised);

        foreach ($hashes as $i => $hash) {
            if (hash_equals($hash, $given)) {
                unset($hashes[$i]);
                $remaining = json_encode(array_values($hashes));
                // Compare-and-set on the exact stored value: a parallel request using the same code loses.
                if (User::whereKey($user->id)->where('two_factor_recovery_codes', $stored)->update(['two_factor_recovery_codes' => $remaining]) !== 1) {
                    return false;
                }
                $user->forceFill(['two_factor_recovery_codes' => $remaining])->syncOriginalAttribute('two_factor_recovery_codes');
                Audit::log('admin.mfa_recovery_code_used', $user, ['remaining' => count($hashes)], $user->id);

                return true;
            }
        }

        return false;
    }

    public static function clearTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    private static function hashCode(string $code): string
    {
        return hash_hmac('sha256', Str::upper($code), (string) config('app.key'));
    }

    /** First page the staff member is allowed to open after sign-in. */
    public static function landingUrl(User $user): string
    {
        $candidates = [
            'dashboard.view' => 'admin.dashboard', 'tickets.view' => 'admin.tickets.index', 'orders.view' => 'admin.orders.index',
            'projects.view' => 'admin.projects.index', 'seo.view' => 'admin.seo.index', 'wallet.view' => 'admin.wallet.index',
            'subscriptions.view' => 'admin.billing.index', 'plans.view' => 'admin.plans.index', 'staff.view' => 'admin.staff.index',
        ];

        foreach ($candidates as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return route($route);
            }
        }

        return route('admin.profile');
    }
}
