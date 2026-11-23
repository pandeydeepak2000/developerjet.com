<?php

namespace App\Services\Infrastructure;

use App\Models\Server;

/**
 * FUTURE Proxmox VE (KVM) adapter — an independent provider adapter, disabled by configuration.
 * Every operation fails closed. Nothing here connects to a node. Enabling needs: dedicated hardware, networking,
 * storage, a least-privilege API token (per-pool role, TLS verification) and a completed security test.
 *
 * Planned surface (documented, deliberately unimplemented): nodes, clusters, resource pools, VM templates, CPU /
 * memory / disk allocation, network + IP management, VM create, start / stop / reboot, console, snapshots, backups,
 * migration and capacity monitoring.
 */
class ProxmoxAdapter implements ProviderAdapter
{
    public const PLANNED_OPERATIONS = [
        'nodes', 'clusters', 'resource_pools', 'vm_templates', 'cpu_allocation', 'memory_allocation', 'disk_allocation',
        'network_ip_management', 'vm_create', 'power', 'web_console', 'snapshots', 'backups', 'vm_migration', 'capacity_monitoring',
    ];

    public function key(): string
    {
        return 'proxmox';
    }

    public function enabled(): bool
    {
        return false; // hard-disabled in this build, regardless of PROXMOX_ENABLED
    }

    public function capabilities(): array
    {
        return [];
    }

    public function perform(Server $server, string $capability, array $arguments = []): array
    {
        throw new AdapterUnavailableException('Proxmox KVM is not enabled. Dedicated hardware and security testing are not yet approved.');
    }
}
