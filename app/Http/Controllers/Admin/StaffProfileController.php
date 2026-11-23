<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\StaffPasswordController;
use App\Models\AuditLog;
use App\Support\Audit;
use App\Support\StaffSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The signed-in staff member's own profile, password, sessions and sensitive-action confirmation. */
class StaffProfileController extends Controller
{
    public const LOGIN_ACTIONS = ['admin.login', 'admin.login_failed', 'admin.login_throttled', 'admin.logout', 'admin.mfa_failed'];

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('admin.profile', [
            'user' => $user->load('staffRole', 'departments'),
            'history' => AuditLog::where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)
                ->whereIn('action', self::LOGIN_ACTIONS)->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $request->user()->forceFill(['name' => $data['name']])->save();
        Audit::log('staff.profile_updated', $request->user());

        return back()->with('success', 'Profile saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', StaffPasswordController::rule(), 'different:current_password'],
        ]);
        $this->throttle('staff-password|'.$user->id, 'current_password');

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            RateLimiter::hit('staff-password|'.$user->id, 300);
            throw ValidationException::withMessages(['current_password' => 'Your current password is not correct.']);
        }

        $user->forceFill(['password' => $data['password'], 'password_change_required' => false])->save();
        // Other staff sessions of this account end; this one continues.
        StaffSecurity::revokeSessions($user, $request);
        StaffSecurity::markConfirmed($request);
        Audit::log('admin.password_changed', $user, [], $user->id);

        return redirect()->route('admin.profile')->with('success', 'Password changed. Your other staff sessions were signed out.');
    }

    public function signOutOthers(Request $request): RedirectResponse
    {
        StaffSecurity::revokeSessions($request->user(), $request);
        Audit::log('admin.sessions_revoked', $request->user(), ['scope' => 'self_others']);

        return back()->with('success', 'All other staff sessions for your account were signed out.');
    }

    public function confirmForm(Request $request): View
    {
        return view('admin.confirm-access', ['mfa' => $request->user()->hasTwoFactor()]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['password' => ['required', 'string'], 'code' => ['nullable', 'string', 'max:20']]);
        $this->throttle('staff-reauth|'.$user->id, 'password');

        if (! StaffSecurity::confirmIdentity($user, $data['password'], $data['code'] ?? null)) {
            RateLimiter::hit('staff-reauth|'.$user->id, 300);
            Audit::log('admin.reauth_failed', $user, [], $user->id);
            throw ValidationException::withMessages(['password' => $user->hasTwoFactor() ? 'Password or code is not valid.' : 'Your password is not correct.']);
        }

        RateLimiter::clear('staff-reauth|'.$user->id);
        StaffSecurity::markConfirmed($request);
        Audit::log('admin.reauth', $user, [], $user->id);

        $return = (string) $request->session()->pull('staff_reauth_return', route('admin.profile'));

        return redirect()->to(str_starts_with($return, url('/admin')) ? $return : route('admin.profile'))
            ->with('success', 'Confirmed. You can continue for '.config('staff.reauth_minutes').' minutes without re-entering your password.');
    }

    private function throttle(string $key, string $field): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([$field => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
    }
}
