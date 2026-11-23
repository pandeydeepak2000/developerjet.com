<?php

namespace App\Services\Provisioning;

use App\Models\Order;
use App\Models\Server;
use RuntimeException;

class NullAutomaticProvisioner implements AutomaticProvisioner
{
    public function isVerified(): bool
    {
        return false;
    }

    public function allocate(Order $order): Server
    {
        throw new RuntimeException('Automatic provisioning has no verified provider or hypervisor integration.');
    }

    public function release(Server $server): void
    {
        throw new RuntimeException('Automatic provisioning has no verified provider or hypervisor integration.');
    }
}
