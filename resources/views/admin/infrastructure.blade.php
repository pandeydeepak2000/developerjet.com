@extends('layouts.admin')

@section('title', 'Infrastructure')
@section('heading', 'Infrastructure')

@section('content')
    <div class="flash flash-info" role="note"><strong>Hybrid KVM — initial mode: on-demand purchase + manual assignment.</strong> Suppliers have no verified API, so you buy the VPS from the supplier, record it in Servers, assign it to the order, then start the deployment. Automatic provisioning and Proxmox KVM are disabled.</div>

    <div class="admin-kpis">
        <div class="admin-kpi"><span>Paid, need a VPS</span><strong>{{ $queue->count() }}</strong></div>
        <div class="admin-kpi"><span>Unallocated servers</span><strong>{{ $unallocated }}</strong></div>
        <div class="admin-kpi"><span>Open monitoring alerts</span><strong>{{ $alerts->count() }}</strong></div>
    </div>

    <section class="admin-card"><h2>Awaiting VPS assignment</h2>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Plan</th><th>Needs</th><th>Access</th></tr></thead><tbody>
            @forelse ($queue as $s)
                <tr><td data-label="Order">@if ($s->order)<a href="{{ route('admin.orders.show', $s->order) }}">{{ $s->order->number }}</a>@endif</td><td data-label="Customer">{{ $s->user->email }}</td><td data-label="Plan">{{ $s->plan?->name }}</td>
                    <td data-label="Needs">{{ $s->plan?->resourceProfile?->summary() ?? 'Resources not confirmed' }}</td><td data-label="Access">{{ ucfirst($s->plan?->access_model ?? 'managed') }}</td></tr>
            @empty<tr><td colspan="5" class="muted">Nothing is waiting for a server.</td></tr>@endforelse
        </tbody></table></div>
        @can('servers.create')<p><a class="admin-btn" href="{{ route('admin.resource.create', 'servers') }}">Record a newly purchased VPS</a></p>@endcan
    </section>

    <section class="admin-card"><h2>Provider capability matrix</h2>
        <p class="muted small">A control shows “Yes” only when the provider has a <strong>verified</strong> API and the control was explicitly verified. Everything else is unavailable to customers.</p>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Provider</th><th>Method</th><th>API</th><th>Servers</th>@foreach ($controls as $c)<th>{{ $c['label'] }}</th>@endforeach</tr></thead><tbody>
            @foreach ($providers as $p)
                <tr><td data-label="Provider">{{ $p->name }}@unless ($p->is_active) <span class="badge badge-neutral">inactive</span>@endunless</td><td data-label="Method">{{ $p->provisioning_method }}</td><td data-label="API"><x-status-badge :status="$p->api_status === 'verified' ? 'verified' : 'pending'" /> {{ $p->api_status }}</td><td data-label="Servers">{{ $p->servers_count }}</td>
                    @foreach ($controls as $key => $c)<td data-label="{{ $c['label'] }}">{{ $c['scope'] === 'infrastructure' && $p->supports($key) ? 'Yes' : '—' }}</td>@endforeach</tr>
            @endforeach
        </tbody></table></div>
    </section>

    <div class="admin-grid-2">
        <section class="admin-card"><h2>Supplier renewals</h2>
            @forelse ($renewals as $r)
                <div class="admin-list-row"><span>{{ $r->label }} <small>{{ $r->supplier?->name }}</small></span><span @class(['text-danger' => $r->renewal_date->isPast()])>{{ $r->renewal_date->format('d M Y') }}</span></div>
            @empty<p class="muted">No renewal dates recorded.</p>@endforelse
        </section>
        <section class="admin-card"><h2>Monitoring alerts</h2>
            @forelse ($alerts as $a)
                <div class="admin-list-row"><span>{{ $a->server?->label }} · {{ $a->check }}</span><span><x-status-badge :status="$a->status" /> {{ $a->detail }}</span></div>
            @empty<p class="muted">No warnings or failures recorded. Live monitoring is not connected yet, so this list only shows checks recorded by staff or future workers.</p>@endforelse
        </section>
    </div>
@endsection
