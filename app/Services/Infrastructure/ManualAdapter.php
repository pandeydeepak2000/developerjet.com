<?php

namespace App\Services\Infrastructure;

use App\Models\Server;

/** Suppliers without a verified API: inventory and manual assignment only. No remote control exists. */
class ManualAdapter implements ProviderAdapter
{
    public function key(): string
    {
        return 'manual';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function capabilities(): array
    {
        return [];
    }

    public function perform(Server $server, string $capability, array $arguments = []): array
    {
        throw new AdapterUnavailableException('This server is managed manually; no verified provider integration exists.');
    }
}
