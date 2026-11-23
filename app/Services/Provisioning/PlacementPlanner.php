<?php

namespace App\Services\Provisioning;

use App\Models\ResourceProfile;
use App\Models\Server;
use Illuminate\Support\Collection;

/** Capacity planning and placement validation. Pure functions over inventory; used by manual validation and future automatic mode. */
class PlacementPlanner
{
    /** Why a server cannot host a profile, or null when it fits. */
    public function incompatibility(Server $server, ?ResourceProfile $profile): ?string
    {
        if ($profile === null || $profile->vcpu === null || $profile->ram_gb === null || $profile->disk_gb === null) {
            return 'The plan has no confirmed resource profile yet, so compatibility cannot be validated.';
        }

        if ($server->status !== 'available') {
            return 'Server is not available (status: '.$server->status.').';
        }

        if ($server->role !== 'customer_vps') {
            return 'Server role is not a dedicated customer VPS.';
        }

        if ($server->assigned_order_id !== null || $server->assigned_user_id !== null) {
            return 'Server is already assigned.';
        }

        foreach ([['vCPU', $server->cpu_capacity, $profile->vcpu], ['RAM (GB)', $server->ram_capacity_gb, $profile->ram_gb], ['storage (GB)', $server->storage_capacity_gb, $profile->disk_gb]] as [$label, $have, $need]) {
            if ($have < $need) {
                return "Insufficient {$label}: server has {$have}, plan needs {$need}.";
            }
        }

        return null;
    }

    /** Best-fit (smallest sufficient) server from a pool; null if none fits. */
    public function pick(Collection $servers, ?ResourceProfile $profile): ?Server
    {
        return $servers->filter(fn (Server $s) => $this->incompatibility($s, $profile) === null)
            ->sortBy(fn (Server $s) => [$s->cpu_capacity, $s->ram_capacity_gb, $s->storage_capacity_gb])
            ->first();
    }
}
