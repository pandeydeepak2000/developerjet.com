@extends('layouts.admin')

@section('title', 'Orders')
@section('heading', 'Orders')

@section('content')
    <form class="admin-filters" method="GET" action="{{ route('admin.orders.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Order number or customer email" aria-label="Search orders">
        <select name="status" aria-label="Status"><option value="">Status: all</option>
            @foreach (\App\Support\OrderStatus::LABELS as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
        </select>
        <select name="assigned" aria-label="Assignment"><option value="">Assigned: anyone</option><option value="me" @selected(request('assigned') === 'me')>Assigned to me</option></select>
        <button class="admin-btn admin-btn-quiet" type="submit">Filter</button>
        @if (request()->query())<a class="admin-link-btn" href="{{ route('admin.orders.index') }}">Reset</a>@endif
    </form>
    @if ($orders->isEmpty())
        <div class="admin-empty"><h2>No orders found</h2><p>Orders appear here once customers check out.</p></div>
    @else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Assigned</th><th>Placed</th></tr></thead>
            <tbody>
            @foreach ($orders as $o)
                <tr>
                    <td data-label="Order"><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a></td>
                    <td data-label="Customer">{{ $o->user->email }}</td>
                    <td data-label="Total">{{ \App\Support\Money::inr($o->total_minor) }}</td>
                    <td data-label="Status"><x-status-badge :status="$o->status" /></td>
                    <td data-label="Assigned">{{ $o->assignee?->name ?? '—' }}</td>
                    <td data-label="Placed">{{ $o->created_at->format('d M Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $orders->links() }}
    @endif
@endsection
