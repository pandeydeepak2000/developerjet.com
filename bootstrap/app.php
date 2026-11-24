<?php

use App\Http\Middleware\BlockWritesWhileImpersonating;
use App\Http\Middleware\ConfirmStaffAccess;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResolveStaffPortal;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [SecurityHeaders::class]);
        // Reject requests whose Host header is not the configured app host (host-header poisoning of
        // generated URLs / reset links). Skipped for local/testing so dev and tests are unaffected.
        if (! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)) {
            $middleware->trustHosts(at: function () {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST);

                return $host ? ['^'.preg_quote($host, '/').'$'] : [];
            });
        }
        $middleware->alias([
            'staff' => EnsureStaff::class,
            'permission' => RequirePermission::class,
            'staff.portal' => ResolveStaffPortal::class,
            'staff.reauth' => ConfirmStaffAccess::class,
            'impersonation.readonly' => BlockWritesWhileImpersonating::class,
            // Same as Laravel's, but staff "View as client" sessions skip the customer email OTP.
            'verified' => EnsureEmailIsVerified::class,
        ]);
        // Gateway webhooks are authenticated by signature, not CSRF/session.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        // The admin area does not use the `auth` middleware (EnsureStaff guards it with the separate `staff` guard).
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Never flash secrets back into the session when a form fails validation.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'ai_api_key', 'setup']);
    })->create();
