@extends('layouts.admin')

@section('title', $member->name)
@section('heading', 'User · '.$member->name)

@section('actions')
    <a class="admin-btn admin-btn-quiet" href="{{ route('admin.users.index') }}">All users</a>
@endsection

@section('content')
    <section class="admin-card"><h2>Account</h2>
        <dl class="admin-dl">
            <dt>Name</dt><dd>{{ $member->name }}</dd>
            <dt>Email</dt><dd>{{ $member->email }}</dd>
            <dt>Phone</dt><dd>{{ $member->phone ?? '—' }}</dd>
            <dt>Type</dt><dd>{{ $member->isStaffAccount() ? ($member->staffRole?->name ?? 'Staff') : 'Customer' }}</dd>
            <dt>Departments</dt><dd>{{ $member->departments->pluck('name')->implode(', ') ?: '—' }}</dd>
            <dt>Status</dt><dd>{{ ($member->is_active ?? true) ? 'Active' : 'Suspended' }}</dd>
            <dt>Email verified</dt><dd>{{ $member->email_verified_at ? 'Yes' : 'Not yet' }}</dd>
            <dt>Joined</dt><dd>{{ $member->created_at?->format('d M Y H:i') }}</dd>
            <dt>Orders</dt><dd>{{ $member->orders_count }}</dd>
            <dt>Services</dt><dd>{{ $member->services_count }}</dd>
            <dt>Invoices</dt><dd>{{ $member->invoices_count }}</dd>
            <dt>Tickets</dt><dd>{{ $ticketCount }}</dd>
        </dl>
    </section>

    @if (auth()->user()->hasPermission($member->isStaffAccount() ? 'staff.edit' : 'customers.edit'))
    <section class="admin-card"><h2>Change email</h2>
        <p class="muted small">Naya email save karte hi purana verification reset ho jata hai aur account ko naya address verify karna padta hai. Staff ka session bhi sign out ho jata hai. Ye action audit log me record hota hai aur password confirm mangta hai.</p>
        <form method="POST" action="{{ route('admin.users.email', $member) }}" class="admin-form admin-form-grid">@csrf
            <label>New email<input type="email" name="email" value="{{ $member->email }}" required maxlength="190"></label>
            <div class="span-2"><button class="admin-btn" type="submit">Save email</button></div>
        </form>
    </section>
    @endif

    @if ($canImpersonate)
    <section class="admin-card"><h2>View as client</h2>
        <p class="muted small">Customer ka dashboard exactly waisa dekho jaisa wo dekhta hai — bina uska password jaane. Ye read-only hai: impersonation ke dauraan koi order, payment ya setting change nahi ho sakti. Start aur Exit dono audit log me record hote hain. Customer ko koi email, OTP ya notification nahi jaata, aur unverified customer ka dashboard bhi bina OTP ke khulta hai.</p>
        <form method="POST" action="{{ route('admin.users.impersonate', $member) }}" onsubmit="return confirm('View dashboard as {{ $member->name }}? This is read-only and logged.');">@csrf<button class="admin-btn" type="submit">View as client</button></form>
    </section>
    @elseif ($member->role === 'customer')
    <section class="admin-card"><h2>View as client</h2><p class="muted">Is customer ko View as Client karne ke liye customers.impersonate permission chahiye, ya account active hona chahiye.</p></section>
    @endif

    <section class="admin-card"><h2>Recent orders</h2>
        @if ($recentOrders->isEmpty())<p class="muted">No orders yet.</p>@else
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Date</th></tr></thead><tbody>@foreach ($recentOrders as $o)<tr><td data-label="Order">{{ $o->number }}</td><td data-label="Total">{{ \App\Support\Money::inr($o->total_minor) }}</td><td data-label="Status">{{ $o->status }}</td><td data-label="Date">{{ $o->created_at?->format('d M Y') }}</td></tr>@endforeach</tbody></table></div>@endif
    </section>

    <section class="admin-card"><h2>Recent tickets</h2>
        @if ($recentTickets->isEmpty())<p class="muted">No tickets yet.</p>@else
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Ticket</th><th>Subject</th><th>Status</th></tr></thead><tbody>@foreach ($recentTickets as $t)<tr><td data-label="Ticket">{{ $t->number }}</td><td data-label="Subject">{{ $t->subject }}</td><td data-label="Status">{{ $t->status }}</td></tr>@endforeach</tbody></table></div>@endif
    </section>
@endsection
