<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\StaffPortal;
use App\Support\StaffSecurity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Settings → Staff portal (Super Admin only). Changing the slug requires the password and, when enrolled, an
 * authenticator code in the same request; the change is atomic and audited, and the old URL stops working at once.
 * Staff who are already signed in are not affected (sessions do not depend on the slug).
 */
class StaffPortalSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->isSuper(), 403, 'Only a Super Admin can manage the staff portal URL.');

        return view('admin.settings.staff-portal', [
            'slug' => StaffPortal::slug(),
            'currentUrl' => StaffPortal::url('login'),
            'base' => url('/staff'),
            'mfa' => $request->user()->hasTwoFactor(),
            'history' => AuditLog::with('user')->where('action', 'staff_portal.slug_changed')->latest('id')->limit(25)->get(),
            'min' => config('staff.slug_min'), 'max' => config('staff.slug_max'),
        ]);
    }

    /** Live validation for the preview (no state change). */
    public function check(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuper(), 403);
        $slug = strtolower(trim((string) $request->query('slug', '')));
        $error = StaffPortal::validationError($slug);

        return response()->json(['ok' => $error === null, 'error' => $error, 'url' => $error === null ? url(StaffPortal::path($slug)) : null]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSuper(), 403, 'Only a Super Admin can change the staff portal URL.');

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
            'code' => [$user->hasTwoFactor() ? 'required' : 'nullable', 'string', 'max:20'],
        ]);

        $key = 'staff-portal-change|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! StaffSecurity::confirmIdentity($user, $data['password'], $data['code'] ?? null)) {
            RateLimiter::hit($key, 600);
            throw ValidationException::withMessages(['password' => $user->hasTwoFactor() ? 'Password or authenticator code is not valid.' : 'Your password is not correct.']);
        }
        RateLimiter::clear($key);
        StaffSecurity::markConfirmed($request);

        try {
            $result = StaffPortal::change($data['slug'], $user, 'web');
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        return redirect()->route('admin.settings.staff-portal')->with('success',
            'Staff login URL changed to '.url(StaffPortal::path($result['new'])).' — the old URL ('.StaffPortal::path($result['old']).') no longer works. Share the new URL with your staff.');
    }
}
