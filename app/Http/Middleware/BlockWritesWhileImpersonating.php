<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** While an admin views the site as a customer, every write is blocked: impersonation is view-only. */
class BlockWritesWhileImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('impersonator_id') && ! $request->isMethod('GET')
            && ! $request->routeIs('admin.impersonation.exit') && ! $request->routeIs('logout')) {
            abort(403, 'Read-only while viewing as a customer. Exit impersonation to make changes.');
        }

        return $next($request);
    }
}
