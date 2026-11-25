<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $guarded = [];

    protected $hidden = ['ssh_credential'];

    protected static function booted(): void
    {
        // Name and size are optional in the admin form: a blank name becomes the IP, a blank size stays 0 and is
        // filled from the order's plan when the server is assigned (see DeploymentService::assign).
        static::saving(function (Server $server) {
            if (blank($server->label)) {
                $server->label = (string) $server->ip_address;
            }
            foreach (['cpu_capacity', 'ram_capacity_gb', 'storage_capacity_gb'] as $column) {
                if ($server->{$column} === null || $server->{$column} === '') {
                    $server->{$column} = 0;
                }
            }
        });
    }

    /** No size entered: the VPS was bought for one order, so it takes that plan's size when assigned. */
    public function sizeUnknown(): bool
    {
        return (int) $this->cpu_capacity === 0 && (int) $this->ram_capacity_gb === 0 && (int) $this->storage_capacity_gb === 0;
    }

    protected function casts(): array
    {
        return [
            'ssh_credential' => 'encrypted',
            'renewal_date' => 'date',
            'suspension_requested_at' => 'datetime',
            'renewal_alerted_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function serverPool(): BelongsTo
    {
        return $this->belongsTo(ServerPool::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(ServerHealthCheck::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'assigned_order_id');
    }
}
