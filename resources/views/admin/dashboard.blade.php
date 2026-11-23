@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    @php $u = auth()->user(); @endphp
    <div class="admin-kpis dj-kpis">
        {{-- Financial figures only for staff who may see payments (not e.g. Technical Support or Sales). --}}
        @can('payments.view')<div class="admin-kpi dj-kpi"><span class="dj-kpi-icon" aria-hidden="true">Rs</span><span>Verified revenue</span><strong>{{ \App\Support\Money::inr($kpi['revenue']) }}</strong><small>Orders with verified payment</small></div>@endcan
        <a class="admin-kpi dj-kpi" href="{{ route('admin.orders.index') }}"><span class="dj-kpi-icon" aria-hidden="true">#</span><span>Orders</span><strong>{{ $kpi['orders'] }}</strong><small>All time - View all</small></a>
        <a class="admin-kpi dj-kpi" href="{{ route('admin.users.index') }}"><span class="dj-kpi-icon" aria-hidden="true">C</span><span>Customers</span><strong>{{ $kpi['customers'] }}</strong><small>Registered accounts</small></a>
        <a class="admin-kpi dj-kpi" href="{{ route('admin.resource.index', 'invoices') }}"><span class="dj-kpi-icon" aria-hidden="true">I</span><span>Invoices issued</span><strong>{{ $kpi['invoices'] }}</strong><small>All invoices</small></a>
        <a class="admin-kpi dj-kpi dj-kpi-alert" href="{{ route('admin.orders.index', ['status' => 'awaiting_provisioning']) }}"><span class="dj-kpi-icon" aria-hidden="true">!</span><span>Awaiting provisioning</span><strong>{{ $kpi['awaiting'] }}</strong><small>Needs a VPS or deployment</small></a>
        <a class="admin-kpi dj-kpi dj-kpi-alert" href="{{ route('admin.tickets.index') }}"><span class="dj-kpi-icon" aria-hidden="true">?</span><span>Open tickets</span><strong>{{ $kpi['tickets'] }}</strong><small>Open or awaiting reply</small></a>
    </div>
    <nav class="dj-quick-actions" aria-label="Quick actions">
        @can('plans.create')<a class="admin-btn admin-btn-quiet" href="{{ route('admin.plans.create') }}">+ New plan</a>@endcan
        @can('orders.view')<a class="admin-btn admin-btn-quiet" href="{{ route('admin.orders.index', ['status' => 'awaiting_provisioning']) }}">Provisioning queue</a>@endcan
        @can('tickets.view')<a class="admin-btn admin-btn-quiet" href="{{ route('admin.tickets.index') }}">Open tickets</a>@endcan
        @can('leads.view')<a class="admin-btn admin-btn-quiet" href="{{ route('admin.leads.index', ['status' => 'new']) }}">New leads</a>@endcan
        @can('servers.create')<a class="admin-btn admin-btn-quiet" href="{{ route('admin.resource.create', 'servers') }}">Record a VPS</a>@endcan
    </nav>

    <div class="admin-grid-2">
        @can('payments.view')
        <section class="admin-card" aria-labelledby="rev-h">
            <h2 id="rev-h">Revenue — last 14 days</h2>
            <div class="admin-bars" role="img" aria-label="Daily verified revenue for the last 14 days">
                @foreach ($series as $d)
                    <div class="admin-bar" title="{{ $d['label'] }}: {{ \App\Support\Money::inr($d['revenue']) }}"><span style="height: {{ max(2, $d['revenue'] / $maxRevenue * 100) }}%"></span><small>{{ \Illuminate\Support\Str::before($d['label'], ' ') }}</small></div>
                @endforeach
            </div>
            @if (collect($series)->sum('revenue') === 0)<p class="muted">No verified payments in this period.</p>@endif
        </section>
        @endcan
        <section class="admin-card" aria-labelledby="ord-h">
            <h2 id="ord-h">Orders placed — last 14 days</h2>
            <div class="admin-bars admin-bars-alt" role="img" aria-label="Orders placed per day for the last 14 days">
                @foreach ($series as $d)
                    <div class="admin-bar" title="{{ $d['label'] }}: {{ $d['orders'] }}"><span style="height: {{ max(2, $d['orders'] / $maxOrders * 100) }}%"></span><small>{{ \Illuminate\Support\Str::before($d['label'], ' ') }}</small></div>
                @endforeach
            </div>
            @if (collect($series)->sum('orders') === 0)<p class="muted">No orders in this period.</p>@endif
        </section>
    </div>

    @if ($sales)
        <div class="admin-grid-2">
            <section class="admin-card" aria-labelledby="sales-h">
                <h2 id="sales-h">Sales — my Business Automation leads</h2>
                <p class="small"><a href="{{ route('admin.leads.index', ['mine' => 1, 'status' => 'open']) }}">{{ $sales['mine_open'] }} open lead(s) assigned to me</a>@if ($sales['new'] !== null) · <a href="{{ route('admin.leads.index', ['status' => 'new']) }}">{{ $sales['new'] }} new, unassigned</a>@endif</p>
                @forelse ($sales['due'] as $lead)
                    <a class="admin-list-row" href="{{ route('admin.leads.show', $lead) }}"><span>{{ $lead->reference }} <small>{{ $lead->name }}</small></span><span class="small">Follow-up {{ $lead->follow_up_at->format('d M H:i') }}</span></a>
                @empty<p class="muted">No follow-ups due today.</p>@endforelse
            </section>
        </div>
    @endif

    <div class="admin-grid-2">
        @can('orders.view')
        <section class="admin-card"><h2>Recent transactions</h2>
            @forelse ($recent as $o)
                <a class="admin-list-row" href="{{ route('admin.orders.show', $o) }}"><span>{{ $o->number }} <small>{{ $o->user->email }}</small></span><span>{{ \App\Support\Money::inr($o->total_minor) }} <x-status-badge :status="$o->status" /></span></a>
            @empty<p class="muted">No orders yet.</p>@endforelse
        </section>
        <section class="admin-card"><h2>Pending deployments</h2>
            @forelse ($pendingProvisioning as $o)
                <a class="admin-list-row" href="{{ route('admin.orders.show', $o) }}"><span>{{ $o->number }} <small>{{ $o->user->email }}</small></span><x-status-badge :status="$o->status" /></a>
            @empty<p class="muted">Nothing awaiting provisioning.</p>@endforelse
        </section>
        @endcan
        @can('tickets.view')
        <section class="admin-card"><h2>Pending customer tickets</h2>
            @forelse ($tickets as $t)
                <a class="admin-list-row" href="{{ route('admin.tickets.show', $t) }}"><span>{{ $t->number }} <small>{{ $t->subject }}</small></span><x-status-badge :status="$t->status" /></a>
            @empty<p class="muted">No open tickets.</p>@endforelse
        </section>
        @endcan
        @can('deployments.view')
        <section class="admin-card"><h2>Recent deployments</h2>
            @forelse ($deployments as $d)
                <a class="admin-list-row" href="{{ route('admin.deployments.show', $d) }}"><span>#{{ $d->id }} <small>{{ $d->service->label }} · {{ $d->mode }}</small></span><x-status-badge :status="$d->status" /></a>
            @empty<p class="muted">No deployments yet.</p>@endforelse
        </section>
        @endcan
    </div>
@endsection
