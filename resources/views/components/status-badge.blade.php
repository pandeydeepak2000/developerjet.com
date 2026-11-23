@props(['status'])
@php
    $tone = match (true) {
        in_array($status, ['active', 'paid', 'verified', 'succeeded', 'completed', 'approved', 'credited', 'sent', 'issued', 'available', 'ok', 'answered', 'accepted']) => 'good',
        in_array($status, ['failed', 'cancelled', 'rejected', 'declined', 'amount_mismatch', 'fail', 'retired', 'overdue', 'past_due', 'unpaid', 'suspended', 'expired']) => 'bad',
        in_array($status, ['pending', 'pending_payment', 'awaiting_provisioning', 'pending_provisioning', 'awaiting_approval', 'refund_pending', 'open', 'customer_reply', 'submitted', 'queued', 'warn', 'created', 'quoted', 'dry_run_ok', 'renewal_due', 'grace_period', 'suspension_pending', 'reactivation_pending']) => 'warn',
        default => 'neutral',
    };
@endphp
<span class="badge badge-{{ $tone }}">{{ \Illuminate\Support\Str::headline($status) }}</span>
