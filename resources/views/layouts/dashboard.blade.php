@extends('layouts.app')

@section('content')
    @php
        $me = auth()->user();
        $dashNav = [
            'Account' => [
                ['dashboard', 'Overview', 'dashboard', 'dashboard'],
                ['dashboard.services', 'My services', 'dashboard.services*', 'server'],
                ['dashboard.orders', 'Orders', 'dashboard.orders*', 'cart'],
                ['dashboard.projects', 'Projects', 'dashboard.projects*', 'code'],
                ['dashboard.requests', 'Quotes', 'dashboard.requests*', 'chat'],
                ['dashboard.automation', 'Automation requests', 'dashboard.automation*', 'workflow'],
            ],
            'Billing' => array_values(array_filter([
                \Illuminate\Support\Facades\Route::has('dashboard.subscriptions') ? ['dashboard.subscriptions', 'Subscriptions & renewals', 'dashboard.subscriptions*', 'clock'] : null,
                ['dashboard.invoices', 'Invoices', 'dashboard.invoices*', 'book'],
                ['dashboard.payments', 'Payments', 'dashboard.payments*', 'card'],
                ['dashboard.wallet', 'Wallet', 'dashboard.wallet*', 'layers'],
            ])),
            'Help' => array_values(array_filter([
                ['dashboard.tickets', 'Support tickets', 'dashboard.tickets*', 'headset'],
                \Illuminate\Support\Facades\Route::has('dashboard.profile') ? ['dashboard.profile', 'Profile & security', 'dashboard.profile*', 'user'] : null,
                ['docs.index', 'Documentation', 'docs.*', 'info'],
            ])),
        ];
        $currentLabel = 'Menu';
        $currentIcon = 'menu';
        foreach ($dashNav as $items) {
            foreach ($items as [$r, $l, $p, $i]) {
                if (request()->routeIs($p)) { $currentLabel = $l; $currentIcon = $i; }
            }
        }
        $initials = collect(preg_split('/\s+/', trim($me->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $walletMinor = (int) ($me->wallet?->balance_minor ?? 0);
    @endphp
    <div class="dash section-shell">
        <aside class="dash-nav" aria-label="Account navigation">
            <div class="dash-profile">
                <span class="dash-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                <span class="dash-user"><strong>{{ $me->name }}</strong><small>{{ $me->email }}</small></span>
            </div>
            <a class="dash-wallet" href="{{ route('dashboard.wallet') }}"><span>Wallet balance</span><strong>{{ \App\Support\Money::inr($walletMinor) }}</strong></a>
            <a class="dash-cta" href="{{ route('plans') }}"><x-icon name="spark" :size="18" /> New service</a>

            <button class="dash-nav__toggle" type="button" aria-expanded="false" aria-controls="dash-sections" data-dash-toggle>
                <span class="dash-nav__current"><x-icon :name="$currentIcon" :size="18" /><span><small>Account menu</small>{{ $currentLabel }}</span></span>
                <x-icon name="chevron-down" :size="18" />
            </button>
            <nav id="dash-sections" data-dash-sections>
                @foreach ($dashNav as $group => $items)
                    <p class="dash-nav__group">{{ $group }}</p>
                    @foreach ($items as [$route, $label, $pattern, $icon])
                        <a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif><x-icon :name="$icon" :size="18" /><span>{{ $label }}</span></a>
                    @endforeach
                @endforeach
                @if ($me->isStaff())
                    {{-- Staff use the separate staff portal sign-in (its own session). --}}
                    <a href="{{ \App\Support\StaffPortal::url('login') }}"><x-icon name="key" :size="18" /><span>Staff portal ↗</span></a>
                @endif
            </nav>

            <div class="dash-help">
                <strong>Need help?</strong>
                <p>Our team replies to tickets about servers, billing and setup.</p>
                <a href="{{ route('dashboard.tickets.create') }}">Open a ticket <x-icon name="arrow-right" :size="16" /></a>
            </div>
        </aside>
        <div class="dash-main">
            @if (session('impersonator_id'))
                <div class="flash flash-warning" role="status">You are viewing as {{ $me->name }} ({{ $me->email }}) — read-only. <form method="POST" action="{{ route('admin.impersonation.exit') }}" style="display:inline">@csrf<button class="admin-link-btn" type="submit">Exit impersonation &amp; return to admin</button></form></div>
            @endif
            <header class="dash-head">
                <div>
                    <h1>@yield('heading')</h1>
                    <p>@yield('subheading')</p>
                </div>
                @hasSection('dash-actions')<div class="dash-head__actions">@yield('dash-actions')</div>@endif
            </header>
            @if (! $me->phone && ! request()->routeIs('dashboard.profile'))
                <div class="flash-warning dash-notice" role="status"><x-icon name="phone" :size="18" /> <span>Please <a href="{{ route('dashboard.profile') }}">add your phone number</a> so our team can reach you about your services and billing.</span></div>
            @endif
            @yield('dash')
        </div>
    </div>
@endsection
