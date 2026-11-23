<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · DeveloperJet Admin</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    @vite(['resources/css/app.css', 'resources/css/refine.css', 'resources/css/site.css', 'resources/css/admin.css', 'resources/js/app.js'])
    <script>try{var t=localStorage.getItem('dj-admin-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
</head>
<body class="admin-body">
@php
    $u = auth()->user();
    $alerts = \App\Support\AdminAlerts::summary();
    $nav = [
        'Overview' => [['Dashboard', 'admin.dashboard', 'dashboard.view', 'admin.dashboard']],
        'People' => [
            ['User Manager', 'admin.users.index', 'customers.view', 'admin/users*'],
            ['Staff & roles', 'admin.staff.index', 'staff.view', 'admin/staff*'],
            ['Departments', 'admin.resource.index:departments', 'tickets.view', 'admin/r/departments*'],
        ],
        'Commerce' => [
            ['Orders', 'admin.orders.index', 'orders.view', 'admin/orders*'],
            ['Payments', 'admin.resource.index:payments', 'payments.view', 'admin/r/payments*'],
            ['Wallet', 'admin.wallet.index', 'wallet.view', 'admin/wallet*'],
            ['Invoices', 'admin.resource.index:invoices', 'invoices.view', 'admin/r/invoices*'],
            ['Billing & renewals', 'admin.billing.index', 'subscriptions.view', 'admin.billing.*'],
            ['Billing operations', 'admin.billing-ops', 'invoices.view', 'admin/billing-ops*'],
            ['Subscriptions', 'admin.resource.index:subscriptions', 'subscriptions.view', 'admin/r/subscriptions*'],
        ],
        'Catalogue' => [
            ['Products & plans', 'admin.plans.index', 'plans.view', 'admin/plans*'],
            ['Categories', 'admin.resource.index:categories', 'categories.view', 'admin/r/categories*'],
            ['Add-ons', 'admin.resource.index:addons', 'addons.view', 'admin/r/addons*'],
        ],
        'Infrastructure' => [
            ['Servers', 'admin.resource.index:servers', 'servers.view', 'admin/r/servers*'],
            ['Infrastructure', 'admin.infrastructure', 'servers.view', 'admin/infrastructure*'],
            ['Providers', 'admin.resource.index:providers', 'servers.view', 'admin/r/providers*'],
            ['Provider offers', 'admin.resource.index:provider_offers', 'servers.view', 'admin/r/provider_offers*'],
            ['Server pools', 'admin.resource.index:server_pools', 'servers.view', 'admin/r/server_pools*'],
            ['Deployments', 'admin.resource.index:deployments', 'deployments.view', 'admin/r/deployments*'],
        ],
        'Sales' => [
            ['Automation leads', 'admin.leads.index', 'leads.view', 'admin/leads*'],
        ],
        'Services' => [
            ['Quote requests', 'admin.resource.index:service_requests', 'projects.view', 'admin/r/service_requests*'],
            ['Projects', 'admin.projects.index', 'projects.view', 'admin/projects*'],
            ['SEO services', 'admin.seo.index', 'seo.view', 'admin/seo*'],
        ],
        'Support' => [
            ['Support tickets', 'admin.tickets.index', 'tickets.view', 'admin/tickets*'],
            ['Documentation', 'admin.resource.index:docs', 'docs.view', 'admin/r/docs*'],
            ['Doc categories', 'admin.resource.index:doc_categories', 'docs.view', 'admin/r/doc_categories*'],
        ],
        'Administration' => [
            ['Notifications', 'admin.resource.index:notifications', 'notifications.view', 'admin/r/notifications*'],
            ['Settings', 'admin.settings.edit', 'settings.view', 'admin/settings*'],
            ['Security logs', 'admin.resource.index:security_logs', 'security_logs.view', 'admin/r/security_logs*'],
            ['Exports', 'admin.exports', '__export', 'admin/exports*'],
        ],
    ];
    $bell = array_sum($alerts);
@endphp
<a class="skip-link" href="#admin-main">Skip to content</a>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
        <button class="admin-sidebar__close" type="button" aria-label="Close navigation" data-admin-close><x-icon name="x" :size="20" /></button>
        <a class="admin-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="28" height="28"> <span>DeveloperJet <small>Admin</small></span></a>
        <nav>
            @foreach ($nav as $group => $items)
                @php $visible = collect($items)->filter(fn ($i) => $i[2] === '__export' ? app(\App\Services\ExportService::class)->allowedTypes($u) !== [] : $u->hasPermission($i[2])); @endphp
                @if ($visible->isNotEmpty())
                    <p class="admin-nav-group">{{ $group }}</p>
                    @foreach ($visible as [$label, $route, , $pattern])
                        @php
                            [$name, $param] = array_pad(explode(':', $route), 2, null);
                            $href = $param ? route($name, $param) : route($name);
                            $icon = match ($label) {
                                'Dashboard' => 'dashboard', 'User Manager' => 'user', 'Orders' => 'cart',
                                'Payments', 'Wallet' => 'card', 'Invoices' => 'book',
                                'Billing & renewals', 'Billing operations' => 'activity', 'Subscriptions' => 'layers',
                                'Products & plans' => 'layers', 'Categories' => 'grid', 'Add-ons' => 'spark',
                                'Servers' => 'server', 'Infrastructure' => 'network', 'Providers' => 'globe',
                                'Provider offers' => 'scale', 'Server pools' => 'server', 'Deployments' => 'rocket',
                                'Automation leads' => 'workflow', 'Quote requests' => 'chat', 'Projects' => 'terminal',
                                'SEO services' => 'search', 'Support tickets' => 'headset', 'Departments' => 'user',
                                'Documentation', 'Doc categories' => 'book', 'Staff & roles' => 'key',
                                'Notifications' => 'mail', 'Settings' => 'settings', 'Security logs' => 'shield',
                                'Exports' => 'disk', default => 'dashboard',
                            };
                        @endphp
                        <a href="{{ $href }}" @if (request()->is($pattern) || request()->routeIs($pattern)) aria-current="page" @endif><x-icon :name="$icon" :size="18" /><span>{{ $label }}</span></a>
                    @endforeach
                @endif
            @endforeach
            <p class="admin-nav-group">Account</p>
            <a href="{{ route('admin.profile') }}" @if (request()->routeIs('admin.profile*')) aria-current="page" @endif>My profile</a>
            <a href="{{ route('admin.security') }}" @if (request()->routeIs('admin.security*')) aria-current="page" @endif>My security</a>
            @if ($u->isSuper())
                <a href="{{ route('admin.settings.staff-portal') }}" @if (request()->routeIs('admin.settings.staff-portal*')) aria-current="page" @endif>Staff portal URL</a>
            @endif
        </nav>
        <p class="admin-sidebar-user"><strong>{{ $u->name }}</strong><small>{{ $u->email }}</small></p>
    </aside>
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
                <button type="button" class="admin-icon-btn" data-theme-toggle aria-label="Toggle light/dark theme">◐</button>
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
            @if (session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="flash flash-error" role="alert">{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
            <nav class="dj-crumbs" aria-label="Breadcrumb"><a href="{{ route('admin.dashboard') }}">Admin</a><span aria-hidden="true">/</span><span aria-current="page">@yield('title', 'Admin')</span></nav>
            <div class="admin-page-head"><h1>@yield('heading')</h1><div class="admin-page-actions">@yield('actions')</div></div>
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
