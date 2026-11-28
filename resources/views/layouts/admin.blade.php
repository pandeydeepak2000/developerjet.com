<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · DeveloperJet Admin</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    @vite(['resources/css/app.css', 'resources/css/refine.css', 'resources/css/site.css', 'resources/css/admin.css', 'resources/css/admin-theme.css', 'resources/js/app.js'])
    <script>try{var t=localStorage.getItem('dj-admin-theme');if(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)t='dark';if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
</head>
<body class="admin-body">
@php
    $u = auth()->user();
    $alerts = \App\Support\AdminAlerts::summary();
    // A short sidebar of sections; related pages are tabs at the top of each page (nothing removed, permissions unchanged).
    $sections = \App\Support\AdminNav::sections($u);
    $current = collect($sections)->firstWhere('active', true);
    $bell = array_sum($alerts);
    $initials = collect(preg_split('/\s+/', trim($u->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<a class="skip-link" href="#admin-main">Skip to content</a>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
        <button class="admin-sidebar__close" type="button" aria-label="Close navigation" data-admin-close><x-icon name="x" :size="20" /></button>
        <a class="admin-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="32" height="32"> <span>DeveloperJet <small>Admin</small></span></a>
        <nav class="admin-nav">
            @foreach ($sections as $section)
                <a href="{{ $section['href'] }}" @if ($section['active']) aria-current="page" @endif>
                    <x-icon :name="$section['icon']" :size="19" /><span>{{ $section['label'] }}</span>
                    @if ($section['label'] === 'Orders' && $alerts['provisioning'] && $u->hasPermission('orders.view'))<em class="admin-nav-count">{{ $alerts['provisioning'] }}</em>@endif
                    @if ($section['label'] === 'Support' && $alerts['tickets'] && $u->hasPermission('tickets.view'))<em class="admin-nav-count">{{ $alerts['tickets'] }}</em>@endif
                </a>
            @endforeach
        </nav>
        <details class="admin-me">
            <summary><span class="admin-me__avatar" aria-hidden="true">{{ $initials ?: '?' }}</span><span class="admin-me__name"><strong>{{ $u->name }}</strong><small>{{ $u->email }}</small></span></summary>
            <div class="admin-me__menu">
                <a href="{{ route('admin.profile') }}" @if (request()->routeIs('admin.profile*')) aria-current="page" @endif>My profile</a>
                <a href="{{ route('admin.security') }}" @if (request()->routeIs('admin.security*')) aria-current="page" @endif>My security</a>
                @if ($u->isSuper())
                    <a href="{{ route('admin.settings.staff-portal') }}" @if (request()->routeIs('admin.settings.staff-portal*')) aria-current="page" @endif>Staff portal URL</a>
                @endif
            </div>
        </details>
    </aside>
    {{-- Keep the sidebar where it was between pages (runs before paint, so no jump); always keep the current item in view. --}}
    <script>(function(){var s=document.getElementById('admin-sidebar');if(!s)return;var k='dj-admin-sidebar-scroll';try{var v=sessionStorage.getItem(k);if(v!==null)s.scrollTop=+v;}catch(e){}var a=s.querySelector('nav a[aria-current="page"]');if(a){var r=a.getBoundingClientRect(),b=s.getBoundingClientRect();if(r.top<b.top||r.bottom>b.bottom)s.scrollTop+=r.top-b.top-b.height/2+r.height/2;}var save=function(){try{sessionStorage.setItem(k,String(s.scrollTop));}catch(e){}};s.addEventListener('click',save);window.addEventListener('pagehide',save);})();</script>
    <div class="admin-overlay" data-admin-overlay hidden></div>
    <div class="admin-main-wrap">
        <header class="admin-topbar">
            <button class="admin-menu-btn" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation" data-admin-menu><x-icon name="menu" :size="22" /></button>
            <button class="admin-icon-btn admin-collapse-btn" type="button" aria-label="Collapse navigation" aria-expanded="true" data-admin-collapse><x-icon name="chevron-down" :size="20" /></button>
            @if ($u->hasPermission('customers.view'))
                <form class="admin-search" method="GET" action="{{ route('admin.users.index') }}" role="search">
                    <input type="search" name="q" placeholder="Search users…" aria-label="Search users">
                </form>
            @else<span></span>@endif
            <div class="admin-top-actions">
                @include('partials.theme-toggle', ['key' => 'dj-admin-theme', 'class' => 'admin-icon-btn'])
                <details class="admin-bell">
                    <summary aria-label="Notifications">🔔@if ($bell)<span class="admin-bell-count">{{ $bell }}</span>@endif</summary>
                    <div class="admin-bell-panel">
                        <strong>Needs attention</strong>
                        @if ($bell === 0)<p class="muted">Nothing needs attention.</p>@endif
                        @if ($alerts['topups'] && $u->hasPermission('wallet.view'))<a href="{{ route('admin.wallet.index') }}">{{ $alerts['topups'] }} wallet top-up request(s)</a>@endif
                        @if ($alerts['provisioning'] && $u->hasPermission('orders.view'))<a href="{{ route('admin.orders.index', ['status' => 'awaiting_provisioning']) }}">{{ $alerts['provisioning'] }} order(s) awaiting provisioning</a>@endif
                        @if ($alerts['tickets'] && $u->hasPermission('tickets.view'))<a href="{{ route('admin.tickets.index') }}">{{ $alerts['tickets'] }} open ticket(s)</a>@endif
                        @if ($alerts['refunds'] && $u->hasPermission('orders.view'))<a href="{{ route('admin.orders.index', ['status' => 'refund_pending']) }}">{{ $alerts['refunds'] }} refund(s) pending</a>@endif
                        @if ($alerts['billing_actions'] && $u->hasPermission('subscriptions.view'))<a href="{{ route('admin.billing.index', ['tab' => 'suspension']) }}">{{ $alerts['billing_actions'] }} billing task(s): suspension / reactivation</a>@endif
                        @if ($alerts['new_leads'] && $u->hasPermission('leads.assign'))<a href="{{ route('admin.leads.index', ['status' => 'new']) }}">{{ $alerts['new_leads'] }} new Business Automation lead(s)</a>@endif
                        @if ($alerts['failed_notifications'] && $u->hasPermission('notifications.view'))<a href="{{ route('admin.resource.index', ['resource' => 'notifications', 'status' => 'failed']) }}">{{ $alerts['failed_notifications'] }} failed notification(s)</a>@endif
                    </div>
                </details>
                <span class="admin-user">{{ $u->name }} <small>{{ $u->isAdmin() ? 'Owner' : ($u->staffRole?->name) }}</small></span>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="admin-link-btn" type="submit">Log out</button></form>
            </div>
        </header>
        <main id="admin-main" class="admin-content">
            {{-- Messages float top-right so they are seen wherever the page is scrolled (success hides itself). --}}
            <div class="admin-toasts" aria-live="polite">
                @if (session('success'))<div class="flash flash-success" data-toast data-autohide><span>{{ session('success') }}</span><button type="button" aria-label="Close" data-toast-close>&times;</button></div>@endif
                @if (session('error'))<div class="flash flash-error" role="alert" data-toast><span>{{ session('error') }}</span><button type="button" aria-label="Close" data-toast-close>&times;</button></div>@endif
                @if ($errors->any())<div class="flash flash-error" role="alert" data-toast><span>{{ $errors->first() }}</span><button type="button" aria-label="Close" data-toast-close>&times;</button></div>@endif
            </div>
            <nav class="dj-crumbs" aria-label="Breadcrumb"><a href="{{ route('admin.dashboard') }}">Admin</a>@if ($current && $current['label'] !== 'Dashboard')<span aria-hidden="true">/</span><a href="{{ $current['href'] }}">{{ $current['label'] }}</a>@endif<span aria-hidden="true">/</span><span aria-current="page">@yield('title', 'Admin')</span></nav>
            @if ($current && count($current['tabs']) > 1)
                <nav class="admin-tabs" aria-label="{{ $current['label'] }} pages">@foreach ($current['tabs'] as $tab)<a href="{{ $tab['href'] }}" @if ($tab['active']) aria-current="page" @endif>{{ $tab['label'] }}</a>@endforeach</nav>
            @endif
            <div class="admin-page-head"><h1>@yield('heading')</h1><div class="admin-page-actions">@yield('actions')</div></div>
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
