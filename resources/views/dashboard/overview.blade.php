@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('heading', 'Welcome, '.\Illuminate\Support\Str::of(auth()->user()->name)->before(' '))
@section('subheading', 'Your DeveloperJet account at a glance.')

@section('dash')
    <div class="stat-grid">
        <x-stat-card label="Orders" :value="$orderCount" :href="route('dashboard.orders')" />
        <x-stat-card label="Services" :value="$serviceCount" :href="route('dashboard.services')" />
        <x-stat-card label="Invoices" :value="$invoiceCount" :href="route('dashboard.invoices')" :note="$unpaidCount ? $unpaidCount.' unpaid' : null" />
        <x-stat-card label="Wallet balance" :value="\App\Support\Money::inr($wallet->balance_minor)" :href="route('dashboard.wallet')" />
    </div>

    @if ($upcoming->isNotEmpty())
        <div class="panel">
            <div class="panel-head"><h2>Upcoming renewals</h2><a href="{{ route('dashboard.subscriptions') }}">All subscriptions</a></div>
            <ul class="renewal-list">
                @foreach ($upcoming as $sub)
                    <li>
                        <span><strong>{{ $sub->service?->label ?? $sub->plan?->name }}</strong><small>{{ $sub->cycleLabel() }} · due {{ $sub->next_due_at->format('d M Y') }}</small></span>
                        <x-status-badge :status="$sub->status" />
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($orderCount === 0)
        <x-empty-state title="No orders yet" text="Browse plans and add one to your cart. Plans that are not yet open for online orders can be arranged with our Sales team." action="Browse plans" :action-url="route('plans')" />
    @endif
@endsection
