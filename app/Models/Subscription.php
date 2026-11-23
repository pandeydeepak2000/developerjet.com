<?php

namespace App\Models;

use App\Support\BillingCycle;
use App\Support\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One recurring billing agreement per service. `period_end` is the paid-through date; `next_due_at` is when the
 * next payment is due (equal to period_end for billing in advance). `price_minor` is the AGREED recurring price
 * captured at purchase — plan price changes never alter it silently.
 */
class Subscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'next_due_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'last_processed_at' => 'datetime',
            'suspension_exempt' => 'boolean',
            'cancel_at_period_end' => 'boolean',
            'cancel_at' => 'datetime',
            'last_renewal_failed_at' => 'datetime',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function renewalOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'renewal_order_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->latest('id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(SubscriptionReminder::class)->latest('id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class);
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class)->latest('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(BillingAction::class)->latest('id');
    }

    /** The outstanding renewal invoice for the current due date, if any. */
    public function openInvoice(): ?Invoice
    {
        return $this->invoices()->where('type', 'invoice')->where('status', 'unpaid')->first();
    }

    public function scopeForCustomer(Builder $q, User $user): Builder
    {
        return $q->where('user_id', $user->id);
    }

    public function statusLabel(): string
    {
        return SubscriptionStatus::label($this->status);
    }

    public function cycleLabel(): string
    {
        return BillingCycle::label($this->billing_interval);
    }

    public function paidThrough()
    {
        return $this->period_end;
    }
}
