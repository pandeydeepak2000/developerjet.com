<?php

namespace App\Services\Infrastructure;

use App\Models\Provider;

class AdapterManager
{
    public function for(?Provider $provider): ProviderAdapter
    {
        return match ($provider?->provisioning_method) {
            'proxmox' => app(ProxmoxAdapter::class),
            default => app(ManualAdapter::class), // 'api' providers fall back to manual until an adapter is built and verified
        };
    }
}
