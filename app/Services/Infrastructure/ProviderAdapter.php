<?php

namespace App\Services\Infrastructure;

use App\Models\Server;

/**
 * One adapter per provider type. An adapter advertises ONLY capabilities it actually implements and has verified.
 * Provider master credentials never reach customers or support staff: adapters read them from secure server config.
 */
interface ProviderAdapter
{
    public function key(): string;

    /** True only when the adapter may talk to a real system. */
    public function enabled(): bool;

    /** @return array<int, string> keys from config('infrastructure.controls') that this adapter implements */
    public function capabilities(): array;

    /** @throws AdapterUnavailableException when disabled or unsupported */
    public function perform(Server $server, string $capability, array $arguments = []): array;
}
