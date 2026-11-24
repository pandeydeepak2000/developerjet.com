@extends('layouts.admin')

@section('title', 'User Manager')
@section('heading', 'User Manager')

@php $me = auth()->user(); $canAdd = $me->hasPermission('customers.edit'); @endphp

@section('actions')
    @if ($canAdd)<a class="admin-btn" href="#add-customer" data-open-details="add-customer">+ Add customer</a>@endif
    @if ($me->hasPermission('staff.view'))<a class="admin-btn admin-btn-quiet" href="{{ route('admin.staff.index') }}">Staff & roles</a>@endif
@endsection

@section('content')
    <p class="admin-lead">Customers aur staff ek hi jagah. Kisi bhi row par <strong>Open</strong> se details, email change aur View as client.</p>

    @if ($canAdd)
    <details class="admin-card admin-disclosure" id="add-customer" @if ($errors->any() && old('form') === 'add-customer') open @endif>
        <summary><span><strong>Add customer</strong><small>Naya client jo phone ya office se aaya ho. Password customer khud set karega.</small></span></summary>
        <form method="POST" action="{{ route('admin.users.store') }}" class="admin-form admin-form-grid" data-loading-form>@csrf
            <input type="hidden" name="form" value="add-customer">
            <label>Full name<input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="off"></label>
            <label>Email<input type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="off"></label>
            <div class="span-2">@include('partials.phone-field', ['idPrefix' => 'add-customer'])</div>
            <label class="admin-check span-2"><input type="checkbox" name="send_setup_link" value="1" checked> Customer ko password set karne ka link email karein (recommended)</label>
            <p class="muted small span-2">Staff customer ka password na dekhta hai na chunta hai. Customer pehli baar login par email OTP se apna email verify karega.</p>
            <div class="span-2"><button class="admin-btn" type="submit">Create customer</button></div>
        </form>
    </details>
    @endif

    <nav class="admin-tabs" aria-label="User type">
        <a href="{{ route('admin.users.index', ['type' => 'customers']) }}" @if ($type === 'customers') aria-current="page" @endif>Customers <span>{{ number_format($counts['customers']) }}</span></a>
        <a href="{{ route('admin.users.index', ['type' => 'staff']) }}" @if ($type === 'staff') aria-current="page" @endif>Staff <span>{{ number_format($counts['staff']) }}</span></a>
        <a href="{{ route('admin.users.index', ['type' => 'all']) }}" @if ($type === 'all') aria-current="page" @endif>All users</a>
    </nav>

    <section class="admin-card admin-card-flush">
        <form class="admin-filters" method="GET" action="{{ route('admin.users.index') }}">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email, phone…" aria-label="Search users">
            <select name="status" aria-label="Status"><option value="">Status: all</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="suspended" @selected(request('status') === 'suspended')>Suspended</option></select>
            @if ($type !== 'customers')
                <select name="role_id" aria-label="Role"><option value="">Role: all</option>@foreach ($roles as $r)<option value="{{ $r->id }}" @selected((string) request('role_id') === (string) $r->id)>{{ $r->name }}</option>@endforeach</select>
            @endif
            <select name="per_page" aria-label="Rows per page">@foreach ([15, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page</option>@endforeach</select>
            <button class="admin-btn admin-btn-quiet" type="submit">Filter</button>
            @if (collect(request()->query())->except('type')->filter()->isNotEmpty())<a class="admin-link-btn" href="{{ route('admin.users.index', ['type' => $type]) }}">Reset</a>@endif
        </form>

        @if ($users->isEmpty())
            <div class="admin-empty"><h2>No users found</h2><p>Filters badaliye{{ $canAdd ? ', ya upar "Add customer" se naya customer jodiye' : '' }}.</p></div>
        @else
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Type</th><th>Orders</th><th>Status</th><th>Joined</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>@foreach ($users as $user)
                    <tr>
                        <td data-label="Name"><a class="admin-strong-link" href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a></td>
                        <td data-label="Email">{{ $user->email }}@unless ($user->email_verified_at) <span class="badge badge-warn" title="Email not verified yet">unverified</span>@endunless</td>
                        <td data-label="Phone">{{ $user->phone ?? '—' }}</td>
                        <td data-label="Type">@if ($user->isStaffAccount())<span class="badge badge-neutral">{{ $user->staffRole?->name ?? 'Staff' }}</span>@else Customer @endif</td>
                        <td data-label="Orders">{{ $user->orders_count }}</td>
                        <td data-label="Status">@if ($user->is_active ?? true)<span class="badge badge-good">Active</span>@else<span class="badge badge-bad">Suspended</span>@endif</td>
                        <td data-label="Joined">{{ $user->created_at?->format('d M Y') }}</td>
                        <td class="admin-row-actions"><a class="admin-btn admin-btn-quiet admin-btn-sm" href="{{ route('admin.users.show', $user) }}">Open</a></td>
                    </tr>@endforeach</tbody>
            </table></div>
            {{ $users->links() }}
        @endif
    </section>
@endsection
