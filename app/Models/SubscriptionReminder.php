<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionReminder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'sent_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function label(): string
    {
        [$type, $days] = array_pad(explode(':', $this->kind), 2, null);

        return match ($type) {
            'before' => "{$days} days before due",
            'due' => 'On the due date',
            'overdue' => "{$days} day(s) overdue",
            'suspension_warning' => 'Suspension warning',
            default => $this->kind,
        };
    }
}
