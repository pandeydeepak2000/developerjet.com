<?php

namespace App\Services\Billing;

use App\Models\Service;
use LogicException;

/** Default: no verified suspension adapter exists for any supplier yet. */
class NullServiceSuspender implements ServiceSuspender
{
    public function supports(Service $service): bool
    {
        return false;
    }

    public function suspend(Service $service): void
    {
        throw new LogicException('No verified suspension adapter is available.');
    }

    public function reactivate(Service $service): void
    {
        throw new LogicException('No verified reactivation adapter is available.');
    }

    public function healthy(Service $service): bool
    {
        return false;
    }
}
