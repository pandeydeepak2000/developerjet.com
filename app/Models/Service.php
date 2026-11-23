<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Service extends Model
{
    protected $guarded = [];

    /** Secrets never leave the model through serialization. */
    protected $hidden = ['ai_api_key'];

    protected function casts(): array
    {
        return [
            'renews_at' => 'datetime',
            'config' => 'array',
            'domain_verified' => 'boolean',
            'ai_api_key' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->latest('id');
    }

    /** Latest LIVE deployment only (dry runs never drive customer-facing progress). Cheap for list pages. */
    public function latestLiveDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->where('mode', 'live')->latestOfMany();
    }

    /** The recurring billing agreement for this service (one per service). */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /** Hermes services are "configuration incomplete" until an AI provider and key are supplied. */
    public function aiConfigured(): bool
    {
        return $this->ai_provider !== null && $this->getRawOriginal('ai_api_key') !== null;
    }
}
