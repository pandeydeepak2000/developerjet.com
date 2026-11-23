<?php

namespace App\Services\Provisioning;

use App\Models\Order;
use App\Models\Server;

/**
 * Contract for future automatic VM creation (provider API or hypervisor). Implementations must return a real, dedicated VM
 * — containers are never presented as dedicated VPS. No implementation exists yet; see NullAutomaticProvisioner.
 */
interface AutomaticProvisioner
{
    public function isVerified(): bool;

    /** Create or reserve a server for the order; must be idempotent per order. */
    public function allocate(Order $order): Server;

    public function release(Server $server): void;
}
