<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Staff-visible ticket activity (assignment, status, priority, department, escalation, SLA). Never shown to customers. */
class TicketEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function describe(): string
    {
        $m = $this->meta ?? [];

        return match ($this->type) {
            'opened' => 'Ticket opened',
            'assigned' => isset($m['to_name']) ? 'Assigned to '.$m['to_name'] : 'Unassigned',
            'status' => 'Status: '.($m['from'] ?? '?').' → '.($m['to'] ?? '?'),
            'priority' => 'Priority: '.($m['from'] ?? '?').' → '.($m['to'] ?? '?'),
            'department' => 'Department: '.($m['from'] ?? '?').' → '.($m['to'] ?? '?'),
            'escalated' => 'Escalated to level '.($m['level'] ?? '?').(isset($m['reason']) ? ' — '.$m['reason'] : ''),
            'sla_breached' => 'SLA target missed ('.($m['target'] ?? 'response').')',
            'first_response' => 'First staff response',
            'note' => 'Private note added',
            'reply' => 'Staff reply sent',
            'customer_reply' => 'Customer replied',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }
}
