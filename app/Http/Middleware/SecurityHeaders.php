<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        // Non-breaking subset of CSP (no script/style restrictions: inline scripts exist).
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Authenticated/account pages must not be cached by shared caches or the back button.
        if ($request->user() || $request->is('admin', 'admin/*', 'staff/*')) {
            // Staff portal pages must never leak the login slug through Referer either.
            if ($request->is('staff/*')) {
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
