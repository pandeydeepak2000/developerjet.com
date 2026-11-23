<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** NULL approval (rows created before per-period approval existed) inherits the plan's price status. */
    public function isApproved(): bool
    {
        return $this->approval_status === null || $this->approval_status === 'approved';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
