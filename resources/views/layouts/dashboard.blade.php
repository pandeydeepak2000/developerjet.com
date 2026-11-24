<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B1736">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') · DeveloperJet</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    @vite(['resources/css/app.css', 'resources/css/refine.css', 'resources/css/site.css', 'resources/js/app.js'])
    <script>document.documentElement.classList.add('js');</script>
    @stack('head')
</head>
{{-- Customer area: its own app shell (full-height sidebar + slim top bar), separate from the marketing website. --}}
<body class="dj-body dj-app">
    <a class="skip-link" href="#main-content">Skip to content</a>
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
        $initials = collect(preg_split('/\s+/', trim($me->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $walletMinor = (int) ($me->wallet?->balance_minor ?? 0);
    @endphp

    <div class="dash dash-app">
        <aside class="dash-nav app-side" id="app-side" aria-label="Account navigation">
            <div class="app-side__top">
                <a class="app-brand" href="{{ route('dashboard') }}" aria-label="DeveloperJet client area"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="36" height="36"><span><strong>DeveloperJet</strong><small>Client area</small></span></a>
                <button class="app-side__close" type="button" aria-label="Close menu" data-app-close><x-icon name="x" :size="22" /></button>
            </div>
            <div class="dash-profile">
                <span class="dash-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                <span class="dash-user"><strong>{{ $me->name }}</strong><small>{{ $me->email }}</small></span>
            </div>
            <a class="dash-wallet" href="{{ route('dashboard.wallet') }}"><span>Wallet balance</span><strong>{{ \App\Support\Money::inr($walletMinor) }}</strong></a>
            <a class="dash-cta" href="{{ route('plans') }}"><x-icon name="spark" :size="18" /> New service</a>

            <nav id="dash-sections">
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
        <div class="app-overlay" data-app-overlay hidden></div>

        <div class="app-main">
            <header class="app-top">
                <button class="app-top__menu" type="button" aria-controls="app-side" aria-expanded="false" aria-label="Open menu" data-app-menu><x-icon name="menu" :size="22" /></button>
                <a class="app-top__brand" href="{{ route('dashboard') }}" aria-label="DeveloperJet dashboard"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="30" height="30"></a>
                <nav class="app-top__crumbs" aria-label="Breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a>@unless (request()->routeIs('dashboard'))<span aria-hidden="true">/</span><span aria-current="page">@yield('title')</span>@endunless</nav>
                <div class="app-top__actions">
                    <a class="app-top__link app-hide-sm" href="{{ route('home') }}"><x-icon name="globe" :size="18" /><span>Website</span></a>
                    <a class="app-top__link" href="{{ route('cart.index') }}" aria-label="Cart, {{ $cartCount }} {{ \Illuminate\Support\Str::plural('item', $cartCount) }}"><x-icon name="cart" :size="18" /><span class="app-hide-sm">Cart</span>@if ($cartCount > 0)<em>{{ $cartCount }}</em>@endif</a>
                    <a class="app-top__avatar" href="{{ route('dashboard.profile') }}" title="{{ $me->name }}" aria-label="Profile & security">{{ $initials ?: '?' }}</a>
                    <form method="POST" action="{{ route('logout') }}" data-loading-form>@csrf<button class="app-top__logout" type="submit" aria-label="Log out"><x-icon name="arrow-right" :size="16" /><span class="app-hide-sm" aria-hidden="true">Log out</span></button></form>
                </div>
            </header>

            <main id="main-content" class="dash-main app-content">
                @include('partials.flash')
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
            </main>

            <footer class="app-foot">
                <span>© {{ date('Y') }} DeveloperJet · AmbitionHost Pvt. Ltd.</span>
                <span><a href="{{ route('terms') }}">Terms</a><a href="{{ route('privacy') }}">Privacy</a><a href="{{ route('refund') }}">Refund policy</a><a href="{{ route('contact') }}">Contact</a></span>
            </footer>
        </div>
    </div>
</body>
</html>
