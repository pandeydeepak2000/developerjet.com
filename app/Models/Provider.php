<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A VPS/dedicated supplier. Holds NO credentials: master API keys never live in this table. */
class Provider extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['verified_capabilities' => 'array', 'is_active' => 'boolean'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProviderOffer::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /** A capability counts only when the provider has a verified API and the capability was explicitly verified. */
    public function supports(string $capability): bool
    {
        return $this->api_status === 'verified'
            && $this->provisioning_method !== 'manual'
            && in_array($capability, $this->verified_capabilities ?? [], true);
    }
}
