@extends('layouts.dashboard')

@section('title', $service->label)
@section('heading', $service->label)
@section('subheading', 'Service overview')

@section('dash')
    @php
        $p = $service->plan; $rp = $p?->resourceProfile;
        $srv = $service->server;
        $active = $service->status === 'active';
        $unmanaged = ($p?->access_model ?? 'unmanaged') === 'unmanaged';
        $sub = $service->subscription;
        $openInvoice = $sub?->invoices->first(fn ($i) => $i->isUnpaid());
        $doneStages = collect($progress['stages'])->where('state', 'done')->count();
    @endphp


    <div class="panel">
        <div class="panel-head"><h2>Overview</h2><x-status-badge :status="$service->status" /></div>
        @if ($active && $service->url)
            <div class="btn-row" style="margin-bottom:12px"><a class="button-primary" href="{{ $service->url }}" rel="noopener">Open application ↗</a></div>
        @endif
        <dl class="kv">
            <div><dt>Product</dt><dd>{{ $registry->recipe($service->kind)['label'] ?? $service->kind }}</dd></div>
            <div><dt>Plan</dt><dd>{{ $p?->name ?? '—' }}</dd></div>
            <div><dt>Billing cycle</dt><dd>{{ $sub?->cycleLabel() ?? '—' }}</dd></div>
            <div><dt>Order</dt><dd>@if ($service->order)<a href="{{ route('dashboard.orders.show', $service->order) }}">{{ $service->order->number }}</a>@else — @endif</dd></div>
            <div><dt>Resources</dt><dd>{{ $rp ? $rp->summary() : 'To be confirmed' }}@if ($rp && $rp->status !== 'verified') <span class="badge badge-warn">unverified</span>@endif</dd></div>
            @if ($service->needsDomain())<div><dt>App address</dt><dd>{{ $service->appDomain() ?? ($service->custom_domain ? $service->custom_domain.' (waiting for DNS verification)' : 'Being arranged by our team') }}</dd></div>@endif
            <div><dt>Service URL</dt><dd>{{ $active && $service->url ? $service->url : 'Available once your service is active' }}</dd></div>
            <div><dt>Renewal date</dt><dd>{{ $service->renews_at?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt>Usage metrics</dt><dd>Not available yet</dd></div>
            <div><dt>Backups</dt><dd>Backup details are shared once your service is configured</dd></div>
        </dl>
        <div class="btn-row" style="margin-top:14px">
            <a class="button-secondary" href="#invoices">View invoices</a>
            @if ($openInvoice && $openInvoice->order_id)<a class="button-primary" href="{{ route('dashboard.orders.show', $openInvoice->order_id) }}">Renew service</a>@endif
            <a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Request upgrade</a>
            <a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Open support ticket</a>
        </div>
    </div>

    @if ($openInvoice && $openInvoice->order_id)
        <div class="flash-warning" role="status">
            Renewal invoice <strong>{{ $openInvoice->number }}</strong> for {{ \App\Support\Money::inr($openInvoice->total_minor) }} is due on {{ $openInvoice->due_at?->format('d M Y') }}.
            <a href="{{ route('dashboard.orders.show', $openInvoice->order_id) }}">Renew now</a> — the service keeps running until the due date.
        </div>
    @endif

    <div class="panel" id="setup">
        <div class="panel-head"><h2>Setup progress</h2>
            @if ($progress['poll'])<span class="badge badge-neutral" data-setup-live>Live</span>@else<x-status-badge :status="$active ? 'active' : 'pending'" />@endif
        </div>
        <p class="muted" data-setup-summary>{{ $progress['summary'] }}</p>
        <div class="setup-progress-bar" role="progressbar" aria-valuenow="{{ $doneStages }}" aria-valuemin="0" aria-valuemax="{{ count($progress['stages']) }}" aria-label="Setup progress"><span data-setup-bar style="width: {{ round($doneStages / max(1, count($progress['stages'])) * 100) }}%"></span></div>
        <ol class="setup-steps" data-setup-stages @if ($progress['poll']) data-poll-url="{{ route('dashboard.services.progress', $service) }}" @endif>
            @foreach ($progress['stages'] as $stage)
                <li data-stage-key="{{ $stage['key'] }}" data-stage-state="{{ $stage['state'] }}"><span class="setup-name">{{ $stage['label'] }}</span>@if ($stage['detail'] || $stage['state'] === 'failed')<span class="setup-detail">{{ $stage['detail'] }}</span>@endif</li>
            @endforeach
        </ol>
        @if ($progress['error'])
            <p style="margin-top:12px"><a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Contact support about this</a></p>
        @endif
    </div>

    <div class="panel"><h2>Server</h2>
        <dl class="kv">
            <div><dt>Service type</dt><dd>{{ $registry->recipe($service->kind)['label'] ?? $service->kind }} · {{ $unmanaged ? 'Self-hosted VPS · full root access' : 'Restricted access (legacy plan)' }}</dd></div>
            <div><dt>Server status</dt><dd>@if ($active)<span class="badge badge-good">Active — setup complete</span>@elseif ($srv)<span class="badge badge-warn">Server assigned — setup pending</span>@else<span class="badge badge-warn">Awaiting VPS assignment</span>@endif</dd></div>
            <div><dt>IP address</dt><dd>@if ($active && $srv)<a href="#access">{{ $srv->ip_address }} — see Server login</a>@else Shown once your service is active @endif</dd></div>
            <div><dt>Region</dt><dd>{{ $srv?->location ?? 'Assigned with your server' }}</dd></div>
            <div><dt>CPU / RAM / storage</dt><dd>{{ $srv ? "{$srv->cpu_capacity} vCPU · {$srv->ram_capacity_gb} GB RAM · {$srv->storage_capacity_gb} GB" : ($rp ? $rp->summary() : 'To be confirmed') }}</dd></div>
            <div><dt>Operating system</dt><dd>{{ $srv?->os_name ?? 'Set during setup' }}</dd></div>
            <div><dt>Access</dt><dd>@if ($unmanaged) You receive full SSH/root access to your own server. After handover you are responsible for the server and your data. @else Root access is restricted on this legacy plan. @endif</dd></div>
            <div><dt>Installed applications</dt><dd>{{ $active ? ($registry->recipe($service->kind)['label'] ?? '—') : 'None yet' }}</dd></div>
        </dl>
        @if ($service->kind === 'claude_code')<p class="muted small">Claude Code needs your own eligible Claude subscription or API credentials. DeveloperJet does not provide or resell Claude access.</p>@endif
        @if ($unmanaged)<p class="muted small">We set up the server and install your software, then hand it over. Operating system updates, firewall rules, your data and anything you install are yours to look after. <strong>Backups are not included</strong> — contact our sales team if you want a backup plan.</p>@endif
    </div>

    <div class="panel"><h2>Controls</h2>
        <p class="muted small">Only controls that are verified for your server are enabled. Others are listed so you know what to expect.</p>
        <ul class="plain-list">
            @foreach ($controls as $c)
                <li><strong>{{ $c['label'] }}</strong> — @if ($c['available'])<span class="badge badge-good">Available</span>@else<span class="badge badge-neutral">Unavailable</span> <span class="muted small">{{ $c['reason'] }}</span>@endif</li>
            @endforeach
        </ul>
    </div>
    <div class="panel"><h2>Health checks</h2>
        @forelse ($health as $h)
            <p style="margin:6px 0"><x-status-badge :status="$h->status" /> {{ \Illuminate\Support\Str::headline($h->check) }}
                <span class="muted small">@if ($h->detail){{ $h->detail }} · @endif{{ $h->created_at?->format('d M Y H:i') }}</span></p>
        @empty<p class="muted">No health checks recorded yet. Checks appear here once our team or the monitoring worker records them — nothing is estimated.</p>@endforelse
        @if ($latest && $latest->mode === 'dry_run')<p class="muted small">Latest internal readiness check: dry run — a simulation only, nothing was installed on any server.</p>@endif
    </div>

    <div class="panel" id="invoices"><h2>Invoices for this service</h2>
        @if ($invoices->isEmpty())
            <p class="muted">No invoices yet.</p>
        @else
            <div class="data-table-wrap"><table class="data-table">
                <thead><tr><th scope="col">Invoice</th><th scope="col">Issued</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col"><span class="sr-only">PDF</span></th></tr></thead>
                <tbody>@foreach ($invoices as $invoice)
                    <tr><td>{{ $invoice->number }}</td><td>{{ $invoice->issued_at?->format('d M Y') ?? '—' }}</td><td>{{ \App\Support\Money::inr($invoice->total_minor) }}</td><td><x-status-badge :status="$invoice->status" /></td><td><a href="{{ route('dashboard.invoices.download', $invoice) }}">PDF</a></td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
        <p class="muted small">Renewal billing, paid-through dates and cancellation live under <a href="{{ route('dashboard.subscriptions') }}">Subscriptions &amp; renewals</a>.</p>
    </div>

    @if ($active && $srv && $unmanaged)
        @php
            $secret = (string) $srv->ssh_credential;
            $isKey = str_starts_with(ltrim($secret), '-----BEGIN');
            $sshCmd = 'ssh '.$srv->ssh_user.'@'.$srv->ip_address.((int) $srv->ssh_port !== 22 ? ' -p '.$srv->ssh_port : '');
        @endphp
        <div class="panel server-login" id="access"><h2>Server login</h2>
            <dl class="kv">
                <div><dt>IP address</dt><dd><code>{{ $srv->ip_address }}</code> <button type="button" class="link-btn" data-copy-text="{{ $srv->ip_address }}">Copy</button></dd></div>
                <div><dt>Username</dt><dd><code>{{ $srv->ssh_user }}</code></dd></div>
                <div><dt>Password</dt><dd>
                    @if ($secret !== '' && ! $isKey)
                        <span class="secret-field"><input type="password" value="{{ $secret }}" readonly aria-label="Root password" data-secret>
                        <button type="button" class="link-btn" data-reveal>Show</button>
                        <button type="button" class="link-btn" data-copy-from-secret>Copy</button></span>
                    @else
                        <span class="muted">Your server uses an SSH key. <a href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Ask support</a> for access.</span>
                    @endif
                </dd></div>
                <div><dt>SSH port</dt><dd>{{ $srv->ssh_port }}</dd></div>
            </dl>
            <h3>How to connect</h3>
            <p><strong>Mac, Linux or Windows Terminal / PowerShell:</strong> run this, then paste the password when asked.</p>
            <pre class="cmd"><code>{{ $sshCmd }}</code><button type="button" class="link-btn" data-copy-text="{{ $sshCmd }}">Copy</button></pre>
            <p><strong>PuTTY (Windows):</strong> Host Name <code>{{ $srv->ip_address }}</code>, Port <code>{{ $srv->ssh_port }}</code>, Connection type SSH → Open → log in as <code>{{ $srv->ssh_user }}</code> → right-click to paste the password.</p>
            <p class="muted small">For your security, change the password after your first login with <code>passwd</code>. You have full root access: your data and backups are your responsibility.</p>
        </div>
    @endif

    @php $keyHelp = config('provisioning.key_help.'.$service->kind); @endphp
    @if ($keyHelp)
        <div class="panel" id="ai"><h2>Add your AI API key</h2>
            @if ($active)
                <p>{{ $keyHelp['intro'] }}</p>
                @foreach ($keyHelp['commands'] as $cmd)
                    <pre class="cmd"><code>{{ $cmd }}</code><button type="button" class="link-btn" data-copy-text="{{ $cmd }}">Copy</button></pre>
                @endforeach
                <p class="muted small">Replace <code>your-key-here</code> with your own key. Your key stays on your server — DeveloperJet never sees it.</p>
            @else
                <p class="muted">Once your server is delivered, this box shows the exact command to add your own AI provider key (OpenRouter, OpenAI, Anthropic…) on your server.</p>
            @endif
        </div>
    @endif

    @if (in_array($service->kind, ['hermes_workspace', 'n8n', 'bundle', 'openclaw']))
        <div class="panel"><h2>Domain</h2>
            <p class="muted">By default your service uses a DeveloperJet address. HTTPS is configured during setup. To use your own domain, add it and verify ownership.</p>
            @if ($service->config['assigned_domain'] ?? null)<p>DeveloperJet address: <strong>{{ $service->config['assigned_domain'] }}</strong></p>@endif
            <form method="POST" action="{{ route('dashboard.services.domain', $service) }}" class="form-grid">@csrf
                <label>Custom domain<input name="custom_domain" value="{{ old('custom_domain', $service->custom_domain) }}" placeholder="app.example.com" maxlength="190"></label>
                <div class="btn-row"><button class="button-primary" type="submit">Save domain</button></div>
            </form>
            @if ($service->custom_domain && ($service->config['domain_token'] ?? null))
                <p>Status: <x-status-badge :status="$service->domain_verified ? 'verified' : 'pending'" /></p>
                @unless ($service->domain_verified)
                    <p class="muted small">Create a DNS TXT record: <code>_developerjet-verify.{{ $service->custom_domain }}</code> = <code>{{ $service->config['domain_token'] }}</code></p>
                    <form method="POST" action="{{ route('dashboard.services.verify-domain', $service) }}">@csrf<button class="button-primary" type="submit">Verify domain</button></form>
                @endunless
            @endif
        </div>
    @endif

    <div class="panel"><h2>Help</h2>
        <div class="btn-row"><a class="button-primary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Contact support</a><a class="auth-secondary-action" href="{{ route('docs.index') }}">Documentation</a></div>
        <p class="muted small">Your root password is shown only in the Server login box on this page, never by email.</p>
    </div>
@endsection
