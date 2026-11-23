@extends('layouts.admin')

@section('title', 'User Manager')
@section('heading', 'User Manager')

@section('actions')
    <a class="admin-btn admin-btn-quiet" href="{{ route('admin.staff.index') }}">Staff & roles</a>
@endsection

@section('content')
    <p class="muted">Customers aur staff — Support team aur Sales team — ek hi jagah se manage karo. Email change aur View as Client yahi se hota hai.</p>
    <nav class="dj-quick-actions" aria-label="User type">
        <a class="admin-btn {{ $type === 'customers' ? '' : 'admin-btn-quiet' }}" href="{{ route('admin.users.index', ['type' => 'customers']) }}">Customers ({{ $counts['customers'] }})</a>
        <a class="admin-btn {{ $type === 'staff' ? '' : 'admin-btn-quiet' }}" href="{{ route('admin.users.index', ['type' => 'staff']) }}">Staff ({{ $counts['staff'] }})</a>
        <a class="admin-btn {{ $type === 'all' ? '' : 'admin-btn-quiet' }}" href="{{ route('admin.users.index', ['type' => 'all']) }}">All users</a>
    </nav>
    <form class="admin-filters" method="GET" action="{{ route('admin.users.index') }}">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email, phone…" aria-label="Search users">
        <select name="status" aria-label="Status"><option value="">Status: all</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="suspended" @selected(request('status') === 'suspended')>Suspended</option></select>
        <select name="role_id" aria-label="Role"><option value="">Role: all</option>@foreach ($roles as $r)<option value="{{ $r->id }}" @selected((string) request('role_id') === (string) $r->id)>{{ $r->name }}</option>@endforeach</select>
        <select name="per_page" aria-label="Rows per page">@foreach ([15, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page</option>@endforeach</select>
        <button class="admin-btn admin-btn-quiet" type="submit">Filter</button>
        @if (request()->query())<a class="admin-link-btn" href="{{ route('admin.users.index', ['type' => $type]) }}">Reset</a>@endif
    </form>
    @if ($users->isEmpty())
        <div class="admin-empty"><h2>No users found</h2><p>Filters badlo ya naya staff invite karo.</p></div>
    @else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Type</th><th>Orders</th><th>Status</th><th>Joined</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>@foreach ($users as $user)
                <tr>
                    <td data-label="Name">{{ $user->name }}</td>
                    <td data-label="Email">{{ $user->email }}</td>
                    <td data-label="Phone">{{ $user->phone ?? '—' }}</td>
                    <td data-label="Type">{{ $user->isStaffAccount() ? ($user->staffRole?->name ?? 'Staff') : 'Customer' }}</td>
                    <td data-label="Orders">{{ $user->orders_count }}</td>
                    <td data-label="Status">{{ ($user->is_active ?? true) ? 'Active' : 'Suspended' }}</td>
                    <td data-label="Joined">{{ $user->created_at?->format('d M Y') }}</td>
                    <td><a href="{{ route('admin.users.show', $user) }}">Open</a></td>
                </tr>@endforeach</tbody>
        </table></div>
        {{ $users->links() }}
    @endif
@endsection
