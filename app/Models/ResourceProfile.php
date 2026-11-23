<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceProfile extends Model
{
    protected $guarded = [];

    /** Human-readable summary, or null when no resources are known (never invented). */
    public function summary(): ?string
    {
        if (! $this->vcpu || ! $this->ram_gb) {
            return null;
        }

        return "{$this->vcpu} vCPU · {$this->ram_gb} GB RAM";
    }
}
