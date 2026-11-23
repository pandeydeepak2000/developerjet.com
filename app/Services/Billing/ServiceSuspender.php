<?php

namespace App\Services\Billing;

use App\Models\Service;

/**
 * Infrastructure hook for suspending / reactivating ONE service. Only a verified, owner-approved provider adapter may
 * return true from supports(). The default binding (NullServiceSuspender) supports nothing, so every suspension and
 * reactivation becomes an admin action task. Implementations must throw on failure — never pretend success.
 */
interface ServiceSuspender
{
    public function supports(Service $service): bool;

    public function suspend(Service $service): void;

    public function reactivate(Service $service): void;

    /** Post-reactivation health check. */
    public function healthy(Service $service): bool;
}
