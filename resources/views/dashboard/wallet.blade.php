@extends('layouts.dashboard')

@section('title', 'Wallet')
@section('heading', 'Wallet')
@section('subheading', 'Your DeveloperJet wallet balance and transactions.')

@section('dash')
    @php $phone = \App\Support\Setting::get('sales.phone'); @endphp
    <div class="stat-grid">
        <x-stat-card label="Balance" :value="\App\Support\Money::inr($wallet->balance_minor)" note="Credits are added only after verification." />
    </div>

    <div class="panel sales-card">
        <h2>Contact our Sales Team to add funds</h2>
        <p class="muted">Funds are added manually. Pay as instructed by our team, then submit a request below. We credit your wallet only after we independently verify the payment.</p>
        @if ($phone)
            <p class="sales-phone"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></p>
        @else
            <p class="muted">Sales contact number has not been configured yet. Please use the <a href="{{ route('contact') }}">contact page</a>.</p>
        @endif
        <form method="POST" action="{{ route('dashboard.wallet.topup') }}" class="form-grid" data-loading-form>
            @csrf
            <label>Amount (₹)<input type="number" name="amount" min="1" step="0.01" required value="{{ old('amount') }}"></label>
            <label>Payment reference (optional)<input name="payment_reference" maxlength="120" value="{{ old('payment_reference') }}" placeholder="e.g. UTR / transaction ID"></label>
            <label class="span-2">Note (optional)<textarea name="note" rows="2" maxlength="500">{{ old('note') }}</textarea></label>
            <div class="span-2"><button class="button-primary" type="submit">Submit top-up request</button></div>
        </form>
    </div>

    @if ($topups->isNotEmpty())
        <div class="panel"><h2>Top-up requests</h2>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>@foreach ($topups as $t)<tr><td>{{ $t->created_at->format('d M Y') }}</td><td>{{ \App\Support\Money::inr($t->amount_minor) }}</td><td><x-status-badge :status="$t->status" /></td></tr>@endforeach</tbody>
            </table></div>
        </div>
    @endif

    @if ($transactions->isEmpty())
        <x-empty-state title="No transactions" text="Wallet credits and debits will be listed here." />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Description</th><th scope="col">Amount</th><th scope="col">Balance</th></tr></thead>
            <tbody>
            @foreach ($transactions as $tx)
                <tr><td>{{ $tx->created_at->format('d M Y') }}</td><td>{{ ucfirst($tx->type) }}</td><td>{{ $tx->description }}</td><td>{{ $tx->type === 'debit' ? '−' : '+' }}{{ \App\Support\Money::inr($tx->amount_minor) }}</td><td>{{ \App\Support\Money::inr($tx->balance_after_minor) }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $transactions->links() }}
    @endif
@endsection
