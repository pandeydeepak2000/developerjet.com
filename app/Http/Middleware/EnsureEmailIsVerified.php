<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as BaseEnsureEmailIsVerified;
use Illuminate\Http\Request;

/**
 * Laravel's `verified` middleware, except while a staff member is viewing a customer's dashboard
 * ("View as client"). That session is read-only (BlockWritesWhileImpersonating), so letting staff see
 * an unverified customer's dashboard never sends an OTP and never notifies the customer.
 */
class EnsureEmailIsVerified extends BaseEnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if ($request instanceof Request && Impersonation::active($request)) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
