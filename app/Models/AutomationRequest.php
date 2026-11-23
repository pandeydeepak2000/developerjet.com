<?php

namespace App\Models;

use App\Support\LeadStatus;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A Business Automation requirement submission (guest or customer) and its quotation lifecycle. */
class AutomationRequest extends Model
{
    protected $guarded = [];

    protected $hidden = ['submission_token', 'fingerprint', 'ip_hash'];

    protected function casts(): array
    {
        return [
            'redacted' => 'boolean',
            'follow_up_at' => 'datetime',
            'quoted_at' => 'datetime',
            'quote_sent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public const BUSINESS_SIZES = [
        'solo' => 'Just me',
        '2-10' => '2–10 people',
        '11-50' => '11–50 people',
        '51-200' => '51–200 people',
        '200+' => 'More than 200 people',
    ];

    public const TIMELINES = [
        'asap' => 'As soon as possible',
        '1-month' => 'Within a month',
        '1-3-months' => 'In 1–3 months',
        'exploring' => 'Just exploring',
    ];

    public const BUDGETS = [
        'not-sure' => 'Not sure yet',
        'under-25k' => 'Under ₹25,000',
        '25k-1l' => '₹25,000 – ₹1,00,000',
        '1l-5l' => '₹1,00,000 – ₹5,00,000',
        'above-5l' => 'Above ₹5,00,000',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function technicalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_owner_id');
    }

    public function followUpOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_owner_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AutomationRequestEvent::class)->orderBy('id');
    }

    public function customerEvents(): HasMany
    {
        return $this->hasMany(AutomationRequestEvent::class)->where('visibility', 'customer')->orderBy('id');
    }

    /** Leads a staff member may see: everything with leads.assign, otherwise only leads they own. */
    public function scopeVisibleTo(Builder $query, User $staff): Builder
    {
        if ($staff->hasPermission('leads.assign')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('assigned_to', $staff->id)
            ->orWhere('technical_owner_id', $staff->id)
            ->orWhere('follow_up_owner_id', $staff->id));
    }

    public function isVisibleTo(User $staff): bool
    {
        return $staff->hasPermission('leads.view') && ($staff->hasPermission('leads.assign')
            || in_array($staff->id, [$this->assigned_to, $this->technical_owner_id, $this->follow_up_owner_id], true));
    }

    public function statusLabel(): string
    {
        return LeadStatus::label($this->status);
    }

    public function phoneDisplay(): string
    {
        return PhoneNumber::display($this->phone);
    }

    public function summary(int $length = 280): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', $this->requirements) ?? '', $length);
    }
}
