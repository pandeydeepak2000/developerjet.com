<?php

namespace App\Http\Middleware;

use App\Support\StaffPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the currently configured slug answers under /staff/{portal}/…; any other value (including retired
 * slugs) is a plain 404 with no redirect, so there is no loop and nothing to enumerate.
 */
class ResolveStaffPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $given = (string) $request->route('portal');

        abort_unless(hash_equals(StaffPortal::slug(), $given), 404);

        $request->route()->forgetParameter('portal');

        return $next($request);
    }
}
