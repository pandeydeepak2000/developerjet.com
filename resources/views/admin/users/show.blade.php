@extends('layouts.admin')

@section('title', $member->name)
@section('heading', $member->name)

@php
    $me = auth()->user();
    $isStaff = $member->isStaffAccount();
    $canEditEmail = $me->hasPermission($isStaff ? 'staff.edit' : 'customers.edit');
    $initials = collect(preg_split('/\s+/', trim($member->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

@section('actions')
    @if ($isStaff && $me->hasPermission('staff.view'))<a class="admin-btn admin-btn-quiet" href="{{ route('admin.staff.show', $member) }}">Staff settings</a>@endif
    <a class="admin-btn admin-btn-quiet" href="{{ route('admin.users.index', ['type' => $isStaff ? 'staff' : 'customers']) }}">← All users</a>
@endsection

@section('content')
    <section class="admin-card admin-profile">
        <div class="admin-avatar" aria-hidden="true">{{ $initials ?: '?' }}</div>
        <div class="admin-profile-main">
            <div class="admin-chips">
                <span class="badge badge-neutral">{{ $isStaff ? ($member->staffRole?->name ?? 'Staff') : 'Customer' }}</span>
                @if ($member->is_active ?? true)<span class="badge badge-good">Active</span>@else<span class="badge badge-bad">Suspended</span>@endif
                @if ($member->email_verified_at)<span class="badge badge-good">Email verified</span>@else<span class="badge badge-warn" title="Customer ne abhi email OTP se confirm nahi kiya">Email not verified</span>@endif
            </div>
            <p class="admin-profile-meta"><span>{{ $member->email }}</span><span>{{ $member->phone ?? 'No phone' }}</span><span>Joined {{ $member->created_at?->format('d M Y') }}</span></p>
        </div>
        @if ($canImpersonate)
            <form method="POST" action="{{ route('admin.users.impersonate', $member) }}" onsubmit="return confirm('View dashboard as {{ $member->name }}? Read-only aur audit log mein record hoga.');">@csrf
                <button class="admin-btn" type="submit">View as client</button>
            </form>
        @endif
    </section>

    @unless ($member->email_verified_at)
        <div class="admin-note admin-note-warn">
            <strong>Email abhi verify nahi hua.</strong> Jab tak customer login karke email par aaya 6 digit code nahi daalta, wo checkout aur apna dashboard nahi khol sakta. Customer se kahiye: login karein, phir "Resend code" dabayein.
        </div>
    @endunless

    <div class="admin-stat-row">
        <div class="admin-stat"><span>Orders</span><strong>{{ $member->orders_count }}</strong></div>
        <div class="admin-stat"><span>Services</span><strong>{{ $member->services_count }}</strong></div>
        <div class="admin-stat"><span>Invoices</span><strong>{{ $member->invoices_count }}</strong></div>
        <div class="admin-stat"><span>Tickets</span><strong>{{ $ticketCount }}</strong></div>
    </div>

    <div class="admin-split">
        <div class="admin-split-main">
            <section class="admin-card"><h2>Recent orders</h2>
                @if ($recentOrders->isEmpty())<p class="muted">Abhi koi order nahi.</p>@else
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>@foreach ($recentOrders as $o)<tr>
                        <td data-label="Order">@if ($me->hasPermission('orders.view'))<a class="admin-strong-link" href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a>@else{{ $o->number }}@endif</td>
                        <td data-label="Amount">{{ \App\Support\Money::inr($o->total_minor) }}</td>
                        <td data-label="Status"><x-status-badge :status="$o->status" /></td>
                        <td data-label="Date">{{ $o->created_at?->format('d M Y') }}</td></tr>@endforeach</tbody></table></div>@endif
            </section>

            <section class="admin-card"><h2>Recent tickets</h2>
                @if ($recentTickets->isEmpty())<p class="muted">Abhi koi ticket nahi.</p>@else
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Ticket</th><th>Subject</th><th>Status</th></tr></thead>
                    <tbody>@foreach ($recentTickets as $t)<tr>
                        <td data-label="Ticket">@if ($me->hasPermission('tickets.view'))<a class="admin-strong-link" href="{{ route('admin.tickets.show', $t) }}">{{ $t->number }}</a>@else{{ $t->number }}@endif</td>
                        <td data-label="Subject">{{ $t->subject }}</td>
                        <td data-label="Status"><x-status-badge :status="$t->status" /></td></tr>@endforeach</tbody></table></div>@endif
            </section>
        </div>

        <aside class="admin-split-side">
            <section class="admin-card"><h2>Account details</h2>
                <dl class="admin-dl admin-dl-stack">
                    <dt>Name</dt><dd>{{ $member->name }}</dd>
                    <dt>Email</dt><dd>{{ $member->email }}</dd>
                    <dt>Phone</dt><dd>{{ $member->phone ?? '—' }}</dd>
                    @if ($isStaff)<dt>Departments</dt><dd>{{ $member->departments->pluck('name')->implode(', ') ?: '—' }}</dd>@endif
                    <dt>Joined</dt><dd>{{ $member->created_at?->format('d M Y, H:i') }}</dd>
                    <dt>Email verified</dt><dd>{{ $member->email_verified_at?->format('d M Y, H:i') ?? 'Not yet' }}</dd>
                </dl>
            </section>

            @if ($canEditEmail)
            <section class="admin-card"><h2>Change email</h2>
                <form method="POST" action="{{ route('admin.users.email', $member) }}" class="admin-form">@csrf
                    <label>New email<input type="email" name="email" value="{{ $member->email }}" required maxlength="190"></label>
                    <p class="muted small">Naya email save hote hi account ko naya address verify karna hoga{{ $isStaff ? ' aur staff sessions sign out ho jayenge' : '' }}. Password confirm maanga jaata hai aur audit log mein record hota hai.</p>
                    <div><button class="admin-btn admin-btn-quiet" type="submit">Save email</button></div>
                </form>
            </section>
            @endif

            @if ($member->role === 'customer')
            <section class="admin-card"><h2>View as client</h2>
                @if ($canImpersonate)
                    <p class="muted small">Customer ka dashboard waisa hi dikhega jaisa wo dekhta hai. Sab read-only hai, customer ko koi email, OTP ya notification nahi jaata, aur Start/Exit audit log mein record hota hai.</p>
                @else
                    <p class="muted small">Iske liye <code>customers.impersonate</code> permission chahiye aur account active hona chahiye.</p>
                @endif
            </section>
            @endif
        </aside>
    </div>
@endsection
