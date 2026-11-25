@extends('layouts.admin')

@section('title', 'Order '.$order->number)
@section('heading', 'Order '.$order->number)

@section('actions')<x-status-badge :status="$order->status" />@endsection

@section('content')
    @php $u = auth()->user(); @endphp

    {{-- 1. Who ordered what (read-only summary). --}}
    <section class="admin-card dj-order-hero">
        <dl class="dj-hero-facts">
            <div><dt>Customer</dt><dd>{{ $order->user->name }}<br><small class="muted">{{ $order->user->email }}</small></dd></div>
            <div><dt>Plan</dt><dd>@foreach ($order->items as $i){{ $i->name }} <small class="muted">({{ \App\Support\BillingCycle::label($i->billing_interval) }})</small>@if (! $loop->last)<br>@endif @endforeach</dd></div>
            <div><dt>Total</dt><dd>{{ \App\Support\Money::inr($order->total_minor) }}</dd></div>
            <div><dt>Paid</dt><dd>{{ $order->paid_at?->format('d M Y, H:i') ?? 'Not paid' }} <small class="muted">{{ $order->payment_method ? '· '.$order->payment_method : '' }}</small></dd></div>
            <div><dt>Placed</dt><dd>{{ $order->created_at->format('d M Y, H:i') }}</dd></div>
        </dl>
        @if ($u->hasPermission('orders.assign'))
            <form method="POST" action="{{ route('admin.orders.assign', $order) }}" class="admin-inline-form">@csrf
                <select name="assigned_to" aria-label="Staff handling this order"><option value="">Unassigned</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($order->assigned_to === $s->id)>{{ $s->name }}</option>@endforeach</select>
                <button class="admin-btn admin-btn-quiet" type="submit">Assign</button>
            </form>
        @endif
    </section>

    {{-- 2. Setup: server → install → delivered. --}}
    @foreach ($order->services as $service)
        @php
            $canSetup = $u->hasPermission('servers.assign') && in_array($service->status, ['pending_provisioning', 'failed']);
            $askAddress = $service->needsDomain() && ! ($service->domain_verified && $service->custom_domain);
            $profile = $service->plan?->resourceProfile;
            $srv = $service->server;
            $delivered = $service->status === 'active';
            $lastDeployment = \App\Models\Deployment::where('service_id', $service->id)->latest('id')->first();
            $step = $delivered ? 3 : ($srv && (! $askAddress || $service->appDomain()) ? 2 : 1);
        @endphp
        <section class="admin-card" @if ($loop->first) id="setup" @endif>
            <div class="dj-order-hero">
                <div>
                    <h2 style="margin:0">{{ $service->label }}</h2>
                    <p class="muted small" style="margin:4px 0 0">@if ($profile){{ $profile->summary() }} · {{ $profile->disk_gb }} GB disk @else Size not in catalogue — enter it in step 1 @endif</p>
                </div>
                <x-status-badge :status="$service->status" />
            </div>

            <ol class="dj-stepper" aria-label="Setup steps">
                <li class="{{ $step > 1 ? 'is-done' : 'is-current' }}"><b>{{ $step > 1 ? '✓' : '1' }}</b> Server{{ $service->needsDomain() ? ' & address' : '' }}</li>
                <li class="{{ $step > 2 ? 'is-done' : ($step === 2 ? 'is-current' : '') }}"><b>{{ $step > 2 ? '✓' : '2' }}</b> Install</li>
                <li class="{{ $delivered ? 'is-done' : '' }}"><b>{{ $delivered ? '✓' : '3' }}</b> Delivered</li>
            </ol>

            <dl class="admin-setup-facts">
                <div><dt>Server</dt><dd>@if ($srv)<strong>{{ $srv->ip_address }}</strong> <small class="muted">{{ $srv->ssh_user.'@'.$srv->ip_address.':'.$srv->ssh_port }} · {{ $srv->getRawOriginal('ssh_credential') ? 'password saved' : 'no password saved' }}</small>@else<span class="badge badge-warn">Not added yet</span>@endif</dd></div>
                @if ($service->needsDomain())
                    <div><dt>App address</dt><dd>
                        @if ($service->appDomain())<strong>{{ $service->appDomain() }}</strong>
                            @if ($service->custom_domain && $service->domain_verified)<span class="badge badge-good">Customer domain · verified</span>@endif
                        @elseif ($service->custom_domain)Customer domain <strong>{{ $service->custom_domain }}</strong> <span class="badge badge-warn">waiting for customer DNS check</span>
                        @else<span class="badge badge-warn">Not set yet</span>@endif
                    </dd></div>
                @endif
                @if ($delivered && $service->url)<div><dt>Live at</dt><dd><a class="admin-strong-link" href="{{ $service->url }}" target="_blank" rel="noopener">{{ $service->url }}</a></dd></div>@endif
            </dl>

            @if ($canSetup)
                @php
                    $pickable = $srv ? collect() : $servers;
                    $picked = (string) old('server_id', '');
                @endphp
                <form method="POST" action="{{ route('admin.services.setup', $service) }}" class="admin-form admin-setup-form dj-step-card" data-loading-form data-setup-form>@csrf
                    <h3><span class="admin-step-num">1</span> Server{{ $askAddress ? ' & app address' : '' }}</h3>
                    <div class="admin-form-grid">
                        @if ($pickable->isNotEmpty())
                            <label class="span-2">Server
                                <select name="server_id" data-setup-server>
                                    <option value="">New VPS — I will type the IP and password below</option>
                                    @foreach ($pickable as $s)
                                        <option value="{{ $s->id }}" @selected($picked === (string) $s->id)>{{ $s->label.($s->label !== $s->ip_address ? ' — '.$s->ip_address : '').' ('.($s->sizeUnknown() ? 'size from plan' : $s->cpu_capacity.'c/'.$s->ram_capacity_gb.'G/'.$s->storage_capacity_gb.'G').')' }}</option>
                                    @endforeach
                                </select>
                                <small class="muted">Already added this VPS under Servers? Pick it here.</small>
                            </label>
                        @endif
                        <label data-new-vps>VPS IP address <input name="ip_address" value="{{ old('ip_address', $srv?->ip_address) }}" placeholder="203.0.113.10" inputmode="decimal"></label>
                        <label data-new-vps>Username <input name="ssh_user" value="{{ old('ssh_user', $srv?->ssh_user ?? 'root') }}" placeholder="root"></label>
                        <label data-new-vps>Root password
                            <input type="password" name="ssh_password" autocomplete="new-password"
                                   placeholder="{{ $srv?->getRawOriginal('ssh_credential') ? '•••• saved — leave blank to keep' : 'From the VPS supplier email' }}">
                            <small class="muted">Stored encrypted, never shown again.</small>
                        </label>
                        <label data-new-vps>SSH port <input type="number" name="ssh_port" value="{{ old('ssh_port', $srv?->ssh_port ?? 22) }}" min="1" max="65535"></label>
                        @if ($askAddress)
                            <label class="span-2">App address (subdomain)
                                <input name="assigned_domain" value="{{ old('assigned_domain', $service->config['assigned_domain'] ?? $service->custom_domain) }}" placeholder="client1.developerjet.com" maxlength="190" required>
                                <small class="muted">The app will open on this address. Add a DNS A record for it pointing to the VPS IP.</small>
                            </label>
                        @endif
                        @unless ($profile)
                            <label>vCPU <input type="number" name="vcpu" min="1" max="256" required value="{{ old('vcpu', $service->config['confirmed_resources']['vcpu'] ?? '') }}"></label>
                            <label>RAM (GB) <input type="number" name="ram_gb" min="1" max="4096" required value="{{ old('ram_gb', $service->config['confirmed_resources']['ram_gb'] ?? '') }}"></label>
                            <label>Disk (GB) <input type="number" name="disk_gb" min="1" max="65536" required value="{{ old('disk_gb', $service->config['confirmed_resources']['disk_gb'] ?? '') }}"></label>
                        @endunless
                    </div>
                    <div class="admin-form-actions"><button class="admin-btn" type="submit">{{ $srv ? 'Update' : 'Save' }}</button></div>
                </form>
            @endif

            @if ($u->hasPermission('deployments.deploy') && $service->server_id && ! $delivered)
                <div class="dj-step-card">
                    <h3><span class="admin-step-num">2</span> Install</h3>
                    <div class="dj-deploy-row">
                        <form method="POST" action="{{ route('admin.services.deploy', $service) }}" class="admin-inline-form" onsubmit="return confirm('Start the install on {{ $srv->ip_address }}?')">@csrf
                            <input type="hidden" name="mode" value="live"><button class="admin-btn admin-btn-lg" type="submit">▶ Deploy</button></form>
                        @if ($lastDeployment)<a class="admin-btn admin-btn-quiet" href="{{ route('admin.deployments.show', $lastDeployment) }}">View last log</a>@endif
                        @if ($canSetup)
                            <form method="POST" action="{{ route('admin.services.delivered', $service) }}" class="admin-inline-form" onsubmit="return confirm('Installed it yourself? The customer will be emailed and will see the IP and root login.')">@csrf
                                <button class="admin-btn admin-btn-quiet" type="submit">I installed it myself — mark delivered</button></form>
                        @endif
                    </div>
                    @unless (config('provisioning.live_enabled'))
                        <p class="admin-setup-note">Automatic install is not switched on yet. Deploy opens the install terminal and runs a <strong>test run</strong> (nothing is installed). Install over SSH yourself, then press "mark delivered".</p>
                    @endunless
                </div>
            @endif

            @if ($delivered)
                <div class="dj-step-card">
                    <h3><span class="admin-step-num">3</span> Delivered</h3>
                    <p style="margin:0">The customer can see the IP, root login{{ $service->url ? ' and app link' : '' }} in their dashboard. @if ($lastDeployment)<a class="admin-strong-link" href="{{ route('admin.deployments.show', $lastDeployment) }}">View install log</a>@endif</p>
                    @if ($u->hasPermission('servers.edit') && $u->hasPermission('deployments.deploy') && $service->server_id)
                        <details class="admin-advanced">
                            <summary>Suspend this server</summary>
                            <form method="POST" action="{{ route('admin.servers.suspend', $service->server_id) }}" class="admin-inline-form" onsubmit="return confirm('Log a suspension request for this server?')">@csrf
                                <input name="reason" placeholder="Suspension reason" required maxlength="500" aria-label="Suspension reason"><button class="admin-btn admin-btn-danger" type="submit">Request suspension</button></form>
                        </details>
                    @endif
                </div>
            @endif
        </section>
    @endforeach

    {{-- 3. Billing, history and actions. --}}
    <div class="admin-grid-2">
        <section class="admin-card">
            <h2>Billing</h2>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Item</th><th>Billing</th><th>Total</th></tr></thead><tbody>
                @foreach ($order->items as $i)<tr><td>{{ $i->name }}</td><td>{{ \App\Support\BillingCycle::label($i->billing_interval) }}</td><td>{{ \App\Support\Money::inr($i->total_minor) }}</td></tr>@endforeach
            </tbody></table></div>
            <dl class="kv"><div><dt>Subtotal</dt><dd>{{ \App\Support\Money::inr($order->subtotal_minor) }}</dd></div><div><dt>Discount</dt><dd>{{ \App\Support\Money::inr($order->discount_minor) }}</dd></div><div><dt>Tax</dt><dd>{{ \App\Support\Money::inr($order->tax_minor) }}</dd></div><div><dt>Wallet applied</dt><dd>{{ \App\Support\Money::inr($order->wallet_applied_minor) }}</dd></div><div><dt><strong>Total</strong></dt><dd><strong>{{ \App\Support\Money::inr($order->total_minor) }}</strong></dd></div></dl>
            @if ($order->customer_snapshot)
                <dl class="kv">
                    @foreach (['billing_name' => 'Billing name', 'phone' => 'Phone', 'gstin' => 'GSTIN', 'billing_address' => 'Address'] as $k => $l)
                        @if (! empty($order->customer_snapshot[$k]))<div><dt>{{ $l }}</dt><dd>{{ $order->customer_snapshot[$k] }}</dd></div>@endif
                    @endforeach
                </dl>
            @endif
            <h2>Payment attempts</h2>
            @forelse ($order->attempts as $a)
                <div class="admin-list-row"><span>{{ $a->gateway }} ({{ $a->mode }}) <small>{{ $a->gateway_payment_id ?? $a->gateway_order_id }}</small></span><span>{{ \App\Support\Money::inr($a->amount_minor) }} <x-status-badge :status="$a->status" /></span></div>
            @empty<p class="muted">No payment attempts.</p>@endforelse
            @foreach ($order->invoices as $inv)<p><a href="{{ route('admin.invoices.download', $inv) }}">Download {{ $inv->number }} (PDF)</a></p>@endforeach

            @php
                $canCancel = $u->hasPermission('orders.edit') && ! $order->paid_at && ! in_array($order->status, ['cancelled', 'refunded']);
                $canRefund = $u->hasPermission('orders.refund') && in_array($order->status, ['awaiting_provisioning', 'active', 'failed']) && $order->paid_at;
            @endphp
            @if ($canCancel || $canRefund || $order->status === 'refund_pending')
                <h2>Actions</h2>
                @if ($canCancel)
                    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" class="admin-inline-form" onsubmit="return confirm('Cancel this order?')">@csrf
                        <input name="reason" placeholder="Reason" required maxlength="500"><button class="admin-btn admin-btn-danger" type="submit">Cancel order</button></form>
                @endif
                @if ($canRefund)
                    <form method="POST" action="{{ route('admin.orders.refund-request', $order) }}" class="admin-inline-form">@csrf
                        <input name="reason" placeholder="Refund reason" required maxlength="500"><button class="admin-btn admin-btn-quiet" type="submit">Request refund</button></form>
                @endif
                @if ($order->status === 'refund_pending')
                    @if ($u->hasPermission('orders.refund') && $u->hasPermission('orders.approve'))
                        <form method="POST" action="{{ route('admin.orders.refund-complete', $order) }}" class="admin-inline-form" onsubmit="return confirm('Record this refund as completed?')">@csrf
                            <select name="destination"><option value="wallet">Credit customer wallet</option><option value="gateway">Refunded at gateway (manual)</option></select><button class="admin-btn" type="submit">Complete refund</button></form>
                    @endif
                    @if ($u->hasPermission('orders.approve'))
                        <form method="POST" action="{{ route('admin.orders.refund-reverse', $order) }}" class="admin-inline-form">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Withdraw refund request</button></form>
                    @endif
                @endif
            @endif
        </section>
        <section class="admin-card"><h2>Timeline</h2>
            <ol class="timeline">@foreach ($order->events as $e)
                <li><strong>{{ $e->to_status ? \App\Support\OrderStatus::label($e->to_status) : ucfirst($e->type) }} @if ($e->is_internal)<span class="badge badge-neutral">internal</span>@endif</strong><span>{{ $e->message }} @if ($e->user)<small>— {{ $e->user->name }}</small>@endif</span><time>{{ \Illuminate\Support\Carbon::parse($e->created_at)->format('d M Y H:i') }}</time></li>
            @endforeach</ol>
            @if ($u->hasPermission('orders.edit'))
                <form method="POST" action="{{ route('admin.orders.note', $order) }}" class="admin-form">@csrf
                    <label>Add note<textarea name="message" rows="2" required maxlength="2000"></textarea></label>
                    <label class="admin-check"><input type="checkbox" name="internal" value="1" checked> Internal (hidden from customer)</label>
                    <button class="admin-btn admin-btn-quiet" type="submit">Add note</button>
                </form>
            @endif
        </section>
    </div>
@endsection
