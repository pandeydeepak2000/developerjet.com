<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\StaffPortal;
use App\Support\StaffSecurity;
use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin area gate. Uses the separate `staff` session guard, so a customer-area sign-in (web guard) never opens
 * the admin area. Requires an active staff account, a current session epoch (revocable), and — in order — a
 * required password change and MFA enrolment. Visitors without a staff session get 404 unless their browser
 * previously completed a staff sign-in, in which case they are sent to the current login URL.
 *
 * Implements AuthenticatesRequests so Laravel runs it with authentication priority: before throttling (limits are
 * keyed per staff user, not per shared office IP) and before route-model binding (no model lookups for outsiders).
 */
class EnsureStaff implements AuthenticatesRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('staff');
        /** @var User|null $user */
        $user = $guard->user();

        if ($user && (! $user->isStaff() || ! StaffSecurity::sessionIsCurrent($request, $user))) {
            StaffSecurity::endStaffSession($request);
            $user = null;
        }

        if (! $user) {
            if ($request->isMethod('GET') && $request->cookie(config('staff.hint_cookie'))) {
                $request->session()->put('url.intended', $request->fullUrl());

                return redirect()->to(StaffPortal::url('login'));
            }

            abort(404);
        }

        // From here on auth()->user() / $request->user() resolve to the staff account. The previous default guard is
        // restored afterwards so a long-lived worker (or the test kernel) never carries the staff guard into the
        // next request. Views are rendered inside $next(), before the restore.
        StaffSecurity::touchSession($request);

        $previous = Auth::getDefaultDriver();
        Auth::shouldUse('staff');

        try {
            $exempt = ['admin.logout', 'admin.profile*', 'admin.security*'];

            if ($user->password_change_required && ! $request->routeIs(...$exempt)) {
                return redirect()->route('admin.profile')->with('error', 'Choose a new password to continue.');
            }

            if (StaffSecurity::mfaRequired($user) && ! $user->hasTwoFactor() && ! $request->routeIs(...$exempt)) {
                return redirect()->route('admin.security')->with('error', 'Two-factor authentication is required for your account. Set it up to continue.');
            }

            return $next($request);
        } finally {
            Auth::shouldUse($previous);
        }
    }
}
