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
        $helpUrl = route('dashboard.tickets.create', ['service' => $service->id]);
        $actionInfo = [
            'reboot' => ['Reboot server', 'Restart the whole VPS. Back online in about 1–2 minutes.', 'Reboot your server now? It will be back in about 1–2 minutes.'],
            'restart_app' => ['Restart '.$appName, 'Fixes most hangs. '.$appName.' is unavailable for a few seconds.', 'Restart '.$appName.' now? It is unavailable for a few seconds.'],
            'reset_login' => ['Forgot '.$appName.' password? Reset login', 'Create the owner account again. Your workflows and credentials are kept.', 'Reset the '.$appName.' login? You will create the owner account again on the next visit. Your workflows and credentials are kept.'],
        ];
    @endphp

    {{-- Header: what it is, its live state, and the things the customer does most. --}}
    <section class="svc-hero">
        <div class="svc-hero-main">
            <span class="svc-hero-icon" aria-hidden="true">{{ strtoupper(mb_substr($appName, 0, 1)) }}</span>
            <div>
                <div class="svc-hero-title"><h2>{{ $service->label }}</h2><x-status-badge :status="$service->status" />
                    @if ($liveStats)<span class="svc-live" data-live-status data-state="checking"><i aria-hidden="true"></i><span>Checking server…</span></span>@endif
                </div>
                <p class="muted">{{ $appName }} · {{ $p?->name ?? '—' }} plan · {{ $sub?->cycleLabel() ?? 'Billing' }}@if ($service->renews_at) · renews {{ $service->renews_at->format('d M Y') }}@endif</p>
            </div>
        </div>
        <div class="svc-hero-actions">
            @if ($active && $service->url)<a class="button-primary" href="{{ $service->url }}" target="_blank" rel="noopener">Open {{ $appName }} ↗</a>@endif
            @if (isset($actions['reboot']))
                <form method="POST" action="{{ route('dashboard.services.action', [$service, 'reboot']) }}" data-submit-once="Rebooting…" onsubmit="return confirm(@js($actionInfo['reboot'][2]))">@csrf
                    <button class="button-secondary" type="submit">⟳ Reboot</button></form>
            @endif
            <a class="button-secondary" href="{{ $helpUrl }}">Get help</a>
        </div>
    </section>

    @if ($service->status === 'suspended')
        <div class="flash-warning" role="status"><strong>This service is suspended.</strong> {{ $appName }} is stopped; your data is kept. @if ($openInvoice && $openInvoice->order_id)Pay the open invoice to restore it, or @endif <a href="{{ $helpUrl }}">contact support</a>.</div>
    @endif

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
                <p style="margin-top:12px"><a class="button-secondary" href="{{ $helpUrl }}">Contact support about this</a></p>
            @endif
        </div>
    @endunless

    {{-- Tabs (with JavaScript off every section simply shows, one after another). --}}
    <nav class="svc-tabs" aria-label="Service sections" data-svc-tabs>
        <a href="#tab-overview" data-tab-link="overview">Overview</a>
        @if ($active && $srv && $unmanaged)<a href="#tab-access" data-tab-link="access">Login &amp; SSH</a>@endif
        <a href="#tab-manage" data-tab-link="manage">Manage</a>
        <a href="#tab-billing" data-tab-link="billing">Billing</a>
    </nav>

    {{-- ───────── Overview ───────── --}}
    <div class="svc-tabpanel" id="tab-overview" data-tab-panel="overview">
        @if ($liveStats)
            <div class="panel usage" data-stats-url="{{ route('dashboard.services.stats', $service) }}">
                <div class="panel-head"><h2>Live server usage</h2><span class="muted small" data-stats-updated>Reading your server…</span></div>
                <p class="flash-warning usage-error" data-stats-error hidden></p>
                <div class="usage-grid">
                    @foreach ([['cpu', 'CPU'], ['mem', 'Memory (RAM)'], ['disk', 'Disk']] as [$k, $label])
                        <div class="usage-card" data-gauge="{{ $k }}">
                            <svg class="gauge" viewBox="0 0 120 120" aria-hidden="true"><circle class="gauge-track" cx="60" cy="60" r="50"/><circle class="gauge-fill" cx="60" cy="60" r="50" pathLength="100" stroke-dasharray="0 100"/></svg>
                            <div class="gauge-value"><strong data-gauge-pct>—</strong></div>
                            <div class="usage-text"><span>{{ $label }}</span><small data-gauge-detail>&nbsp;</small></div>
                        </div>
                    @endforeach
                    <div class="usage-card usage-net">
                        <div class="usage-text"><span>Network</span>
                            <p class="net-row"><b aria-hidden="true">↓</b> <strong data-net-rx>—</strong> <small>in</small></p>
                            <p class="net-row"><b aria-hidden="true">↑</b> <strong data-net-tx>—</strong> <small>out</small></p>
                            <small data-net-total>&nbsp;</small></div>
                    </div>
                </div>
                <div class="usage-charts">
                    <figure><figcaption>CPU % <small>since you opened this page</small></figcaption><svg viewBox="0 0 300 60" preserveAspectRatio="none" data-spark="cpu" aria-hidden="true"><polyline points=""/></svg></figure>
                    <figure><figcaption>RAM % <small>since you opened this page</small></figcaption><svg viewBox="0 0 300 60" preserveAspectRatio="none" data-spark="mem" aria-hidden="true"><polyline points=""/></svg></figure>
                </div>
                <dl class="usage-foot">
                    <div><dt>Uptime</dt><dd data-stat-uptime>—</dd></div>
                    <div><dt>Load (1/5/15 min)</dt><dd data-stat-load>—</dd></div>
                    <div><dt>Hostname</dt><dd data-stat-hostname>—</dd></div>
                    <div><dt>Swap</dt><dd data-stat-swap>—</dd></div>
                </dl>
                <p class="muted small" style="margin:10px 0 0">Readings come straight from your server and refresh every minute while this page is open.</p>
            </div>
        @endif

        <div class="svc-grid">
            <div class="svc-main">
                <div class="panel"><h2>Server details</h2>
                    <dl class="kv kv-wide">
                        <div><dt>Status</dt><dd><x-status-badge :status="$service->status" /></dd></div>
                        <div><dt>IP address</dt><dd>@if ($active && $srv)<code>{{ $srv->ip_address }}</code>@else — @endif</dd></div>
                        <div><dt>Operating system</dt><dd>{{ $srv?->os_name ?: ($active ? 'Ubuntu' : '—') }}</dd></div>
                        <div><dt>Size</dt><dd>{{ $size }}</dd></div>
                        <div><dt>Installed software</dt><dd>{{ $appName }}</dd></div>
                        @if ($service->needsDomain())<div><dt>App address</dt><dd>{{ $service->appDomain() ?? '—' }}</dd></div>@endif
                        @if ($active && $srv)<div><dt>SSH</dt><dd><code>{{ $srv->ssh_user }}{{ '@'.$srv->ip_address }}</code> · port {{ $srv->ssh_port }}</dd></div>@endif
                        <div><dt>Access</dt><dd>{{ $unmanaged ? 'Full root (self-hosted)' : 'Restricted' }}</dd></div>                    </dl>
                </div>

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
                            @if (isset($actions['reset_login']))<p class="small" style="margin-top:10px"><strong>Forgot your n8n password?</strong> Open the <em>Manage</em> tab and use <em>“Forgot n8n password? Reset login”</em> — you set a new owner account and keep your workflows.</p>@endif
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
            </div>

            <aside class="svc-side">
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
    </div>

    {{-- ───────── Login & SSH ───────── --}}
    @if ($active && $srv && $unmanaged)
        @php
            $secret = (string) $srv->ssh_credential;
            $isKey = str_starts_with(ltrim($secret), '-----BEGIN');
            $sshCmd = 'ssh '.$srv->ssh_user.'@'.$srv->ip_address.((int) $srv->ssh_port !== 22 ? ' -p '.$srv->ssh_port : '');
        @endphp
        <div class="svc-tabpanel" id="tab-access" data-tab-panel="access">
            <div class="svc-grid">
                <div class="svc-main">
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
                                    <span class="muted">Your server uses an SSH key. <a href="{{ $helpUrl }}">Ask support</a> for access.</span>
                                @endif
                            </div>
                            <div class="cred-row"><span>SSH port</span><code>{{ $srv->ssh_port }}</code></div>
                        </div>

                        <details class="connect-help" open>
                            <summary>How to connect</summary>
                            <p><strong>Mac, Linux or Windows Terminal / PowerShell</strong> — run this, then paste the password when asked:</p>
                            <pre class="cmd"><code>{{ $sshCmd }}</code><button type="button" class="link-btn" data-copy-text="{{ $sshCmd }}">Copy</button></pre>
                            <p><strong>PuTTY (Windows)</strong> — Host Name <code>{{ $srv->ip_address }}</code>, Port <code>{{ $srv->ssh_port }}</code>, Connection type SSH → Open → log in as <code>{{ $srv->ssh_user }}</code> → right-click to paste the password.</p>
                        </details>
                    </div>
                </div>
                <aside class="svc-side">
                    <div class="panel" id="root-password"><h2>Root password</h2>
                        @if ($canResetRoot)
                            <form method="POST" action="{{ route('dashboard.services.root-password', $service) }}" class="rootpw-form" data-submit-once="Changing…" onsubmit="return confirm('Change the root password now? The current password stops working immediately.')">@csrf
                                <input type="hidden" name="mode" value="custom">
                                <label>New password
                                    <span class="secret-field"><input type="password" name="root_password" minlength="12" maxlength="64" required autocomplete="new-password" data-secret>
                                    <button type="button" class="link-btn" data-reveal>Show</button></span></label>
                                <label>Type it again<input type="password" name="root_password_confirmation" minlength="12" maxlength="64" required autocomplete="new-password"></label>
                                @error('root_password', 'rootPassword')<p class="field-error">{{ $message }}</p>@enderror
                                <p class="muted small" style="margin:0">At least 12 characters, with letters and numbers. No spaces.</p>
                                <button class="button-primary" type="submit">Change password</button>
                            </form>
                            <div class="rootpw-or"><span>or</span></div>
                            <form method="POST" action="{{ route('dashboard.services.root-password', $service) }}" data-submit-once="Generating…" onsubmit="return confirm('Generate a new random root password now? The current password stops working immediately.')">@csrf
                                <button class="button-secondary" type="submit" style="width:100%">Generate a strong password for me</button></form>
                            <p class="muted small">The new password works at once and shows under <em>Server login</em>. Please change it here rather than with <code>passwd</code> on the server — otherwise this panel can no longer reboot or show usage.</p>
                        @else
                            <p class="small">To change the root password, <a href="{{ $helpUrl }}">contact support</a>.</p>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    @endif

    {{-- ───────── Manage ───────── --}}
    <div class="svc-tabpanel" id="tab-manage" data-tab-panel="manage">
        @foreach ($availableControls as $c)
            <p class="small" style="margin:0 0 6px"><span class="badge badge-good">Available</span> {{ $c['label'] }}</p>
        @endforeach

        <div class="panel"><h2>Manage</h2>
            <div class="action-grid">
                @foreach ($actions as $key => $label)
                    @php [$title, $desc, $confirm] = $actionInfo[$key] ?? [$label, '', 'Run this action now?']; @endphp
                    <div class="action-card">
                        <div><h3>{{ $title }}</h3><p class="muted small">{{ $desc }}</p></div>
                        <form method="POST" action="{{ route('dashboard.services.action', [$service, $key]) }}" data-submit-once="Working…" onsubmit="return confirm(@js($confirm))">@csrf
                            <button class="button-secondary" type="submit">{{ $key === 'reboot' ? 'Reboot' : ($key === 'restart_app' ? 'Restart' : 'Reset login') }}</button></form>
                    </div>
                @endforeach
                <div class="action-card">
                    <div><h3>Upgrade plan</h3><p class="muted small">More CPU, RAM or disk. Our team moves you to a bigger plan.</p></div>
                    <a class="button-secondary" href="{{ $helpUrl }}">Request upgrade</a>
                </div>
                @unless (isset($actions['reboot']))
                    <div class="action-card">
                        <div><h3>Reboot or reinstall</h3><p class="muted small">Our team reboots or reinstalls your server from a support ticket.</p></div>
                        <a class="button-secondary" href="{{ $helpUrl }}">Reboot or reinstall</a>
                    </div>
                @endunless
                <div class="action-card">
                    <div><h3>Documentation</h3><p class="muted small">Guides for SSH, domains and every product.</p></div>
                    <a class="button-secondary" href="{{ route('docs.index') }}">Open docs</a>
                </div>
            </div>
        </div>

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

        @if ($canReinstall)
            <div class="panel danger-zone" id="reinstall">
                <h2>Reinstall fresh</h2>
                <p class="small">Deletes <strong>{{ $appName }} and all of its data</strong> on this server, then installs it again from scratch — like a new server. Your IP and root login stay the same. <strong>This cannot be undone</strong>, so download anything you need first.</p>
                <form method="POST" action="{{ route('dashboard.services.reinstall', $service) }}" class="reinstall-form" data-submit-once="Reinstalling…">@csrf
                    <label><span>Type <code>REINSTALL</code> to confirm</span><input name="confirm" autocomplete="off" required pattern="REINSTALL" placeholder="REINSTALL"></label>
                    <button class="button-danger" type="submit">Delete everything and reinstall</button>
                </form>
                @error('confirm')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @endif
    </div>

    {{-- ───────── Billing ───────── --}}
    <div class="svc-tabpanel" id="tab-billing" data-tab-panel="billing">
        <div class="panel"><h2>Plan &amp; billing</h2>
            <dl class="kv">
                <div><dt>Plan</dt><dd>{{ $p?->name ?? '—' }}</dd></div>
                <div><dt>Billing</dt><dd>{{ $sub?->cycleLabel() ?? '—' }}</dd></div>
                <div><dt>Renews</dt><dd>{{ $service->renews_at?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Order</dt><dd>@if ($service->order)<a href="{{ route('dashboard.orders.show', $service->order) }}">{{ $service->order->number }}</a>@else — @endif</dd></div>
            </dl>
            @if ($invoices->isNotEmpty())
                <ul class="svc-invoices" id="invoices">
                    @foreach ($invoices as $invoice)
                        <li><a href="{{ route('dashboard.invoices.download', $invoice) }}">{{ $invoice->number }}</a><span>{{ \App\Support\Money::inr($invoice->total_minor) }}</span><x-status-badge :status="$invoice->status" /></li>
                    @endforeach
                </ul>
            @endif
            <p class="muted small"><a href="{{ route('dashboard.subscriptions') }}">Renewals &amp; cancellation</a></p>
        </div>
    </div>
@endsection
