@extends('layouts.admin')

@section('title', 'Wallet')
@section('heading', 'Wallet')

@section('content')
    @php $u = auth()->user(); @endphp
    <section class="admin-card"><h2>Top-up requests</h2>
        <p class="muted small">Verify the funds independently (bank/UPI statement) before crediting. Customer-supplied references are not proof of payment.</p>
        @if ($topups->isEmpty())<p class="muted">No top-up requests.</p>@else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>#</th><th>Customer</th><th>Amount</th><th>Customer reference</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>@foreach ($topups as $t)
                <tr>
                    <td data-label="#">{{ $t->id }}</td><td data-label="Customer">{{ $t->user->email }}</td><td data-label="Amount">{{ \App\Support\Money::inr($t->amount_minor) }}</td>
                    <td data-label="Reference">{{ $t->payment_reference ?? '—' }}</td><td data-label="Status"><x-status-badge :status="$t->status" /></td>
                    <td class="admin-row-actions">
                        @if ($t->status === 'pending' && $u->hasPermission('wallet.approve'))
                            <form method="POST" action="{{ route('admin.wallet.approve', $t) }}" onsubmit="return confirm('Credit {{ \App\Support\Money::inr($t->amount_minor) }} to {{ $t->user->email }}? Confirm funds are received.')">@csrf<button class="admin-btn" type="submit">Credit</button></form>
                            <form method="POST" action="{{ route('admin.wallet.reject', $t) }}" class="admin-inline-form">@csrf<input name="review_note" placeholder="Reason" required maxlength="250"><button class="admin-link-danger" type="submit">Reject</button></form>
                        @endif
                    </td>
                </tr>@endforeach</tbody>
        </table></div>{{ $topups->links() }}@endif
    </section>

    @if ($u->hasPermission('wallet.create'))
    <section class="admin-card"><h2>Manual adjustment</h2>
        <form method="POST" action="{{ route('admin.wallet.adjust') }}" class="admin-form admin-form-grid" data-loading-form>@csrf
            <div class="dj-picker" data-customer-picker data-url="{{ route('admin.wallet.customers') }}">
                <label for="wallet-customer">Customer</label>
                <div class="dj-picker-field">
                    <input id="wallet-customer" type="text" name="email" value="{{ old('email') }}" required autocomplete="off" maxlength="150"
                           placeholder="e.g. rahul, rahul@gmail.com, 98765…" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="wallet-customer-list">
                    <ul id="wallet-customer-list" class="dj-picker-list" role="listbox" hidden></ul>
                </div>
                <p class="dj-picker-hint muted small" data-picker-hint aria-live="polite">Naam, email ya phone ke 2+ akshar type karein, phir list se customer chunein.</p>
            </div>
            <label>Type<select name="type"><option value="credit">Credit</option><option value="debit">Debit</option></select></label>
            <label>Amount (₹)<input type="number" name="amount" step="0.01" min="0.01" required></label>
            <label>Transaction ID / UTR *<input name="transaction_id" required minlength="3" maxlength="120" value="{{ old('transaction_id') }}" placeholder="UTR / Transaction ID"></label>
            <label>Reason (audited)<input name="reason" required minlength="5" maxlength="250"></label>
            <div class="span-2"><button class="admin-btn" type="submit">Record adjustment</button></div>
        </form>
    </section>
    @endif

    <section class="admin-card"><h2>Customer wallets</h2>
        <form class="admin-filters" method="GET"><input type="search" name="q" value="{{ request('q') }}" placeholder="Customer email" aria-label="Search wallets"><button class="admin-btn admin-btn-quiet">Search</button></form>
        @if ($wallets->isEmpty())<p class="muted">No wallets yet.</p>@else
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Customer</th><th>Balance</th></tr></thead>
            <tbody>@foreach ($wallets as $w)<tr><td>{{ $w->user->email }}</td><td>{{ \App\Support\Money::inr($w->balance_minor) }}</td></tr>@endforeach</tbody></table></div>{{ $wallets->links() }}@endif
    </section>

    <section class="admin-card"><h2>Wallet history</h2>
        <p class="muted small">Transaction ID ya customer email se search karein — kis staff ne fund add kiya, woh bhi dikhega.</p>
        <form class="admin-filters" method="GET"><input type="search" name="tx_q" value="{{ request('tx_q') }}" placeholder="Transaction ID / customer email" aria-label="Search wallet history"><button class="admin-btn admin-btn-quiet">Search</button></form>
        @if ($history->isEmpty())<p class="muted">No wallet transactions yet.</p>@else
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Customer</th><th>Type</th><th>Amount</th><th>Transaction ID</th><th>Reason</th><th>Added by</th></tr></thead>
            <tbody>@foreach ($history as $h)<tr><td data-label="Date">{{ $h->created_at->format('d M Y H:i') }}</td><td data-label="Customer">{{ $h->wallet->user->email ?? '—' }}</td><td data-label="Type">{{ $h->type }}</td><td data-label="Amount">{{ \App\Support\Money::inr($h->amount_minor) }}</td><td data-label="Transaction ID">{{ $h->meta['transaction_id'] ?? $h->reference }}</td><td data-label="Reason">{{ $h->description ?? '—' }}</td><td data-label="Added by">{{ $h->actor ? $h->actor->name.' ('.$h->actor->email.')' : '—' }}</td></tr>@endforeach</tbody></table></div>{{ $history->links() }}@endif
    </section>
@endsection
