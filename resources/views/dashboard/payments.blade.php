@extends('layouts.dashboard')

@section('title', 'Payments')
@section('heading', 'Payments')
@section('subheading', 'Online payment attempts and wallet payments for your orders.')

@section('dash')
    <p class="muted">A payment counts only after it is verified with the payment gateway. Failed or abandoned attempts are never charged. Saved payment details are never charged automatically.</p>
    @if ($attempts->isEmpty() && $walletPayments->isEmpty())
        <x-empty-state title="No payments yet" text="Payments appear here when you pay for an order or renewal." />
    @endif

    @if ($attempts->isNotEmpty())
        <div class="panel">
            <h2>Online payments</h2>
            <div class="data-table-wrap"><table class="data-table">
                <thead><tr><th scope="col">Date</th><th scope="col">Order</th><th scope="col">Method</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead>
                <tbody>@foreach ($attempts as $a)
                    <tr>
                        <td>{{ $a->created_at->format('d M Y, H:i') }}</td>
                        <td>@if ($a->order)<a href="{{ route('dashboard.orders.show', $a->order) }}">{{ $a->order->number }}</a>@else — @endif</td>
                        <td>{{ ucfirst($a->gateway) }}{{ $a->mode === 'test' ? ' (test mode)' : '' }}</td>
                        <td>{{ \App\Support\Money::inr($a->amount_minor) }}</td>
                        <td><x-status-badge :status="$a->status === 'created' ? 'pending' : $a->status" /></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $attempts->links() }}
        </div>
    @endif

    @if ($walletPayments->isNotEmpty())
        <div class="panel">
            <h2>Paid from wallet</h2>
            <div class="data-table-wrap"><table class="data-table">
                <thead><tr><th scope="col">Order</th><th scope="col">Wallet amount</th><th scope="col">Paid</th></tr></thead>
                <tbody>@foreach ($walletPayments as $o)
                    <tr><td><a href="{{ route('dashboard.orders.show', $o) }}">{{ $o->number }}</a></td><td>{{ \App\Support\Money::inr($o->wallet_applied_minor) }}</td><td>{{ $o->paid_at?->format('d M Y') ?? 'Reserved — order not completed' }}</td></tr>
                @endforeach</tbody>
            </table></div>
            <p><a href="{{ route('dashboard.wallet') }}">Full wallet history</a></p>
        </div>
    @endif
@endsection
