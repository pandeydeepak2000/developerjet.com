@extends('layouts.dashboard')

@section('title', 'Orders')
@section('heading', 'Orders')
@section('subheading', 'Your order history.')

@section('dash')
    @if ($orders->isEmpty())
        <x-empty-state title="No orders yet" text="Orders appear here once you place one from your cart." action="Browse plans" :action-url="route('plans')" />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th scope="col">Order</th><th scope="col">Status</th><th scope="col">Total</th><th scope="col">Placed</th></tr></thead>
            <tbody>
            @foreach ($orders as $order)
                <tr><td><a href="{{ route('dashboard.orders.show', $order) }}">{{ $order->number }}</a></td><td><x-status-badge :status="$order->status" /></td><td>{{ \App\Support\Money::inr($order->total_minor) }}</td><td>{{ $order->created_at->format('d M Y') }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $orders->links() }}
    @endif
@endsection
