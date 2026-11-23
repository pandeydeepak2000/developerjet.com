<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'emailed_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'billing_snapshot' => 'array',
            'payment_info' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isUnpaid(): bool
    {
        return $this->type === 'invoice' && $this->status === 'unpaid';
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
