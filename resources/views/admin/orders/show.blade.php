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
            $delivered = in_array($service->status, ['active', 'suspended'], true);
            $serverActions = $delivered ? app(\App\Services\Provisioning\ServerActions::class)->available($service) : [];
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
                    @php
                        $inFlight = $lastDeployment && in_array($lastDeployment->status, ['queued', 'preflight', 'running'], true);
                        $registry = app(\App\Services\Provisioning\RecipeRegistry::class);
                        $liveOn = config('provisioning.live_enabled') && config('provisioning.worker') === 'ssh';
                        $missingStep = collect($registry->steps($service->kind))->first(fn ($s) => ! $registry->approved($s, $service->kind));
                        $autoReady = $liveOn && $missingStep === null;
                    @endphp
                    @if ($lastDeployment)
                        <p class="dj-run-status dj-run-{{ $inFlight ? 'running' : ($lastDeployment->status === 'failed' ? 'failed' : 'ok') }}">
                            <span class="dj-dot" aria-hidden="true"></span>
                            @if ($inFlight)<strong>Install in progress</strong> — started {{ $lastDeployment->created_at->diffForHumans() }}. Deploy is locked until it finishes.
                            @else<strong>Last run:</strong> {{ $lastDeployment->mode === 'dry_run' ? 'Test run' : 'Live install' }} · {{ \Illuminate\Support\Str::headline($lastDeployment->status) }} · {{ $lastDeployment->created_at->format('d M, H:i') }}@endif
                            <a class="admin-strong-link" href="{{ route('admin.deployments.show', $lastDeployment) }}">{{ $inFlight ? 'Watch the terminal' : 'View log' }}</a>
                        </p>
                    @endif
                    <div class="dj-deploy-row">
                        @if ($inFlight)
                            <button class="admin-btn admin-btn-lg" type="button" disabled aria-disabled="true">⏳ Installing…</button>
                        @else
                            <form method="POST" action="{{ route('admin.services.deploy', $service) }}" class="admin-inline-form" data-submit-once="Starting…" onsubmit="return confirm('Start the install on {{ $srv->ip_address }}?')">@csrf
                                <input type="hidden" name="mode" value="live"><button class="admin-btn admin-btn-lg" type="submit">▶ {{ $lastDeployment ? 'Deploy again' : 'Deploy' }}</button></form>
                        @endif
                        {{-- Manual hand-over only where automatic install cannot do the job (switched off, or no approved installer for this product yet). --}}
                        @if ($canSetup && ! $inFlight && ! $autoReady)
                            <form method="POST" action="{{ route('admin.services.delivered', $service) }}" class="admin-inline-form" data-submit-once="Saving…" onsubmit="return confirm('Installed it yourself? The customer will be emailed and will see the IP and root login.')">@csrf
                                <button class="admin-btn admin-btn-quiet" type="submit">I installed it myself — mark delivered</button></form>
                        @endif
                    </div>
                    @if (! $liveOn)
                        <p class="admin-setup-note">Automatic install is not switched on yet. Deploy opens the install terminal and runs a <strong>test run</strong> (nothing is installed). Install over SSH yourself, then press "mark delivered".</p>
                    @elseif (! $autoReady)
                        <p class="admin-setup-note">Automatic install for <strong>{{ $registry->recipe($service->kind)['label'] ?? $service->kind }}</strong> is not ready yet (step "{{ $registry->label($missingStep) }}" has no approved installer), so Deploy will stop there. Until it is added, install it over SSH and press "mark delivered".</p>
                    @endif
                </div>
            @endif

            @if ($canSetup && ($srv || ($service->config['assigned_domain'] ?? null)))
                <details class="admin-advanced dj-reset">
                    <summary>Start over</summary>
                    <p class="muted small">Puts this order back to step 1: the server returns to your Servers list (unassigned) and the app address is cleared. The VPS itself is <strong>not</strong> wiped — to wipe it, reinstall the OS from your VPS supplier's panel. Logs are kept.</p>
                    <form method="POST" action="{{ route('admin.services.reset', $service) }}" class="admin-inline-form" data-submit-once="Resetting…" onsubmit="return confirm('Reset this setup and start again from step 1?')">@csrf
                        <button class="admin-btn admin-btn-danger" type="submit">Reset setup</button></form>
                </details>
            @endif

            @if ($delivered)
                <div class="dj-step-card">
                    <h3><span class="admin-step-num">3</span> {{ $service->status === 'suspended' ? 'Suspended' : 'Delivered' }}</h3>
                    @if ($service->status === 'suspended')
                        <p class="admin-setup-note" style="margin:0 0 10px">The app is <strong>stopped</strong> and the customer sees this service as suspended. Their data is kept — Unsuspend starts it again.</p>
                    @else
                        <p style="margin:0">The customer can see the IP, root login{{ $service->url ? ' and app link' : '' }} in their dashboard. @if ($lastDeployment)<a class="admin-strong-link" href="{{ route('admin.deployments.show', $lastDeployment) }}">View install log</a>@endif</p>
                    @endif

                    @if ($serverActions && $u->hasPermission('deployments.deploy'))
                        @php
                            $confirms = [
                                'reboot' => 'Reboot the whole server now? Everything on it restarts (1–2 minutes).',
                                'restart_app' => 'Restart the app now? It is unavailable for a few seconds.',
                                'reset_login' => 'Reset the app login? The customer must create the owner account again (workflows are kept).',
                                'stop_app' => 'Suspend this service? The app stops for the customer until you unsuspend it.',
                                'start_app' => 'Unsuspend and start the app again?',
                            ];
                            $canSuspend = $u->hasPermission('servers.edit');
                        @endphp
                        <div class="dj-deploy-row" style="margin-top:12px">
                            @foreach (['reboot', 'restart_app', 'reset_login'] as $a)
                                @isset($serverActions[$a])
                                    <form method="POST" action="{{ route('admin.services.action', [$service, $a]) }}" class="admin-inline-form" data-submit-once="Working…" onsubmit="return confirm(@js($confirms[$a]))">@csrf
                                        <button class="admin-btn admin-btn-quiet" type="submit">{{ $serverActions[$a] }}</button></form>
                                @endisset
                            @endforeach
                            @if ($canSuspend && $service->status === 'active' && isset($serverActions['stop_app']))
                                <form method="POST" action="{{ route('admin.services.action', [$service, 'stop_app']) }}" class="admin-inline-form" data-submit-once="Suspending…" onsubmit="return confirm(@js($confirms['stop_app']))">@csrf
                                    <button class="admin-btn admin-btn-danger" type="submit">Suspend</button></form>
                            @elseif ($canSuspend && $service->status === 'suspended' && isset($serverActions['start_app']))
                                <form method="POST" action="{{ route('admin.services.action', [$service, 'start_app']) }}" class="admin-inline-form" data-submit-once="Starting…" onsubmit="return confirm(@js($confirms['start_app']))">@csrf
                                    <button class="admin-btn" type="submit">Unsuspend</button></form>
                            @endif
                        </div>
                        @if ($canSuspend && isset($serverActions['wipe_app']))
                            <details class="admin-advanced dj-reset">
                                <summary>Reinstall fresh (delete everything)</summary>
                                <p class="muted small">Deletes {{ config('provisioning.recipes.'.$service->kind.'.label', 'the app') }} and <strong>all its data</strong> on this server, then installs it again like new. The customer's other files and the operating system are not touched — for a full OS reinstall use your VPS supplier's panel.</p>
                                <form method="POST" action="{{ route('admin.services.reinstall', $service) }}" class="admin-inline-form" data-submit-once="Deleting…" onsubmit="return confirm('Delete the app and ALL its data, then reinstall fresh? This cannot be undone.')">@csrf
                                    <button class="admin-btn admin-btn-danger" type="submit">Delete app &amp; reinstall fresh</button></form>
                            </details>
                        @endif
                    @elseif (! $serverActions)
                        <p class="muted small" style="margin-top:8px">Reboot, suspend and reinstall buttons appear here when automatic install (SSH) is switched on.</p>
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
