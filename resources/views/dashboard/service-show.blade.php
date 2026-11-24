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

    @if ($service->usesAi() && ! $service->aiConfigured() && ! ($service->config['ai_skipped'] ?? false))
        <div class="flash flash-warning" role="status"><strong>One step left:</strong> add your AI provider API key so we can finish setting up {{ $registry->recipe($service->kind)['label'] ?? 'your service' }}. <a href="#ai">Add API key</a></div>
    @endif

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
            <div><dt>IP address</dt><dd>{{ $active && $srv ? $srv->ip_address : 'Shown once your service is active' }}</dd></div>
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

    @if (in_array($service->kind, ['hermes_cli', 'hermes_workspace', 'bundle', 'openclaw', 'claude_code']))
        <div class="panel" id="ai"><h2>AI provider</h2>
            @php $linked = $savedKeys->firstWhere('id', (int) ($service->config['api_key_id'] ?? 0)); @endphp
            @if ($service->aiConfigured())
                <p><span class="badge badge-good">Configured</span> {{ $providers[$service->ai_provider] ?? $service->ai_provider }}@if ($linked) · <strong>{{ $linked->label }}</strong> <code>{{ $linked->masked() }}</code>@endif. The key is encrypted and never shown again.</p>
            @else
                <div class="flash flash-info" role="note"><strong>Configuration incomplete.</strong> Your service works once installed, but needs an AI provider key to answer requests. Pick a saved key or add one.</div>
            @endif

            @if ($savedKeys->isNotEmpty())
                <form method="POST" action="{{ route('dashboard.services.ai', $service) }}" class="ai-pick" autocomplete="off">@csrf
                    <label>Use a saved key
                        <select name="api_key_id" required>
                            @foreach ($savedKeys as $k)<option value="{{ $k->id }}" @selected($linked && $linked->id === $k->id)>{{ $k->label }} · {{ $k->providerLabel() }} · {{ $k->masked() }}</option>@endforeach
                        </select>
                    </label>
                    <button class="button-primary" type="submit">Use this key</button>
                </form>
                <p class="muted small">Or add a new key below. Manage all keys under <a href="{{ route('dashboard.keys') }}">AI API keys</a>.</p>
            @endif

            <details class="ai-new" @if ($savedKeys->isEmpty()) open @endif>
                <summary>{{ $savedKeys->isEmpty() ? 'Add your API key' : 'Add a new key' }}</summary>
                <form method="POST" action="{{ route('dashboard.services.ai', $service) }}" class="form-grid" autocomplete="off">@csrf
                    <label>Provider<select name="ai_provider">@foreach ($providers as $k => $l)<option value="{{ $k }}" @selected($service->ai_provider === $k)>{{ $l }}</option>@endforeach</select></label>
                    <label>API key<input type="password" name="ai_api_key" autocomplete="new-password" maxlength="300" required placeholder="Paste your key"></label>
                    <div class="btn-row span-2"><button class="button-primary" type="submit">Save and use</button></div>
                </form>
                <p class="muted small">The key is saved to your account (encrypted) so you can reuse it on other services.</p>
            </details>
            <form method="POST" action="{{ route('dashboard.services.ai', $service) }}">@csrf<input type="hidden" name="skip" value="1"><button class="link-danger" type="submit">Skip for now</button></form>
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
        <p class="muted small">For your security, root passwords are never emailed or displayed. First-time access is arranged securely by our team.</p>
    </div>
@endsection
