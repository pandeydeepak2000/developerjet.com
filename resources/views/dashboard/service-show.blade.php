@extends('layouts.dashboard')

@section('title', $service->label)
@section('heading', $service->label)
@section('subheading', $registry->recipe($service->kind)['label'] ?? 'Service')

@section('dash')
    @php
        $p = $service->plan; $rp = $p?->resourceProfile;
        $srv = $service->server;
        $active = $service->status === 'active';
        $unmanaged = ($p?->access_model ?? 'unmanaged') === 'unmanaged';
        $sub = $service->subscription;
        $openInvoice = $sub?->invoices->first(fn ($i) => $i->isUnpaid());
        $doneStages = collect($progress['stages'])->where('state', 'done')->count();
        $appName = $registry->recipe($service->kind)['label'] ?? 'your app';
        $size = $srv && ! $srv->sizeUnknown() ? "{$srv->cpu_capacity} vCPU · {$srv->ram_capacity_gb} GB RAM · {$srv->storage_capacity_gb} GB" : ($rp ? $rp->summary().($rp->disk_gb ? ' · '.$rp->disk_gb.' GB' : '') : '—');
        $availableControls = collect($controls)->where('available', true);
        $keyHelp = config('provisioning.key_help.'.$service->kind);
    @endphp

    {{-- Header: what it is, its state, and the one thing the customer most wants to do. --}}
    <section class="svc-hero">
        <div class="svc-hero-main">
            <span class="svc-hero-icon" aria-hidden="true">{{ strtoupper(mb_substr($appName, 0, 1)) }}</span>
            <div>
                <div class="svc-hero-title"><h2>{{ $service->label }}</h2><x-status-badge :status="$service->status" /></div>
                <p class="muted">{{ $appName }} · {{ $p?->name ?? '—' }} plan · {{ $sub?->cycleLabel() ?? 'Billing' }}@if ($service->renews_at) · renews {{ $service->renews_at->format('d M Y') }}@endif</p>
            </div>
        </div>
        <div class="svc-hero-actions">
            @if ($active && $service->url)<a class="button-primary" href="{{ $service->url }}" target="_blank" rel="noopener">Open {{ $appName }} ↗</a>@endif
            <a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Get help</a>
        </div>
    </section>

    @if ($openInvoice && $openInvoice->order_id)
        <div class="flash-warning" role="status">
            Renewal invoice <strong>{{ $openInvoice->number }}</strong> for {{ \App\Support\Money::inr($openInvoice->total_minor) }} is due on {{ $openInvoice->due_at?->format('d M Y') }}.
            <a href="{{ route('dashboard.orders.show', $openInvoice->order_id) }}">Renew now</a> — the service keeps running until the due date.
        </div>
    @endif

    <div class="svc-stats">
        <div class="svc-stat"><span>IP address</span>
            @if ($active && $srv)<strong><code>{{ $srv->ip_address }}</code> <button type="button" class="link-btn" data-copy-text="{{ $srv->ip_address }}">Copy</button></strong>
            @else<strong class="muted">{{ $srv ? 'Server assigned — setup pending' : 'Awaiting VPS assignment' }}</strong>@endif</div>
        <div class="svc-stat"><span>Resources</span><strong>{{ $size }}</strong></div>
        <div class="svc-stat"><span>Operating system</span><strong>{{ $srv?->os_name ?: ($active ? 'Ubuntu' : '—') }}</strong></div>
        @if ($service->needsDomain())
            <div class="svc-stat"><span>Address</span><strong>@if ($active && $service->url)<a href="{{ $service->url }}" target="_blank" rel="noopener">{{ $service->appDomain() ?? $service->url }}</a>@else{{ $service->appDomain() ?? ($service->custom_domain ? $service->custom_domain.' (waiting for DNS)' : 'Being arranged') }}@endif</strong></div>
        @else
            <div class="svc-stat"><span>Access</span><strong>{{ $unmanaged ? 'Full root (SSH)' : 'Restricted' }}</strong></div>
        @endif
    </div>

    @unless ($active)
        <div class="panel" id="setup">
            <div class="panel-head"><h2>Setup progress</h2>
                @if ($progress['poll'])<span class="badge badge-neutral" data-setup-live>Live</span>@else<x-status-badge :status="'pending'" />@endif
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
    @endunless

    <div class="svc-grid">
        <div class="svc-main">
            @if ($active && $srv && $unmanaged)
                @php
                    $secret = (string) $srv->ssh_credential;
                    $isKey = str_starts_with(ltrim($secret), '-----BEGIN');
                    $sshCmd = 'ssh '.$srv->ssh_user.'@'.$srv->ip_address.((int) $srv->ssh_port !== 22 ? ' -p '.$srv->ssh_port : '');
                @endphp
                <div class="panel server-login" id="access">
                    <div class="panel-head"><h2>Server login</h2><span class="badge badge-good">Full root access</span></div>
                    <div class="cred-rows">
                        <div class="cred-row"><span>Host / IP</span><code>{{ $srv->ip_address }}</code><button type="button" class="link-btn" data-copy-text="{{ $srv->ip_address }}">Copy</button></div>
                        <div class="cred-row"><span>Username</span><code>{{ $srv->ssh_user }}</code><button type="button" class="link-btn" data-copy-text="{{ $srv->ssh_user }}">Copy</button></div>
                        <div class="cred-row"><span>Password</span>
                            @if ($secret !== '' && ! $isKey)
                                <span class="secret-field"><input type="password" value="{{ $secret }}" readonly aria-label="Root password" data-secret>
                                <button type="button" class="link-btn" data-reveal>Show</button>
                                <button type="button" class="link-btn" data-copy-from-secret>Copy</button></span>
                            @else
                                <span class="muted">Your server uses an SSH key. <a href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Ask support</a> for access.</span>
                            @endif
                        </div>
                        <div class="cred-row"><span>SSH port</span><code>{{ $srv->ssh_port }}</code></div>
                    </div>

                    <details class="connect-help" open>
                        <summary>How to connect</summary>
                        <p><strong>Mac, Linux or Windows Terminal / PowerShell</strong> — run this, then paste the password when asked:</p>
                        <pre class="cmd"><code>{{ $sshCmd }}</code><button type="button" class="link-btn" data-copy-text="{{ $sshCmd }}">Copy</button></pre>
                        <p><strong>PuTTY (Windows)</strong> — Host Name <code>{{ $srv->ip_address }}</code>, Port <code>{{ $srv->ssh_port }}</code>, Connection type SSH → Open → log in as <code>{{ $srv->ssh_user }}</code> → right-click to paste the password.</p>
                        <p class="muted small">Tip: change the password after your first login with <code>passwd</code>.</p>
                    </details>
                </div>
            @endif

            @if ($keyHelp)
                <div class="panel" id="ai"><h2>{{ $service->kind === 'n8n' ? 'Getting started' : 'Add your AI API key' }}</h2>
                    @if (! $active)
                        <p class="muted">Once your server is delivered, this box shows the exact steps to add your own AI provider key (OpenRouter, OpenAI, Anthropic…).</p>
                    @elseif ($service->kind === 'n8n')
                        <ol class="start-steps">
                            <li><strong>Open n8n</strong> — @if ($service->url)<a href="{{ $service->url }}" target="_blank" rel="noopener">{{ $service->url }}</a>@else your app address @endif</li>
                            <li><strong>Create your owner account</strong> — the first account created becomes the owner, so do this straight away.</li>
                            <li><strong>Add your AI keys</strong> — in n8n go to <em>Credentials → Add credential</em> and choose OpenAI, OpenRouter or another provider.</li>
                        </ol>
                        <p class="muted small">Add your AI API key inside n8n — your keys stay on your server; DeveloperJet never sees them.</p>
                    @else
                        <p>{{ $keyHelp['intro'] }}</p>
                        @foreach ($keyHelp['commands'] as $cmd)
                            <pre class="cmd"><code>{{ $cmd }}</code><button type="button" class="link-btn" data-copy-text="{{ $cmd }}">Copy</button></pre>
                        @endforeach
                        <p class="muted small">Replace <code>your-key-here</code> with your own key. Your key stays on your server — DeveloperJet never sees it.</p>
                    @endif
                </div>
            @endif

            @if (in_array($service->kind, ['hermes_workspace', 'n8n', 'bundle', 'openclaw']))
                <div class="panel"><h2>Domain</h2>
                    <p class="muted">Your app runs on @if ($service->appDomain())<strong>{{ $service->appDomain() }}</strong>@else a DeveloperJet address @endif with HTTPS. To use your own domain, add it and verify ownership.</p>
                    <form method="POST" action="{{ route('dashboard.services.domain', $service) }}" class="form-grid">@csrf
                        <label>Your own domain<input name="custom_domain" value="{{ old('custom_domain', $service->custom_domain) }}" placeholder="app.example.com" maxlength="190"></label>
                        <div class="btn-row"><button class="button-secondary" type="submit">Save domain</button></div>
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
        </div>

        <aside class="svc-side">
            <div class="panel"><h2>Plan &amp; billing</h2>
                <dl class="kv">
                    <div><dt>Plan</dt><dd>{{ $p?->name ?? '—' }}</dd></div>
                    <div><dt>Billing</dt><dd>{{ $sub?->cycleLabel() ?? '—' }}</dd></div>
                    <div><dt>Renews</dt><dd>{{ $service->renews_at?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>Order</dt><dd>@if ($service->order)<a href="{{ route('dashboard.orders.show', $service->order) }}">{{ $service->order->number }}</a>@else — @endif</dd></div>
                </dl>
                @if ($invoices->isNotEmpty())
                    <ul class="svc-invoices" id="invoices">
                        @foreach ($invoices->take(3) as $invoice)
                            <li><a href="{{ route('dashboard.invoices.download', $invoice) }}">{{ $invoice->number }}</a><span>{{ \App\Support\Money::inr($invoice->total_minor) }}</span><x-status-badge :status="$invoice->status" /></li>
                        @endforeach
                    </ul>
                @endif
                <p class="muted small"><a href="{{ route('dashboard.subscriptions') }}">Renewals &amp; cancellation</a></p>
            </div>

            <div class="panel"><h2>Manage</h2>
                @foreach ($availableControls as $c)
                    <p class="small" style="margin:0 0 6px"><span class="badge badge-good">Available</span> {{ $c['label'] }}</p>
                @endforeach
                <div class="svc-manage">
                    <a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Request upgrade</a>
                    <a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Reboot or reinstall</a>
                    <a class="button-secondary" href="{{ route('docs.index') }}">Documentation</a>
                </div>
                <p class="muted small">Need a reboot, reinstall or a bigger plan? Our team handles it from a support ticket.</p>
            </div>

            @if ($health->isNotEmpty())
                <div class="panel"><h2>Health checks</h2>
                    @foreach ($health as $h)
                        <p style="margin:6px 0"><x-status-badge :status="$h->status" /> {{ \Illuminate\Support\Str::headline($h->check) }}
                            <span class="muted small">{{ $h->created_at?->format('d M, H:i') }}</span></p>
                    @endforeach
                </div>
            @endif

            @if ($unmanaged)
                <div class="panel svc-note">
                    <h2>Good to know</h2>
                    <p class="small">This is your own self-hosted server with full root access. Updates, firewall, your data and anything you install are yours to look after.</p>
                    <p class="small"><strong>Backups are not included</strong> — contact our sales team for a backup plan.</p>
                    @if ($service->kind === 'claude_code')<p class="small">Claude Code needs your own Claude subscription or API key. DeveloperJet does not provide or resell Claude access.</p>@endif
                </div>
            @endif
        </aside>
    </div>
@endsection
