@extends('layouts.admin')

@section('title', 'Billing & renewals')
@section('heading', 'Billing & renewals')

@section('actions')
    @if (auth()->user()->hasPermission('settings.view'))<a class="admin-btn admin-btn-quiet" href="{{ route('admin.settings.edit') }}#billing">Billing settings</a>@endif
@endsection

@section('content')
    <section class="admin-card">
        <h2>How renewals run</h2>
        <p class="muted small">
            Daily <code>billing:run</code>: renewal invoice {{ $settings['invoice_days'] }} day(s) before due ·
            reminders {{ $settings['reminders'] ? implode(', ', array_map(fn ($d) => $d.'d before', $settings['offsets'])) ?: 'none before due' : 'OFF' }}{{ $settings['reminders'] && $settings['due_day'] ? ', on the due date' : '' }}{{ $settings['reminders'] && $settings['overdue_offsets'] ? ', '.implode(', ', array_map(fn ($d) => $d.'d overdue', $settings['overdue_offsets'])) : '' }} ·
            grace {{ $settings['grace'] }} day(s) ·
            auto-suspension <strong>{{ $settings['auto_suspend'] ? 'ON (queue)' : 'OFF' }}</strong> ·
            staff approval {{ $settings['approval'] ? 'required' : 'not required' }} ·
            live adapter suspension <strong>{{ $settings['live'] ? 'ENABLED' : 'OFF' }}</strong>.
            No supplier has a verified suspension adapter, so suspensions and reactivations are staff tasks. Customers are never charged automatically.
        </p>
        <div class="admin-kpis">
            <a class="admin-kpi" href="{{ route('admin.billing.index', ['tab' => 'overdue']) }}"><span>Overdue / grace</span><strong>{{ $counts['overdue'] }}</strong></a>
            <a class="admin-kpi" href="{{ route('admin.billing.index', ['tab' => 'suspension']) }}"><span>Suspension pending</span><strong>{{ $counts['suspension'] }}</strong></a>
            <a class="admin-kpi" href="{{ route('admin.billing.index', ['tab' => 'reactivation']) }}"><span>Reactivation pending</span><strong>{{ $counts['reactivation'] }}</strong></a>
            <a class="admin-kpi" href="{{ route('admin.billing.index', ['tab' => 'reminders', 'status' => 'failed']) }}"><span>Failed reminders</span><strong>{{ $counts['failed_reminders'] }}</strong></a>
            <a class="admin-kpi" href="{{ route('admin.billing.index', ['tab' => 'cancellations']) }}"><span>Cancellation requests</span><strong>{{ $counts['cancellations'] }}</strong></a>
            <a class="admin-kpi" href="{{ route('admin.billing.index', ['tab' => 'failed']) }}"><span>Failed renewals</span><strong>{{ $counts['failed'] }}</strong></a>
        </div>
    </section>

    <nav class="admin-tabs" aria-label="Billing views">
        @foreach (\App\Http\Controllers\Admin\BillingController::TABS as $key => $label)
            <a href="{{ route('admin.billing.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'cancellations')
        <section class="admin-card">
            <p><a href="{{ route('admin.billing.index', ['tab' => 'cancellations']) }}">Pending</a> · <a href="{{ route('admin.billing.index', ['tab' => 'cancellations', 'status' => 'approved']) }}">Approved (ending)</a> · <a href="{{ route('admin.billing.index', ['tab' => 'cancellations', 'status' => 'all']) }}">All</a></p>
            @if ($cancellations->isEmpty())
                <p class="muted">No cancellation requests.</p>
            @else
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>Requested</th><th>Customer</th><th>Subscription</th><th>When</th><th>Reason</th><th>Status</th><th>Review</th></tr></thead>
                    <tbody>@foreach ($cancellations as $c)
                        <tr>
                            <td data-label="Requested">{{ $c->created_at->format('d M Y') }}</td>
                            <td data-label="Customer">{{ $c->user?->email }}</td>
                            <td data-label="Subscription"><a href="{{ route('admin.billing.show', $c->subscription_id) }}">{{ $c->subscription?->plan?->name ?? '#'.$c->subscription_id }}</a></td>
                            <td data-label="When">{{ $c->type === 'immediate' ? 'Immediately' : 'End of period' }}{{ $c->effective_at ? ' · '.$c->effective_at->format('d M Y') : '' }}</td>
                            <td data-label="Reason">{{ \Illuminate\Support\Str::limit($c->reason ?? '—', 80) }}</td>
                            <td data-label="Status"><x-status-badge :status="$c->status" /></td>
                            <td>@if ($c->status === 'pending' && auth()->user()->hasPermission('subscriptions.edit'))
                                <form method="POST" action="{{ route('admin.billing.cancellations.review', $c) }}" class="admin-inline-form">@csrf
                                    <input name="note" maxlength="500" placeholder="Note (required to reject)" aria-label="Review note">
                                    <button class="admin-btn admin-btn-quiet" name="decision" value="approve" type="submit">Approve</button>
                                    <button class="admin-btn admin-btn-danger" name="decision" value="reject" type="submit">Reject</button>
                                </form>
                            @else{{ $c->reviewer?->name ?? '—' }}@endif</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
                {{ $cancellations->links() }}
            @endif
        </section>
    @elseif ($tab !== 'reminders')
        <form class="admin-filters" method="GET" action="{{ route('admin.billing.index') }}" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Customer name or email" aria-label="Search customer">
            <button class="admin-btn admin-btn-quiet" type="submit">Search</button>
        </form>
        <section class="admin-card">
            @if ($rows->isEmpty())
                <p class="muted">Nothing here.</p>
            @else
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>Customer</th><th>Service</th><th>Billing</th><th>Agreed price</th><th>Paid through</th><th>Next due</th><th>Status</th><th><span class="sr-only">Open</span></th></tr></thead>
                    <tbody>
                    @foreach ($rows as $sub)
                        <tr>
                            <td data-label="Customer">{{ $sub->user?->email }}</td>
                            <td data-label="Service">{{ $sub->service?->label ?? $sub->plan?->name ?? '—' }}</td>
                            <td data-label="Billing">{{ $sub->cycleLabel() }}</td>
                            <td data-label="Agreed price">{{ $sub->price_minor !== null ? \App\Support\Money::inr($sub->price_minor) : 'not captured' }}</td>
                            <td data-label="Paid through">{{ $sub->period_end?->format('d M Y') ?? '—' }}</td>
                            <td data-label="Next due">{{ $sub->next_due_at?->format('d M Y') ?? '—' }}</td>
                            <td data-label="Status"><x-status-badge :status="$sub->status" />@if ($sub->suspension_exempt) <small class="muted">exempt</small>@endif @if ($sub->cancel_at_period_end) <small class="muted">ends {{ $sub->cancel_at?->format('d M Y') }}</small>@endif @if ($sub->renewal_failures) <small class="admin-error">{{ $sub->renewal_failures }} failure(s): {{ \Illuminate\Support\Str::limit($sub->last_renewal_error, 60) }}</small>@endif</td>
                            <td><a href="{{ route('admin.billing.show', $sub) }}">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                {{ $rows->links() }}
            @endif
        </section>
    @else
        <section class="admin-card">
            <p class="muted small">Each reminder is sent at most once per due date. Failed sends can be retried; the e-mail itself is queued (run <code>php artisan queue:work</code>).</p>
            <p><a href="{{ route('admin.billing.index', ['tab' => 'reminders']) }}">All</a> · <a href="{{ route('admin.billing.index', ['tab' => 'reminders', 'status' => 'failed']) }}">Failed only</a></p>
            @if ($reminders->isEmpty())
                <p class="muted">No reminders.</p>
            @else
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>When</th><th>Customer</th><th>Reminder</th><th>Due date</th><th>Invoice</th><th>Status</th><th>Attempts</th><th>Error</th><th><span class="sr-only">Retry</span></th></tr></thead>
                    <tbody>
                    @foreach ($reminders as $r)
                        <tr>
                            <td data-label="When">{{ $r->created_at->format('d M Y H:i') }}</td>
                            <td data-label="Customer">{{ $r->subscription?->user?->email ?? '—' }}</td>
                            <td data-label="Reminder">{{ $r->label() }}</td>
                            <td data-label="Due date">{{ $r->due_date->format('d M Y') }}</td>
                            <td data-label="Invoice">{{ $r->invoice?->number ?? '—' }}</td>
                            <td data-label="Status"><x-status-badge :status="$r->status" /></td>
                            <td data-label="Attempts">{{ $r->attempts }}</td>
                            <td data-label="Error">{{ $r->error ?? '—' }}</td>
                            <td>
                                @if ($r->status === 'failed' && auth()->user()->hasPermission('subscriptions.edit'))
                                    <form method="POST" action="{{ route('admin.billing.reminders.retry', $r) }}">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Retry</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                {{ $reminders->links() }}
            @endif
        </section>
    @endif
@endsection
