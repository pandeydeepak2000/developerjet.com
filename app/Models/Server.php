<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $guarded = [];

    protected $hidden = ['ssh_credential'];

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
