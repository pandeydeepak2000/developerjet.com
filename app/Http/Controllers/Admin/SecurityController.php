<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Totp;
use App\Support\Audit;
use App\Support\StaffSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff two-factor (TOTP) enrolment, recovery codes and resets. The seed is stored encrypted and shown only while
 * enrolling (before confirmation); afterwards it is never displayed again — a lost device needs a reset.
 * Recovery codes are shown once and stored only as HMAC-SHA256 hashes.
 */
class SecurityController extends Controller
{
    public function show(Request $request, Totp $totp): View
    {
        $user = $request->user();
        $pending = ! $user->hasTwoFactor() && $user->two_factor_secret ? $user->two_factor_secret : null;

        return view('admin.security', [
            'enabled' => $user->hasTwoFactor(), 'secret' => $pending,
            'uri' => $pending ? $totp->uri($pending, $user->email) : null,
            'required' => StaffSecurity::mfaRequired($user),
            'remaining' => StaffSecurity::remainingRecoveryCodes($user),
            'codes' => $request->session()->get('recovery_codes'),
        ]);
    }

    public function start(Request $request, Totp $totp): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->hasTwoFactor(), 403);
        $user->forceFill(['two_factor_secret' => $totp->generateSecret(), 'two_factor_confirmed_at' => null])->save();

        return redirect()->route('admin.security');
    }

    public function confirm(Request $request, Totp $totp): RedirectResponse
    {
        $user = $request->user();
        $code = $request->validate(['code' => ['required', 'string', 'max:12']])['code'];
        abort_if($user->hasTwoFactor() || ! $user->two_factor_secret, 403);
        $this->throttle('mfa-confirm|'.$user->id);

        $step = $totp->verify((string) $user->two_factor_secret, $code);
        if ($step === null) {
            RateLimiter::hit('mfa-confirm|'.$user->id, 300);
            throw ValidationException::withMessages(['code' => 'That code is not valid. Check the time on your phone and try again.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $codes = StaffSecurity::generateRecoveryCodes($user);
        StaffSecurity::markConfirmed($request);
        Audit::log('admin.mfa_enabled', $user, [], $user->id);

        return redirect()->route('admin.security')->with('success', 'Two-factor authentication is on. Save your recovery codes now — they are shown only once.')
            ->with('recovery_codes', $codes);
    }

    /** New set of recovery codes; the old ones stop working. Requires password + current code. */
    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 403);
        $data = $request->validate(['password' => ['required', 'string'], 'code' => ['required', 'string', 'max:20']]);
        $this->throttle('mfa-codes|'.$user->id);

        if (! StaffSecurity::confirmIdentity($user, $data['password'], $data['code'])) {
            RateLimiter::hit('mfa-codes|'.$user->id, 300);
            throw ValidationException::withMessages(['code' => 'Password or code is not valid.']);
        }

        $codes = StaffSecurity::generateRecoveryCodes($user);
        Audit::log('admin.mfa_recovery_codes_rotated', $user, [], $user->id);

        return redirect()->route('admin.security')->with('success', 'New recovery codes generated. The previous codes no longer work.')
            ->with('recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['password' => ['required', 'string'], 'code' => ['required', 'string', 'max:20']]);

        if (StaffSecurity::mfaRequired($user)) {
            return back()->with('error', 'Two-factor authentication is mandatory for your account and cannot be turned off. Ask a Super Admin for a reset if you lost your device.');
        }

        $this->throttle('mfa-disable|'.$user->id);

        if (! StaffSecurity::confirmIdentity($user, $data['password'], $data['code'])) {
            RateLimiter::hit('mfa-disable|'.$user->id, 300);
            throw ValidationException::withMessages(['code' => 'Password or code is not valid.']);
        }

        StaffSecurity::clearTwoFactor($user);
        Audit::log('admin.mfa_disabled', $user, [], $user->id);

        return redirect()->route('admin.security')->with('success', 'Two-factor authentication is off.');
    }

    /**
     * MFA reset workflow for a lost authenticator: another staff member with staff.edit (recently re-authenticated)
     * resets it. Never possible on yourself; only a Super Admin can reset a Super Admin. All the target's staff
     * sessions end and they must enrol again at next sign-in (if MFA is required for them).
     */
    public function reset(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.edit') && $request->user()->id !== $user->id && $user->isStaffAccount(), 403);
        abort_if($user->isSuperAccount() && ! $request->user()->isSuper(), 403);

        StaffSecurity::clearTwoFactor($user);
        StaffSecurity::revokeSessions($user);
        Audit::log('admin.mfa_reset', $user, ['by' => $request->user()->id], $request->user()->id);

        return back()->with('success', 'Two-factor reset for '.$user->name.'. Their sessions were signed out and they must enrol again.');
    }

    private function throttle(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
    }
}
