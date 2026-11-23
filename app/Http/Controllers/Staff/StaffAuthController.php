<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Audit;
use App\Support\StaffPortal;
use App\Support\StaffSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The single staff sign-in for every staff role (Super Admin, Admin, Billing, Support, Sales, Developer, SEO,
 * custom roles) at /staff/{slug}/login. Steps: password → authenticator/recovery code (if enrolled) → one-time
 * email ownership check (accounts whose email is not yet verified) → staff session.
 * Customers can never authenticate here; failures are throttled per email+IP and audited.
 */
class StaffAuthController extends Controller
{
    private const PENDING = 'staff_pending';

    public function create(): View|RedirectResponse
    {
        $user = Auth::guard('staff')->user();
        if ($user?->isStaff() && StaffSecurity::sessionIsCurrent(request(), $user)) {
            return redirect()->to(StaffSecurity::landingUrl($user));
        }

        return view('staff.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:200']]);
        $email = strtolower($data['email']);
        $key = 'staff-login|'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            Audit::log('admin.login_throttled', null, ['email' => $email]);
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $user = User::where('email', $email)->first();
        // Always do the same hashing work so response time does not reveal whether the email exists.
        $hash = $user ? (string) $user->password : '$2y$12$'.str_repeat('a', 53);
        $ok = Hash::check($data['password'], $hash) && $user && $user->isStaff();

        if (! $ok) {
            RateLimiter::hit($key, 300);
            Audit::log('admin.login_failed', $user && $user->isStaffAccount() ? $user : null, ['email' => $email]);

            throw ValidationException::withMessages(['email' => 'These credentials do not match an active staff account.']);
        }

        RateLimiter::clear($key);

        if ($user->hasTwoFactor()) {
            $this->setPending($request, $user, 'mfa');

            return redirect()->to(StaffPortal::url('two-factor'));
        }

        return $this->afterSecondFactor($request, $user, ['mfa' => false]);
    }

    public function twoFactorForm(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request, 'mfa') ? view('staff.two-factor') : redirect()->to(StaffPortal::url('login'));
    }

    public function twoFactorVerify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request, 'mfa');
        if (! $user) {
            return redirect()->to(StaffPortal::url('login'));
        }

        $code = $request->validate(['code' => ['required', 'string', 'max:20']])['code'];
        $key = 'staff-mfa|'.$user->id.'|'.$request->ip();
        $userKey = 'staff-mfa-user|'.$user->id; // across all IPs

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($userKey, 10)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $method = StaffSecurity::verifySecondFactor($user, $code);

        if ($method === null) {
            RateLimiter::hit($key, 300);
            RateLimiter::hit($userKey, 1800);
            Audit::log('admin.mfa_failed', $user, [], $user->id);

            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($userKey);

        return $this->afterSecondFactor($request, $user, ['mfa' => $method]);
    }

    public function emailForm(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request, 'email') ? view('staff.verify-email') : redirect()->to(StaffPortal::url('login'));
    }

    public function emailVerify(Request $request, OtpService $otp): RedirectResponse
    {
        $user = $this->pendingUser($request, 'email');
        if (! $user) {
            return redirect()->to(StaffPortal::url('login'));
        }

        $code = $request->validate(['code' => ['required', 'string', 'max:12']])['code'];
        $result = $otp->verify($user, $code);

        if ($result !== OtpService::OK) {
            Audit::log('admin.email_verify_failed', $user, ['result' => $result], $user->id);
            throw ValidationException::withMessages(['code' => $result === OtpService::EXPIRED || $result === OtpService::LOCKED
                ? 'That code can no longer be used. Request a new one.' : 'That code is not valid.']);
        }

        Audit::log('admin.email_verified', $user, [], $user->id);

        return $this->finish($request, $user->fresh(), (array) $request->session()->get(self::PENDING.'.meta', []));
    }

    public function emailResend(Request $request, OtpService $otp): RedirectResponse
    {
        $user = $this->pendingUser($request, 'email');
        if (! $user) {
            return redirect()->to(StaffPortal::url('login'));
        }

        if (($wait = $otp->waitSeconds($user)) > 0) {
            return back()->withErrors(['code' => "Please wait {$wait} seconds before requesting another code."]);
        }

        $status = $otp->issueWithStatus($user, $request->ip());

        return match ($status) {
            OtpService::SENT => back()->with('status', 'A new code has been sent by email.'),
            OtpService::LOGGED_ONLY => back()->with('error', 'Email delivery is not configured (the current mailer only writes messages to a log), so the code was not delivered to your inbox. A Super Admin must configure SMTP in Settings - Email (SMTP), then request a new code.'),
            default => back()->with('error', 'We could not send the email. Please try again shortly; if it keeps failing, a Super Admin should test the SMTP settings.'),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        Audit::log('admin.logout', $request->user());
        StaffSecurity::endStaffSession($request);

        return redirect()->to(StaffPortal::url('login'))->with('status', 'You have been signed out.');
    }

    private function afterSecondFactor(Request $request, User $user, array $meta): RedirectResponse
    {
        // Ownership check: an account whose email was never verified (e.g. created by the CLI) must prove it.
        if (! $user->hasVerifiedEmail()) {
            $this->setPending($request, $user, 'email', $meta);
            $otp = app(OtpService::class);
            $status = null;
            if ($otp->waitSeconds($user) === 0) {
                $status = $otp->issueWithStatus($user, $request->ip());
            }

            $redirect = redirect()->to(StaffPortal::url('verify-email'));
            if ($status === OtpService::LOGGED_ONLY) {
                $redirect->with('error', 'Email delivery is not configured (the current mailer only writes messages to a log), so the code was not delivered to your inbox. A Super Admin must configure SMTP in Settings - Email (SMTP), then request a new code.');
            } elseif ($status === OtpService::FAILED) {
                $redirect->with('error', 'We could not send the email. Please try again shortly; if it keeps failing, a Super Admin should test the SMTP settings.');
            }

            return $redirect;
        }

        return $this->finish($request, $user, $meta);
    }

    private function finish(Request $request, User $user, array $meta): RedirectResponse
    {
        $request->session()->forget(self::PENDING);
        StaffSecurity::startSession($request, $user, $meta);

        return redirect()->intended(StaffSecurity::landingUrl($user));
    }

    private function setPending(Request $request, User $user, string $stage, array $meta = []): void
    {
        $request->session()->put(self::PENDING, [
            'id' => $user->id, 'stage' => $stage, 'meta' => $meta, 'expires' => now()->addMinutes(10)->timestamp,
        ]);
    }

    private function pendingUser(Request $request, string $stage): ?User
    {
        $pending = $request->session()->get(self::PENDING);

        if (! $pending || ($pending['stage'] ?? null) !== $stage || $pending['expires'] < time()) {
            return null;
        }

        $user = User::find($pending['id']);

        if (! $user || ! $user->isStaff()) {
            return null;
        }

        return $stage === 'mfa' && ! $user->hasTwoFactor() ? null : $user;
    }
}
