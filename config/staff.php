<?php

/*
 | Staff portal configuration. The live login slug is stored in the database (settings key
 | `staff_portal.slug`, changed by a Super Admin under Settings → Staff portal or by `php artisan staff:portal`).
 | The value below is only the fallback used when no slug has been saved yet.
 */
return [
    'default_slug' => env('STAFF_PORTAL_DEFAULT_SLUG', 'developerjet'),

    'slug_min' => 4,
    'slug_max' => 40,

    // Slugs that may never be used (too guessable or would read like system paths).
    'reserved_slugs' => [
        'admin', 'administrator', 'login', 'logout', 'signin', 'sign-in', 'staff', 'portal', 'api', 'auth',
        'root', 'system', 'dashboard', 'password', 'reset', 'reset-password', 'forgot-password', 'two-factor',
        'security', 'profile', 'settings', 'invitation', 'invite', 'assets', 'build', 'storage', 'public',
        'webhooks', 'test', 'default', 'null', 'undefined', 'wp-admin', 'cpanel', 'whm', 'whmcs',
    ],

    // Super Admin accounts must enrol an authenticator app before using the admin area.
    // Disabled only in the automated test environment (phpunit.xml); dedicated tests turn it on.
    'enforce_privileged_mfa' => (bool) env('STAFF_ENFORCE_PRIVILEGED_MFA', true),

    // Sensitive actions require the password (and authenticator code) again within this many minutes.
    // 480 = 8 hours: ek baar staff login kar le to din bhar baar-baar confirm nahi mangega.
    // Login par hi staff_reauth_at set hota hai (StaffSecurity::startSession).
    'reauth_minutes' => 480,

    // Configurable staff session lifetime in minutes (sliding inactivity window, default 8h workday).
    // The underlying Laravel session must live at least this long (see config/session.php / SESSION_LIFETIME).
    // Expiry, MFA, password-change and staff.reauth checks are unaffected and still enforced.
    'session_lifetime_minutes' => (int) env('STAFF_SESSION_LIFETIME', 480),

    'invitation_hours' => 72,

    'recovery_codes' => 10,

    // A browser that completed a staff sign-in gets a cookie so an expired session is sent back to the
    // current login URL; other visitors to /admin receive 404 (the area is not advertised).
    'hint_cookie' => 'dj_staff_portal',
];
