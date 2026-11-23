<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationRequest extends Model
{
    public const LABELS = [
        'pending' => 'Awaiting review', 'approved' => 'Approved — cancels at period end', 'rejected' => 'Not approved',
        'withdrawn' => 'Withdrawn', 'completed' => 'Completed',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'effective_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? ucfirst($this->status);
    }
}
