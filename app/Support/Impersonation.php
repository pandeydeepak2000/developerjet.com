<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "View as client": a staff member with customers.impersonate is viewing a customer's dashboard.
 * Only true when the session was started by UserManagerController::impersonate AND the same staff
 * member is still signed in on the staff guard AND still holds the permission.
 */
class Impersonation
{
    public static function active(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }
        $session = $request->session();
        $staffId = $session->get('impersonator_id');
        $customerId = $session->get('impersonated_user_id');
        if (! $staffId || ! $customerId || (int) Auth::guard('web')->id() !== (int) $customerId) {
            return false;
        }
        $staff = Auth::guard('staff')->user();

        return $staff !== null
            && (int) $staff->getAuthIdentifier() === (int) $staffId
            && $staff->hasPermission('customers.impersonate');
    }
}
