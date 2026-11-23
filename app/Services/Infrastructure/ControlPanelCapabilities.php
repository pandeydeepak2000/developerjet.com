<?php

namespace App\Services\Infrastructure;

use App\Models\Service;

/**
 * Decides which customer VPS controls are really available for a service. Anything not proven is reported as
 * unavailable with a plain reason: no fake buttons, no fake KVM console.
 */
class ControlPanelCapabilities
{
    public function __construct(private AdapterManager $adapters) {}

    /** @return array<string, array{label: string, available: bool, reason: ?string}> */
    public function for(Service $service): array
    {
        $server = $service->server;
        $provider = $server?->supplier;
        $adapter = $this->adapters->for($provider);
        $unmanaged = ($server?->access_model ?? 'managed') === 'unmanaged';
        $out = [];

        foreach (config('infrastructure.controls') as $key => $control) {
            [$available, $reason] = $this->decide($key, $control['scope'], $service, $provider, $adapter, $unmanaged);
            $out[$key] = ['label' => $control['label'], 'available' => $available, 'reason' => $available ? null : $reason];
        }

        return $out;
    }

    private function decide(string $key, string $scope, Service $service, $provider, ProviderAdapter $adapter, bool $unmanaged): array
    {
        if ($service->status !== 'active') {
            return [false, 'Available after your service is active.'];
        }

        if ($scope === 'application') {
            if ($unmanaged) {
                return [false, 'You manage your own applications over SSH.'];
            }

            return config('infrastructure.app_controls_enabled')
                ? [true, null]
                : [false, 'Not available yet: application controls have not been verified.'];
        }

        if (! $provider) {
            return [false, 'Not available: this server is administered manually by our team.'];
        }

        if (! $adapter->enabled()) {
            return [false, 'Not available: the infrastructure integration is not enabled.'];
        }

        if (! $provider->supports($key) || ! in_array($key, $adapter->capabilities(), true)) {
            return [false, 'Not available: no verified provider integration for this control. Contact support.'];
        }

        return [true, null];
    }
}
