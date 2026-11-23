<?php

namespace App\Http\Middleware;

use App\Support\StaffSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sensitive staff actions: the password (and authenticator code, if enrolled) must have been re-entered recently. */
class ConfirmStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (StaffSecurity::recentlyConfirmed($request)) {
            return $next($request);
        }

        // A POST cannot be replayed safely: send the user to confirm, then back to the page they came from.
        $return = $request->isMethod('GET') ? $request->fullUrl() : url()->previous(route('admin.profile'));
        $request->session()->put('staff_reauth_return', $return);

        return redirect()->route('admin.confirm-access')
            ->with('error', 'Confirm your password to continue'.($request->isMethod('GET') ? '.' : ', then repeat the action.'));
    }
}
