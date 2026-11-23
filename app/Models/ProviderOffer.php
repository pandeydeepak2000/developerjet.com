<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderOffer extends Model
{
    protected $guarded = [];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** Gross margin % over purchase cost for the same billing period, or null when either figure is unknown. */
    public function marginPercent(): ?float
    {
        if (! $this->purchase_cost_minor || ! $this->selling_price_minor) {
            return null;
        }

        return round(($this->selling_price_minor - $this->purchase_cost_minor) / $this->selling_price_minor * 100, 1);
    }
}
