@extends('layouts.admin')

@section('title', 'Billing operations')
@section('heading', 'Billing operations')

@section('content')
    @php $u = auth()->user(); @endphp
    <nav class="admin-tabs" aria-label="Billing operations views">
        @foreach (\App\Http\Controllers\Admin\BillingOpsController::TABS as $key => $label)
            <a href="{{ route('admin.billing-ops', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <a href="{{ route('admin.billing.index') }}">Renewals & subscriptions →</a>
    </nav>

    @if ($tab === 'overdue')
        <section class="admin-card">
            <p class="muted small">Unpaid invoices past their due date. {{ $dueSoon }} more fall due in the next 7 days. Customers pay invoices themselves; nothing is charged automatically.</p>
            @if ($invoices->isEmpty())<p class="muted">No overdue invoices.</p>@else
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Due</th><th>Days overdue</th><th><span class="sr-only">PDF</span></th></tr></thead>
                <tbody>@foreach ($invoices as $i)
                    <tr><td data-label="Invoice">{{ $i->number }}</td><td data-label="Customer">{{ $i->user?->email }}</td><td data-label="Total">{{ \App\Support\Money::inr($i->total_minor) }}</td>
                        <td data-label="Due">{{ $i->due_at->format('d M Y') }}</td><td data-label="Days overdue">{{ (int) $i->due_at->diffInDays(now()) }}</td>
                        <td><a href="{{ route('admin.invoices.download', $i) }}">PDF</a></td></tr>
                @endforeach</tbody>
            </table></div>{{ $invoices->links() }}@endif
        </section>
    @elseif ($tab === 'reconciliation')
        <section class="admin-card">
            <p class="muted small">Gateway payments are settled by the browser callback, the webhook, and every 15 minutes by <code>payments:reconcile</code>. All three use the same idempotent path, so a payment is never credited twice. Razorpay has only been tested against fakes; PTSure is disabled.</p>
            @if ($u->hasPermission('orders.edit'))
                <form method="POST" action="{{ route('admin.billing-ops.reconcile') }}">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Run reconciliation now</button></form>
            @endif
        </section>
        @foreach ([['Payment attempts open for more than 10 minutes', $stale], ['Amount mismatches (held for review)', $mismatch], ['Verified payments whose order is not marked paid', $unsettled]] as [$title, $rows])
            <section class="admin-card"><h2>{{ $title }} ({{ $rows->count() }})</h2>
                @if ($rows->isEmpty())<p class="muted">None.</p>@else
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>#</th><th>Order</th><th>Gateway</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>@foreach ($rows as $a)<tr><td data-label="#">{{ $a->id }}</td><td data-label="Order">@if ($a->order)<a href="{{ route('admin.orders.show', $a->order) }}">{{ $a->order->number }}</a>@else — @endif</td><td data-label="Gateway">{{ $a->gateway }}</td><td data-label="Amount">{{ \App\Support\Money::inr($a->amount_minor) }}</td><td data-label="Status"><x-status-badge :status="$a->status" /></td><td data-label="Created">{{ $a->created_at->format('d M Y H:i') }}</td></tr>@endforeach</tbody>
                </table></div>@endif
            </section>
        @endforeach
        <section class="admin-card"><h2>Paid orders without an invoice ({{ $paidWithoutInvoice->count() }})</h2>
            @if ($paidWithoutInvoice->isEmpty())<p class="muted">None — every paid order has an invoice.</p>@else
            <ul class="admin-list">@foreach ($paidWithoutInvoice as $o)<li><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a> · paid {{ $o->paid_at->format('d M Y') }}</li>@endforeach</ul>@endif
        </section>
    @else
        <section class="admin-card">
            <p class="muted small">Billing-related audit entries (orders, payments, wallet, invoices, subscriptions, plan and settings changes). Secrets are never written to the audit log.</p>
            @if ($logs->isEmpty())<p class="muted">No entries.</p>@else
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>When</th><th>Action</th><th>By</th><th>Subject</th><th>IP</th></tr></thead>
                <tbody>@foreach ($logs as $l)<tr><td data-label="When">{{ $l->created_at?->format('d M Y H:i') }}</td><td data-label="Action">{{ $l->action }}</td><td data-label="By">{{ $l->user?->name ?? 'System' }}</td><td data-label="Subject">{{ $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '—' }}</td><td data-label="IP">{{ $l->ip ?? '—' }}</td></tr>@endforeach</tbody>
            </table></div>{{ $logs->links() }}@endif
        </section>
    @endif
@endsection
