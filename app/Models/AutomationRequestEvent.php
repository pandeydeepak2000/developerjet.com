<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** History entry on a Business Automation lead. visibility=internal is never shown to the customer. */
class AutomationRequestEvent extends Model
{
    protected $guarded = [];

    public function automationRequest(): BelongsTo
    {
        return $this->belongsTo(AutomationRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
