@extends('layouts.admin')

@section('title', 'Subscription #'.$sub->id)
@section('heading', 'Subscription #'.$sub->id.' — '.($sub->service?->label ?? $sub->plan?->name ?? ''))

@section('actions')
    <a class="admin-btn admin-btn-quiet" href="{{ route('admin.billing.index') }}">Back to billing</a>
@endsection

@section('content')
    @php
        $status = $sub->status;
        $open = $sub->invoices->first(fn ($i) => $i->isUnpaid());
    @endphp
    <div class="admin-grid-2">
        <section class="admin-card">
            <h2>Billing</h2>
            <dl class="kv">
                <div><dt>Customer</dt><dd>{{ $sub->user?->name }} · {{ $sub->user?->email }}@if ($sub->user?->phone) · {{ $sub->user->phone }}@endif</dd></div>
                <div><dt>Service</dt><dd>{{ $sub->service?->label ?? '—' }} ({{ $sub->service?->status ?? 'no service' }})</dd></div>
                <div><dt>Server</dt><dd>{{ $sub->service?->server?->label ?? 'not assigned' }}</dd></div>
                <div><dt>Plan</dt><dd>{{ $sub->plan?->name ?? '—' }}</dd></div>
                <div><dt>Billing period</dt><dd>{{ $sub->cycleLabel() }} (anchor day {{ $sub->anchor_day ?? '—' }})</dd></div>
                <div><dt>Agreed price</dt><dd>{{ $sub->price_minor !== null ? \App\Support\Money::inr($sub->price_minor) : 'not captured (legacy) — uses approved plan price' }}{{ $sub->discount_percent ? ' · '.$sub->discount_percent.'% discount' : '' }}</dd></div>
                <div><dt>Current period</dt><dd>{{ $sub->period_start?->format('d M Y') }} – {{ $sub->period_end?->format('d M Y') }}</dd></div>
                <div><dt>Paid through</dt><dd>{{ $sub->period_end?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Next due</dt><dd>{{ $sub->next_due_at?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Grace ends</dt><dd>{{ $sub->grace_ends_at?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Status</dt><dd><x-status-badge :status="$status" /> @if ($sub->suspension_exempt)<small>(exempt from auto-suspension)</small>@endif</dd></div>
                <div><dt>Open renewal invoice</dt><dd>{{ $open ? $open->number.' · '.\App\Support\Money::inr($open->total_minor).' · due '.$open->due_at?->format('d M Y') : 'none' }}</dd></div>
            </dl>
        </section>

        <section class="admin-card">
            <h2>Actions</h2>
            @if (! $canEdit)
                <p class="muted">You can view this subscription but not change it (needs subscriptions.edit).</p>
            @else
                <p class="muted small">Nothing here contacts a server unless a verified adapter exists and live suspension is enabled. “Confirm” actions record what you did by hand. Data is never deleted.</p>
                <div class="admin-action-stack">
                    @if (in_array($status, ['active', 'renewal_due', 'grace_period', 'overdue', 'past_due'], true))
                        <form method="POST" action="{{ route('admin.billing.action', $sub) }}">@csrf<input type="hidden" name="action" value="invoice"><button class="admin-btn admin-btn-quiet" type="submit">Issue / show renewal invoice now</button></form>
                    @endif

                    <form method="POST" action="{{ route('admin.billing.action', $sub) }}">@csrf<input type="hidden" name="action" value="{{ $sub->suspension_exempt ? 'unexempt' : 'exempt' }}"><button class="admin-btn admin-btn-quiet" type="submit">{{ $sub->suspension_exempt ? 'Remove suspension exemption' : 'Exempt from auto-suspension' }}</button></form>

                    @if (in_array($status, ['grace_period', 'overdue', 'past_due'], true))
                        <form method="POST" action="{{ route('admin.billing.action', $sub) }}" class="admin-confirm-form">@csrf<input type="hidden" name="action" value="request_suspension">
                            <label class="admin-check"><input type="checkbox" name="confirm" value="1" required> Queue this service for suspension</label>
                            <input name="note" maxlength="300" placeholder="Reason (optional)" aria-label="Reason">
                            <button class="admin-btn" type="submit">Queue suspension</button></form>
                    @endif

                    @if (in_array($status, ['suspension_pending', 'overdue'], true))
                        <form method="POST" action="{{ route('admin.billing.action', $sub) }}" class="admin-confirm-form">@csrf<input type="hidden" name="action" value="confirm_suspended">
                            <label class="admin-check"><input type="checkbox" name="confirm" value="1" required> I have suspended this service on the provider (data kept)</label>
                            <input name="note" maxlength="300" placeholder="What was done (optional)" aria-label="Note">
                            <button class="admin-btn" type="submit">Confirm suspended</button></form>
                        <form method="POST" action="{{ route('admin.billing.action', $sub) }}" class="admin-confirm-form">@csrf<input type="hidden" name="action" value="suspend_adapter">
                            <label class="admin-check"><input type="checkbox" name="confirm" value="1" required> Use the verified adapter (only if one exists)</label>
                            <button class="admin-btn admin-btn-quiet" type="submit">Suspend via adapter</button></form>
                    @endif

                    @if ($status === 'reactivation_pending')
                        <form method="POST" action="{{ route('admin.billing.action', $sub) }}">@csrf<input type="hidden" name="action" value="reactivate_adapter"><button class="admin-btn admin-btn-quiet" type="submit">Reactivate via adapter (if verified)</button></form>
                        <form method="POST" action="{{ route('admin.billing.action', $sub) }}" class="admin-confirm-form">@csrf<input type="hidden" name="action" value="confirm_reactivated">
                            <label class="admin-check"><input type="checkbox" name="confirm" value="1" required> I reactivated the service and checked that it works</label>
                            <input name="note" maxlength="300" placeholder="Health check notes (optional)" aria-label="Note">
                            <button class="admin-btn" type="submit">Confirm reactivated</button></form>
                    @endif

                    @if (! in_array($status, ['cancelled', 'expired'], true))
                        <form method="POST" action="{{ route('admin.billing.action', $sub) }}" class="admin-confirm-form">@csrf<input type="hidden" name="action" value="cancel">
                            <label class="admin-check"><input type="checkbox" name="confirm" value="1" required> Cancel this subscription (no further renewals; nothing is deleted)</label>
                            <input name="note" maxlength="300" placeholder="Reason" aria-label="Cancellation reason">
                            <button class="admin-link-danger" type="submit">Cancel subscription</button></form>
                    @endif

                    @if ($canPrice)
                        <form method="POST" action="{{ route('admin.billing.action', $sub) }}" class="admin-confirm-form">@csrf<input type="hidden" name="action" value="price">
                            <label>New agreed price per period (₹)<input type="number" name="price" min="0" step="0.01" required></label>
                            <input name="note" maxlength="300" placeholder="Reason (required, recorded)" required aria-label="Reason for price change">
                            <button class="admin-btn admin-btn-quiet" type="submit">Change agreed price</button></form>
                    @endif
                </div>
            @endif
        </section>
    </div>

    <section class="admin-card">
        <h2>Invoices</h2>
        @if ($sub->invoices->isEmpty())<p class="muted">No invoices linked to this subscription yet.</p>@else
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>Number</th><th>Period</th><th>Total</th><th>Status</th><th>Due</th><th>Paid</th></tr></thead>
                <tbody>@foreach ($sub->invoices as $i)
                    <tr><td data-label="Number">{{ $i->number }}</td><td data-label="Period">{{ $i->period_start?->format('d M Y') }} – {{ $i->period_end?->format('d M Y') }}</td><td data-label="Total">{{ \App\Support\Money::inr($i->total_minor) }}</td><td data-label="Status"><x-status-badge :status="$i->status" /></td><td data-label="Due">{{ $i->due_at?->format('d M Y') ?? '—' }}</td><td data-label="Paid">{{ $i->paid_at?->format('d M Y') ?? '—' }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>

    <div class="admin-grid-2">
        <section class="admin-card">
            <h2>Recurring add-ons</h2>
            @if ($addons->isEmpty())<p class="muted">No add-ons on this subscription.</p>@else
                <ul class="admin-list">@foreach ($addons as $a)
                    <li><strong>{{ $a->name }}</strong> · {{ \App\Support\Money::inr($a->price_minor) }} per period · <x-status-badge :status="$a->status" />{{ $a->starts_at ? ' · billed from '.$a->starts_at->format('d M Y') : '' }}
                        @if ($a->status === 'active' && $canEdit)<form method="POST" action="{{ route('admin.billing.addons.destroy', [$sub, $a]) }}" class="admin-inline-form">@csrf @method('DELETE')<button class="admin-link-danger" type="submit">Stop billing from next renewal</button></form>@endif</li>
                @endforeach</ul>
            @endif
            @if ($canPrice && $availableAddons->isNotEmpty() && ! in_array($sub->status, ['cancelled', 'expired'], true))
                <form method="POST" action="{{ route('admin.billing.addons.store', $sub) }}" class="admin-form">@csrf
                    <label>Add-on<select name="addon_id">@foreach ($availableAddons as $ad)<option value="{{ $ad->id }}">{{ $ad->name }}</option>@endforeach</select></label>
                    <label>Agreed price per period (₹)<input type="number" name="price" min="0" step="0.01" required></label>
                    <p class="muted small">Billed from the next renewal invoice ({{ \App\Support\BillingSettings::addonMidcyclePolicy() === 'manual_invoice' ? 'any part-period charge must be raised manually' : 'no charge for the current period' }}). No proration is calculated automatically.</p>
                    <button class="admin-btn admin-btn-quiet" type="submit">Add add-on</button>
                </form>
            @elseif (! $canPrice)<p class="muted small">Adding add-ons needs pricing approval rights (plans.approve).</p>@endif
        </section>
        <section class="admin-card">
            <h2>Cancellation & renewal health</h2>
            @if ($sub->cancel_at_period_end)<p><span class="badge badge-warn">Ends {{ $sub->cancel_at?->format('d M Y') }}</span> Approved cancellation — no further renewal invoices.</p>@endif
            @if ($cancellations->isEmpty())<p class="muted">No cancellation requests.</p>@else
                <ul class="admin-list">@foreach ($cancellations as $c)<li>{{ $c->created_at->format('d M Y') }} · {{ $c->type === 'immediate' ? 'immediate' : 'end of period' }} · <x-status-badge :status="$c->status" />{{ $c->reviewer ? ' · '.$c->reviewer->name : '' }}@if ($c->review_note)<br><small class="muted">{{ $c->review_note }}</small>@endif</li>@endforeach</ul>
                <p><a href="{{ route('admin.billing.index', ['tab' => 'cancellations']) }}">Review requests</a></p>
            @endif
            @if ($sub->renewal_failures)
                <p class="admin-error">Renewal processing failed {{ $sub->renewal_failures }} time(s), last {{ $sub->last_renewal_failed_at?->format('d M Y H:i') }}: {{ $sub->last_renewal_error }}</p>
            @endif
            @if ($canEdit)
                <form method="POST" action="{{ route('admin.billing.retry', $sub) }}">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Run renewal processing now</button></form>
            @endif
        </section>
    </div>

    <div class="admin-grid-2">
        <section class="admin-card">
            <h2>Staff tasks</h2>
            @if ($actions->isEmpty())<p class="muted">None.</p>@else
                <ul class="admin-list">@foreach ($actions as $a)
                    <li><strong>{{ ucfirst($a->type) }}</strong> · <x-status-badge :status="$a->status" /> · {{ $a->created_at->format('d M Y') }}<br><small>{{ $a->reason }}</small>@if ($a->resolution_note)<br><small class="muted">{{ $a->resolution_note }} — {{ $a->resolver?->name ?? 'system' }}</small>@endif</li>
                @endforeach</ul>
            @endif
        </section>
        <section class="admin-card">
            <h2>Reminders</h2>
            @if ($reminders->isEmpty())<p class="muted">None sent yet.</p>@else
                <ul class="admin-list">@foreach ($reminders as $r)
                    <li>{{ $r->label() }} · due {{ $r->due_date->format('d M Y') }} · <x-status-badge :status="$r->status" />@if ($r->error)<br><small class="muted">{{ $r->error }}</small>@endif</li>
                @endforeach</ul>
            @endif
        </section>
    </div>

    <section class="admin-card">
        <h2>History</h2>
        @if ($events->isEmpty())<p class="muted">No history.</p>@else
            <ol class="timeline">@foreach ($events as $e)
                <li><time>{{ $e->created_at->format('d M Y H:i') }}</time> <strong>{{ ucfirst($e->type) }}</strong>@if ($e->from_status || $e->to_status) ({{ \App\Support\SubscriptionStatus::label($e->from_status) }} → {{ \App\Support\SubscriptionStatus::label($e->to_status) }})@endif — {{ $e->note }} <small class="muted">{{ $e->actor?->name ?? 'system' }}</small></li>
            @endforeach</ol>
        @endif
    </section>
@endsection
