@extends('layouts.admin')

@section('title', 'Order '.$order->number)
@section('heading', 'Order '.$order->number)

@section('actions')<x-status-badge :status="$order->status" />@endsection

@section('content')
    @php $u = auth()->user(); @endphp
    <div class="admin-grid-2">
        <section class="admin-card">
            <h2>Customer</h2>
            <p>{{ $order->user->name }}<br><small>{{ $order->user->email }}</small></p>
            @if ($order->customer_snapshot)
                <dl class="kv">
                    @foreach (['billing_name' => 'Billing name', 'phone' => 'Phone', 'gstin' => 'GSTIN', 'billing_address' => 'Address'] as $k => $l)
                        @if (! empty($order->customer_snapshot[$k]))<div><dt>{{ $l }}</dt><dd>{{ $order->customer_snapshot[$k] }}</dd></div>@endif
                    @endforeach
                </dl>
            @endif
            <dl class="kv"><div><dt>Payment method</dt><dd>{{ $order->payment_method ?? '—' }}</dd></div><div><dt>Paid at</dt><dd>{{ $order->paid_at?->format('d M Y H:i') ?? 'Not paid' }}</dd></div><div><dt>Assigned to</dt><dd>{{ $order->assignee?->name ?? 'Unassigned' }}</dd></div></dl>
            @if ($u->hasPermission('orders.assign'))
                <form method="POST" action="{{ route('admin.orders.assign', $order) }}" class="admin-inline-form">@csrf
                    <select name="assigned_to" aria-label="Assign to"><option value="">Unassigned</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($order->assigned_to === $s->id)>{{ $s->name }}</option>@endforeach</select>
                    <button class="admin-btn admin-btn-quiet" type="submit">Assign</button>
                </form>
            @endif
        </section>
        <section class="admin-card">
            <h2>Items & totals</h2>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Item</th><th>Billing</th><th>Total</th></tr></thead><tbody>
                @foreach ($order->items as $i)<tr><td>{{ $i->name }}</td><td>{{ $i->billing_interval }}</td><td>{{ \App\Support\Money::inr($i->total_minor) }}</td></tr>@endforeach
            </tbody></table></div>
            <dl class="kv"><div><dt>Subtotal</dt><dd>{{ \App\Support\Money::inr($order->subtotal_minor) }}</dd></div><div><dt>Discount</dt><dd>{{ \App\Support\Money::inr($order->discount_minor) }}</dd></div><div><dt>Tax</dt><dd>{{ \App\Support\Money::inr($order->tax_minor) }}</dd></div><div><dt>Wallet applied</dt><dd>{{ \App\Support\Money::inr($order->wallet_applied_minor) }}</dd></div><div><dt><strong>Total</strong></dt><dd><strong>{{ \App\Support\Money::inr($order->total_minor) }}</strong></dd></div></dl>
        </section>
    </div>

    @if ($order->services->isNotEmpty())
        <section class="admin-card">
            <h2>Services & provisioning</h2>
            @foreach ($order->services as $service)
                @php
                    $canSetup = $u->hasPermission('servers.assign') && in_array($service->status, ['pending_provisioning', 'failed']);
                    $askAddress = $service->needsDomain() && ! ($service->domain_verified && $service->custom_domain);
                    $profile = $service->plan?->resourceProfile;
                    $srv = $service->server;
                @endphp
                <div class="admin-service-row admin-setup">
                    <div class="admin-setup-head">
                        <div><strong>{{ $service->label }}</strong> <x-status-badge :status="$service->status" />
                            <p class="muted small">@if ($profile)Plan size: {{ $profile->summary() }} · {{ $profile->disk_gb }} GB disk @else Plan size not in catalogue — enter it below @endif</p>
                        </div>
                    </div>

                    <dl class="admin-setup-facts">
                        <div><dt>Server</dt><dd>@if ($srv)<strong>{{ $srv->ip_address }}</strong> <small class="muted">({{ $srv->ssh_user.'@'.$srv->ip_address.':'.$srv->ssh_port }} ·{{ $srv->getRawOriginal('ssh_credential') ? 'password/key saved' : 'no password saved' }})</small>@else<span class="badge badge-warn">Not added yet</span>@endif</dd></div>
                        @if ($service->needsDomain())
                            <div><dt>App address</dt><dd>
                                @if ($service->appDomain())<strong>{{ $service->appDomain() }}</strong>
                                    @if ($service->custom_domain && $service->domain_verified)<span class="badge badge-good">Customer domain · verified</span>@endif
                                @elseif ($service->custom_domain)Customer domain <strong>{{ $service->custom_domain }}</strong> <span class="badge badge-warn">waiting for customer DNS check</span>
                                @else<span class="badge badge-warn">Not set yet</span>@endif
                            </dd></div>
                        @endif
                        @if ($service->usesAi())
                            <div><dt>AI key</dt><dd>
                                @if ($service->aiConfigured())<span class="badge badge-good">Provided · {{ config('provisioning.ai_providers.'.$service->ai_provider, $service->ai_provider) }}</span>
                                @else<span class="badge badge-neutral">Not provided</span> <small class="muted">customer can add it in their dashboard</small>@endif
                            </dd></div>
                        @endif
                    </dl>

                    @if ($canSetup)
                        <form method="POST" action="{{ route('admin.services.setup', $service) }}" class="admin-form admin-setup-form" data-loading-form>@csrf
                            <h3><span class="admin-step-num">1</span> VPS details</h3>
                            <div class="admin-form-grid">
                                <label>VPS IP address <input name="ip_address" value="{{ old('ip_address', $srv?->ip_address) }}" placeholder="203.0.113.10" required inputmode="decimal"></label>
                                <label>Username <input name="ssh_user" value="{{ old('ssh_user', $srv?->ssh_user ?? 'root') }}" placeholder="root"></label>
                                <label>Root password
                                    <input type="password" name="ssh_password" autocomplete="new-password" @unless ($srv?->getRawOriginal('ssh_credential')) required @endunless
                                           placeholder="{{ $srv?->getRawOriginal('ssh_credential') ? '•••• saved — leave blank to keep' : 'From the VPS supplier email' }}">
                                    <small class="muted">Stored encrypted, never shown again.</small>
                                </label>
                                <label>SSH port <input type="number" name="ssh_port" value="{{ old('ssh_port', $srv?->ssh_port ?? 22) }}" min="1" max="65535"></label>
                                @if ($askAddress)
                                    <label class="span-2">App address (subdomain)
                                        <input name="assigned_domain" value="{{ old('assigned_domain', $service->config['assigned_domain'] ?? $service->custom_domain) }}" placeholder="client1.developerjet.com" maxlength="190" required>
                                        <small class="muted">Add a DNS A record for this name pointing to the VPS IP.</small>
                                    </label>
                                @endif
                                @unless ($profile)
                                    <label>vCPU <input type="number" name="vcpu" min="1" max="256" required value="{{ old('vcpu', $service->config['confirmed_resources']['vcpu'] ?? '') }}"></label>
                                    <label>RAM (GB) <input type="number" name="ram_gb" min="1" max="4096" required value="{{ old('ram_gb', $service->config['confirmed_resources']['ram_gb'] ?? '') }}"></label>
                                    <label>Disk (GB) <input type="number" name="disk_gb" min="1" max="65536" required value="{{ old('disk_gb', $service->config['confirmed_resources']['disk_gb'] ?? '') }}"></label>
                                @endunless
                            </div>
                            <div class="admin-form-actions"><button class="admin-btn" type="submit">{{ $srv ? 'Update details' : 'Save details' }}</button></div>
                        </form>

                        @if ($servers->isNotEmpty())
                            <details class="admin-advanced">
                                <summary>Or use a server already in inventory</summary>
                                <form method="POST" action="{{ route('admin.services.assign', $service) }}" class="admin-inline-form">@csrf
                                    <select name="server_id" aria-label="Server"><option value="">Select server…</option>@foreach ($servers as $s)<option value="{{ $s->id }}">{{ $s->label.($s->label !== $s->ip_address ? ' — '.$s->ip_address : '').' ('.($s->sizeUnknown() ? 'size from plan' : $s->cpu_capacity.'c/'.$s->ram_capacity_gb.'G/'.$s->storage_capacity_gb.'G').')' }}</option>@endforeach</select>
                                    @unless ($profile)
                                        <input type="number" name="vcpu" min="1" max="256" placeholder="vCPU" required aria-label="Required vCPU">
                                        <input type="number" name="ram_gb" min="1" max="4096" placeholder="RAM GB" required aria-label="Required RAM in GB">
                                        <input type="number" name="disk_gb" min="1" max="65536" placeholder="Disk GB" required aria-label="Required disk in GB">
                                    @endunless
                                    <button class="admin-btn admin-btn-quiet" type="submit">Assign</button>
                                </form>
                            </details>
                        @endif
                    @endif

                    @if ($u->hasPermission('deployments.deploy') && $service->server_id)
                        <div class="admin-setup-deploy">
                            <h3><span class="admin-step-num">2</span> Deploy</h3>
                            <div class="btn-row">
                                <form method="POST" action="{{ route('admin.services.deploy', $service) }}" class="admin-inline-form" onsubmit="return confirm('Install {{ $service->plan?->name ?? 'the app' }} on {{ $srv->ip_address }}?')">@csrf
                                    <input type="hidden" name="mode" value="live"><button class="admin-btn" type="submit">Deploy</button></form>
                                <form method="POST" action="{{ route('admin.services.deploy', $service) }}" class="admin-inline-form">@csrf
                                    <input type="hidden" name="mode" value="dry_run"><button class="admin-btn admin-btn-quiet" type="submit">Check only (dry run)</button></form>
                            </div>
                            @unless (config('provisioning.live_enabled'))
                                <p class="admin-setup-note">Automatic install is not switched on yet, so Deploy will stop safely without installing anything. Until it is approved and tested on a real VPS, install the app over SSH yourself.</p>
                            @endunless
                        </div>
                    @endif

                    @if ($u->hasPermission('servers.edit') && $u->hasPermission('deployments.deploy') && $service->server_id && ! $canSetup)
                        <details class="admin-advanced">
                            <summary>Suspend this server</summary>
                            <form method="POST" action="{{ route('admin.servers.suspend', $service->server_id) }}" class="admin-inline-form" onsubmit="return confirm('Log a suspension request for this server?')">@csrf
                                <input name="reason" placeholder="Suspension reason" required maxlength="500" aria-label="Suspension reason"><button class="admin-btn admin-btn-danger" type="submit">Request suspension</button></form>
                        </details>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    <div class="admin-grid-2">
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
        <section class="admin-card"><h2>Payment attempts</h2>
            @forelse ($order->attempts as $a)
                <div class="admin-list-row"><span>{{ $a->gateway }} ({{ $a->mode }}) <small>{{ $a->gateway_payment_id ?? $a->gateway_order_id }}</small></span><span>{{ \App\Support\Money::inr($a->amount_minor) }} <x-status-badge :status="$a->status" /></span></div>
            @empty<p class="muted">No payment attempts.</p>@endforelse
            @foreach ($order->invoices as $inv)<p><a href="{{ route('admin.invoices.download', $inv) }}">Download {{ $inv->number }} (PDF)</a></p>@endforeach

            <h2>Actions</h2>
            @if ($u->hasPermission('orders.edit') && ! $order->paid_at && ! in_array($order->status, ['cancelled', 'refunded']))
                <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" class="admin-inline-form" onsubmit="return confirm('Cancel this order?')">@csrf
                    <input name="reason" placeholder="Reason" required maxlength="500"><button class="admin-btn admin-btn-danger" type="submit">Cancel order</button></form>
            @endif
            @if ($u->hasPermission('orders.refund') && in_array($order->status, ['awaiting_provisioning', 'active', 'failed']) && $order->paid_at)
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
        </section>
    </div>
@endsection
