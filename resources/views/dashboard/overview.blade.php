@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('heading', 'Welcome, '.\Illuminate\Support\Str::of(auth()->user()->name)->before(' '))
@section('subheading', 'Your DeveloperJet account at a glance.')

@section('dash-actions')
    <a class="button-primary dash-btn" href="{{ route('plans') }}"><x-icon name="spark" :size="18" /> New service</a>
@endsection

@section('dash')
    @php $me = auth()->user(); @endphp

    <div class="dash-stats">
        <a class="dash-stat" href="{{ route('dashboard.services') }}"><span class="dash-stat__icon"><x-icon name="server" :size="20" /></span><span>Services</span><strong>{{ $serviceCount }}</strong></a>
        <a class="dash-stat" href="{{ route('dashboard.orders') }}"><span class="dash-stat__icon"><x-icon name="cart" :size="20" /></span><span>Orders</span><strong>{{ $orderCount }}</strong></a>
        <a class="dash-stat {{ $unpaidCount ? 'is-alert' : '' }}" href="{{ route('dashboard.invoices') }}"><span class="dash-stat__icon"><x-icon name="book" :size="20" /></span><span>Invoices</span><strong>{{ $invoiceCount }}</strong>@if ($unpaidCount)<small>{{ $unpaidCount }} unpaid</small>@endif</a>
        <a class="dash-stat" href="{{ route('dashboard.wallet') }}"><span class="dash-stat__icon"><x-icon name="layers" :size="20" /></span><span>Wallet balance</span><strong>{{ \App\Support\Money::inr($wallet->balance_minor) }}</strong></a>
    </div>

    <div class="dash-grid">
        <section class="dash-card dash-card--wide">
            <div class="dash-card__head"><h2>Your services</h2>@if ($serviceCount)<a href="{{ route('dashboard.services') }}">View all</a>@endif</div>
            @forelse ($recentServices as $service)
                <a class="dash-service" href="{{ route('dashboard.services.show', $service) }}">
                    <span class="dash-service__icon"><x-icon name="server" :size="20" /></span>
                    <span class="dash-service__body"><strong>{{ $service->label ?? $service->plan?->name }}</strong><small>{{ $service->plan?->category?->name }} · {{ $service->plan?->name }}</small></span>
                    <x-status-badge :status="$service->status" />
                </a>
            @empty
                <div class="dash-empty">
                    <span class="dash-empty__icon"><x-icon name="rocket" :size="26" /></span>
                    <h3>{{ $orderCount ? 'No services yet' : 'No orders yet' }}</h3>
                    <p>Pick a plan for Hermes, n8n, OpenClaw or a Docker VPS. Our team sets up the server after payment.</p>
                    <a class="button-primary dash-btn" href="{{ route('plans') }}">Browse plans <x-icon name="arrow-right" :size="16" /></a>
                </div>
            @endforelse
        </section>

        <section class="dash-card">
            <div class="dash-card__head"><h2>Quick actions</h2></div>
            <div class="dash-actions">
                <a href="{{ route('plans') }}"><x-icon name="spark" :size="18" /><span>Order a new service</span></a>
                <a href="{{ route('dashboard.wallet') }}"><x-icon name="layers" :size="18" /><span>Add funds to wallet</span></a>
                <a href="{{ route('dashboard.tickets.create') }}"><x-icon name="headset" :size="18" /><span>Open a support ticket</span>@if ($openTickets)<em>{{ $openTickets }} open</em>@endif</a>
                <a href="{{ route('docs.index') }}"><x-icon name="info" :size="18" /><span>Read the guides</span></a>
            </div>
        </section>

        @if ($upcoming->isNotEmpty())
            <section class="dash-card">
                <div class="dash-card__head"><h2>Upcoming renewals</h2><a href="{{ route('dashboard.subscriptions') }}">All</a></div>
                <ul class="dash-list">
                    @foreach ($upcoming as $sub)
                        <li>
                            <span><strong>{{ $sub->service?->label ?? $sub->plan?->name }}</strong><small>{{ $sub->cycleLabel() }} · due {{ $sub->next_due_at->format('d M Y') }}</small></span>
                            <x-status-badge :status="$sub->status" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($orderCount === 0)
            <section class="dash-card">
                <div class="dash-card__head"><h2>Getting started</h2></div>
                <ol class="dash-steps">
                    <li class="is-done"><x-icon name="check" :size="16" /> Email verified</li>
                    <li class="{{ $me->phone ? 'is-done' : '' }}"><x-icon name="check" :size="16" /> @if ($me->phone) Phone number added @else <a href="{{ route('dashboard.profile') }}">Add your phone number</a> @endif</li>
                    <li><x-icon name="check" :size="16" /> <a href="{{ route('plans') }}">Choose a plan and check out</a></li>
                    <li><x-icon name="check" :size="16" /> We prepare your server and share access here</li>
                </ol>
            </section>
        @endif
    </div>
@endsection
