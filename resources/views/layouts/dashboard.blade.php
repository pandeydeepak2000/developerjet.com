@extends('layouts.app')

@section('content')
    @php
        $dashNav = [
            'Account' => [
                ['dashboard', 'Overview', 'dashboard'],
                ['dashboard.services', 'My services', 'dashboard.services*'],
                ['dashboard.orders', 'Orders', 'dashboard.orders*'],
                ['dashboard.projects', 'Projects', 'dashboard.projects*'],
                ['dashboard.requests', 'Quotes', 'dashboard.requests*'],
                ['dashboard.automation', 'Automation requests', 'dashboard.automation*'],
            ],
            'Billing' => array_values(array_filter([
                \Illuminate\Support\Facades\Route::has('dashboard.subscriptions') ? ['dashboard.subscriptions', 'Subscriptions & renewals', 'dashboard.subscriptions*'] : null,
                ['dashboard.invoices', 'Invoices', 'dashboard.invoices*'],
                ['dashboard.payments', 'Payments', 'dashboard.payments*'],
                ['dashboard.wallet', 'Wallet', 'dashboard.wallet*'],
            ])),
            'Help' => array_values(array_filter([
                ['dashboard.tickets', 'Support tickets', 'dashboard.tickets*'],
                \Illuminate\Support\Facades\Route::has('dashboard.profile') ? ['dashboard.profile', 'Profile & security', 'dashboard.profile*'] : null,
                ['docs.index', 'Documentation', 'docs.*'],
            ])),
        ];
        $currentLabel = 'Menu';
        foreach ($dashNav as $items) {
            foreach ($items as [$r, $l, $p]) {
                if (request()->routeIs($p)) { $currentLabel = $l; }
            }
        }
    @endphp
    <div class="dash section-shell">
        <aside class="dash-nav" aria-label="Account navigation">
            <p class="dash-user"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></p>
            <button class="dash-nav__toggle" type="button" aria-expanded="false" aria-controls="dash-sections" data-dash-toggle>
                <span><small>Account menu</small>{{ $currentLabel }}</span>
                <x-icon name="chevron-down" :size="18" />
            </button>
            <nav id="dash-sections" data-dash-sections>
                @foreach ($dashNav as $group => $items)
                    <p class="dash-nav__group">{{ $group }}</p>
                    @foreach ($items as [$route, $label, $pattern])
                        <a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                @endforeach
                @if (auth()->user()->isStaff())
                    {{-- Staff use the separate staff portal sign-in (its own session). --}}
                    <a href="{{ \App\Support\StaffPortal::url('login') }}">Staff portal ↗</a>
                @endif
            </nav>
        </aside>
        <div class="dash-main">
            @if (session('impersonator_id'))
                <div class="flash flash-warning" role="status">You are viewing as {{ auth()->user()->name }} ({{ auth()->user()->email }}) — read-only. <form method="POST" action="{{ route('admin.impersonation.exit') }}" style="display:inline">@csrf<button class="admin-link-btn" type="submit">Exit impersonation &amp; return to admin</button></form></div>
            @endif
            <header class="dash-head">
                <h1>@yield('heading')</h1>
                <p>@yield('subheading')</p>
            </header>
            @if (! auth()->user()->phone && ! request()->routeIs('dashboard.profile'))
                <div class="flash-warning" role="status">Please <a href="{{ route('dashboard.profile') }}">add your phone number</a> so our team can reach you about your services and billing.</div>
            @endif
            @yield('dash')
        </div>
    </div>
@endsection
