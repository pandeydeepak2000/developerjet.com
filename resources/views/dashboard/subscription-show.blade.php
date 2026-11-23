@extends('layouts.dashboard')

@section('title', 'Subscription')
@section('heading', $sub->service?->label ?? $sub->plan?->name ?? 'Subscription')
@section('subheading', 'Billing details and renewal history.')

@section('dash')
    @php $open = $sub->invoices->first(fn ($i) => $i->isUnpaid()); @endphp
    @if ($open && $open->order_id)
        <div class="flash-warning" role="status">
            Renewal invoice <strong>{{ $open->number }}</strong> for {{ \App\Support\Money::inr($open->total_minor) }} is due on {{ $open->due_at?->format('d M Y') }}.
            <a href="{{ route('dashboard.orders.show', $open->order_id) }}">Renew now</a>
        </div>
    @endif

    <div class="panel">
        <div class="panel-head"><h2>Billing</h2><x-status-badge :status="$sub->status" /></div>
        <dl class="kv">
            <div><dt>Plan</dt><dd>{{ $sub->plan?->name ?? '—' }}</dd></div>
            <div><dt>Billing period</dt><dd>{{ $sub->cycleLabel() }}</dd></div>
            <div><dt>Agreed price</dt><dd>{{ $sub->price_minor !== null ? \App\Support\Money::inr($sub->price_minor) : '—' }} per period{{ $sub->discount_percent ? ' · '.$sub->discount_percent.'% discount' : '' }} (tax added where configured)</dd></div>
            <div><dt>Current period</dt><dd>{{ $sub->period_start?->format('d M Y') ?? '—' }} – {{ $sub->period_end?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt>Paid through</dt><dd>{{ $sub->period_end?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt>Next due</dt><dd>{{ in_array($sub->status, ['cancelled', 'expired'], true) ? '—' : ($sub->next_due_at?->format('d M Y') ?? '—') }}</dd></div>
            @if ($sub->grace_ends_at && in_array($sub->status, ['grace_period', 'overdue'], true))<div><dt>Grace period ends</dt><dd>{{ $sub->grace_ends_at->format('d M Y') }}</dd></div>@endif
            <div><dt>Status</dt><dd>{{ $sub->statusLabel() }}</dd></div>
        </dl>
        @if ($sub->service)<p><a href="{{ route('dashboard.services.show', $sub->service) }}">Open the service page</a></p>@endif
    </div>

    @if ($sub->cancel_at_period_end && $sub->cancel_at && ! in_array($sub->status, ['cancelled', 'expired'], true))
        <div class="flash-warning" role="status">This subscription is set to end on <strong>{{ $sub->cancel_at->format('d M Y') }}</strong> and will not renew. Your service keeps working until then.</div>
    @endif

    @if ($addons->isNotEmpty())
        <div class="panel">
            <h2>Add-ons</h2>
            <ul class="plain-list">@foreach ($addons as $a)<li>{{ $a->name }} — {{ \App\Support\Money::inr($a->price_minor) }} per period{{ $a->starts_at && $a->starts_at->isFuture() ? ' (billed from '.$a->starts_at->format('d M Y').')' : '' }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($canCancel || $cancellation || $lastCancellation)
        <div class="panel" id="cancel">
            <h2>Cancellation</h2>
            @if ($cancellation)
                <p><x-status-badge :status="$cancellation->status" /> {{ $cancellation->label() }}{{ $cancellation->effective_at ? ' · ends '.$cancellation->effective_at->format('d M Y') : '' }}</p>
                <form method="POST" action="{{ route('dashboard.subscriptions.cancel.withdraw', [$sub, $cancellation]) }}">@csrf
                    <button class="button-secondary" type="submit">Keep my subscription (withdraw request)</button>
                </form>
            @elseif ($canCancel)
                @if ($lastCancellation && $lastCancellation->status === 'rejected')
                    <p class="muted">Your last request was not approved{{ $lastCancellation->review_note ? ': '.$lastCancellation->review_note : '.' }}</p>
                @endif
                <p class="muted">Cancelling stops future renewals. {{ $needsApproval ? 'Our team reviews each request.' : '' }} No refund is calculated automatically — see our <a href="{{ route('refund') }}">refund policy</a>. Your data is not deleted when you submit this form.</p>
                <form method="POST" action="{{ route('dashboard.subscriptions.cancel', $sub) }}" class="auth-fields profile-form cancel-form" data-loading-form>@csrf
                    <fieldset><legend>When</legend>
                        <label class="check-row"><input type="radio" name="type" value="end_of_period" checked> At the end of the paid period ({{ $sub->period_end?->format('d M Y') ?? 'period end' }})</label>
                        @if ($allowImmediate)<label class="check-row"><input type="radio" name="type" value="immediate"> Immediately</label>@endif
                    </fieldset>
                    <label>Reason (optional)<textarea name="reason" rows="3" maxlength="1000"></textarea></label>
                    <label class="check-row"><input type="checkbox" name="confirm" value="1" required> I understand this subscription will not renew.</label>
                    @error('confirm')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    <div><button class="button-secondary button-danger" type="submit">Request cancellation</button></div>
                </form>
            @endif
        </div>
    @endif

    <div class="panel">
        <h2>Invoices</h2>
        @if ($sub->invoices->isEmpty())
            <p class="muted">No renewal invoices yet. The next one is issued before {{ $sub->next_due_at?->format('d M Y') ?? 'the due date' }}.</p>
        @else
            <div class="data-table-wrap"><table class="data-table">
                <thead><tr><th scope="col">Invoice</th><th scope="col">Period</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col"><span class="sr-only">PDF</span></th></tr></thead>
                <tbody>
                @foreach ($sub->invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->number }}</td>
                        <td>{{ $invoice->period_start?->format('d M Y') ?? '—' }} – {{ $invoice->period_end?->format('d M Y') ?? '—' }}</td>
                        <td>{{ \App\Support\Money::inr($invoice->total_minor) }}</td>
                        <td><x-status-badge :status="$invoice->status" /></td>
                        <td><a href="{{ route('dashboard.invoices.download', $invoice) }}">PDF</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="panel">
        <h2>Renewal history</h2>
        @if ($events->isEmpty())
            <p class="muted">No history yet.</p>
        @else
            <ol class="timeline">
                @foreach ($events as $e)
                    <li><time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->format('d M Y') }}</time> {{ $e->note }}</li>
                @endforeach
            </ol>
        @endif
    </div>
@endsection
