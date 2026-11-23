@extends('layouts.dashboard')

@section('title', 'Invoices')
@section('heading', 'Invoices')
@section('subheading', 'Billing documents for your orders and renewals.')

@section('dash')
    @php $unpaid = $invoices->getCollection()->filter(fn ($i) => $i->isUnpaid()); @endphp
    @if ($unpaid->isNotEmpty())
        <div class="flash-warning" role="status">
            You have {{ $unpaid->count() }} unpaid {{ \Illuminate\Support\Str::plural('invoice', $unpaid->count()) }}. Use <strong>Renew now</strong> to pay — nothing is charged automatically.
        </div>
    @endif
    @if ($invoices->isEmpty())
        <x-empty-state title="No invoices yet" text="Invoices appear here after a payment is verified, and renewal invoices appear before a service is due." />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th scope="col">Invoice</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Total</th><th scope="col">Issued</th><th scope="col">Due</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->number ?? 'Draft' }}@if ($invoice->period_start)<br><small class="muted">{{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end?->format('d M Y') }}</small>@endif</td>
                    <td>{{ $invoice->type === 'credit_note' ? 'Credit note' : ($invoice->subscription_id ? 'Renewal' : 'Invoice') }}</td>
                    <td><x-status-badge :status="$invoice->status" /></td>
                    <td>{{ \App\Support\Money::inr($invoice->total_minor) }}</td>
                    <td>{{ $invoice->issued_at?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $invoice->isUnpaid() ? $invoice->due_at?->format('d M Y') : '—' }}</td>
                    <td class="table-actions">
                        @if ($invoice->isUnpaid() && $invoice->order_id)<a class="button-primary button-sm" href="{{ route('dashboard.orders.show', $invoice->order_id) }}">Renew now</a>@endif
                        @if ($invoice->number)<a href="{{ route('dashboard.invoices.download', $invoice) }}">PDF</a>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $invoices->links() }}
    @endif
@endsection
