@extends('layouts.dashboard')

@section('title', $service->label)
@section('heading', $service->label)
@section('subheading', 'AI All-Access')

@section('dash')
    @php
        $p = $service->plan;
        $sub = $service->subscription;
        $cfg = $service->config ?? [];
        $active = $service->status === 'active';
        $credit = $cfg['credit'] ?? ($p?->comparison['Monthly usage credit'] ?? null);
        $keys = $p?->comparison['API keys'] ?? '1';
        $base = $cfg['api_base_url'] ?? null;
        $anthropic = $cfg['anthropic_base_url'] ?? $base;
        $key = $active && $service->aiConfigured() ? (string) $service->ai_api_key : null;
        $openInvoice = $sub?->invoices->first(fn ($i) => $i->isUnpaid());
    @endphp

    <section class="svc-hero">
        <div class="svc-hero-main">
            <span class="svc-hero-icon" aria-hidden="true">AI</span>
            <div>
                <div class="svc-hero-title"><h2>{{ $service->label }}</h2><x-status-badge :status="$service->status" /></div>
                <p class="muted">One API key for GPT, Claude, Gemini, DeepSeek and more · {{ $sub?->cycleLabel() ?? 'Billing' }}@if ($service->renews_at) · renews {{ $service->renews_at->format('d M Y') }}@endif</p>
            </div>
        </div>
        <div class="svc-hero-actions">
            <a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Get help</a>
        </div>
    </section>

    @if ($service->status === 'suspended')
        <div class="flash-warning" role="status"><strong>This service is suspended.</strong> Your API key is paused. @if ($openInvoice && $openInvoice->order_id)<a href="{{ route('dashboard.orders.show', $openInvoice->order_id) }}">Pay the open invoice</a> or @endif <a href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">contact support</a>.</div>
    @elseif ($openInvoice && $openInvoice->order_id)
        <div class="flash-warning" role="status">Renewal invoice <strong>{{ $openInvoice->number }}</strong> for {{ \App\Support\Money::inr($openInvoice->total_minor) }} is due on {{ $openInvoice->due_at?->format('d M Y') }}. <a href="{{ route('dashboard.orders.show', $openInvoice->order_id) }}">Renew now</a>.</div>
    @endif

    <div class="svc-stats">
        <div class="svc-stat"><span>Monthly credit</span><strong>{{ $credit ?? '—' }}</strong></div>
        <div class="svc-stat"><span>API keys</span><strong>{{ $keys }}</strong></div>
        <div class="svc-stat"><span>Models</span><strong>GPT, Claude, Gemini, DeepSeek…</strong></div>
        <div class="svc-stat"><span>Renews</span><strong>{{ $service->renews_at?->format('d M Y') ?? '—' }}</strong></div>
    </div>

    <div class="svc-grid">
        <div class="svc-main">
            @if (! $key)
                <div class="panel" id="access">
                    <h2>Your API key</h2>
                    @if ($service->status === 'suspended')
                        <p class="muted">Your key is hidden while the service is suspended.</p>
                    @else
                        <p>We are creating your API key with a {{ $credit ?? 'monthly' }} usage credit. It appears here as soon as it is ready, and we email you. This is usually done within a few hours (business hours).</p>
                    @endif
                </div>
            @else
                <div class="panel server-login" id="access">
                    <div class="panel-head"><h2>Your API access</h2><span class="badge badge-good">Active</span></div>
                    <div class="cred-rows">
                        <div class="cred-row"><span>Base URL</span><code>{{ $base }}</code><button type="button" class="link-btn" data-copy-text="{{ $base }}">Copy</button></div>
                        @if ($anthropic && $anthropic !== $base)
                            <div class="cred-row"><span>Anthropic URL</span><code>{{ $anthropic }}</code><button type="button" class="link-btn" data-copy-text="{{ $anthropic }}">Copy</button></div>
                        @endif
                        <div class="cred-row"><span>API key</span>
                            <span class="secret-field"><input type="password" value="{{ $key }}" readonly aria-label="API key" data-secret>
                            <button type="button" class="link-btn" data-reveal>Show</button>
                            <button type="button" class="link-btn" data-copy-from-secret>Copy</button></span>
                        </div>
                    </div>
                    <p class="muted small" style="margin-top:10px">Keep this key private — anyone with it can use your credit. If it leaks, <a href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">ask us to replace it</a>.</p>
                </div>

                <div class="panel" id="setup">
                    <h2>Use it in your tools</h2>
                    <details class="connect-help" open>
                        <summary>Claude Code</summary>
                        <pre class="cmd"><code>export ANTHROPIC_BASE_URL={{ $anthropic }}
export ANTHROPIC_AUTH_TOKEN=your-api-key
claude</code></pre>
                    </details>
                    <details class="connect-help">
                        <summary>OpenAI SDK, Cursor and other OpenAI-compatible apps</summary>
                        <pre class="cmd"><code>export OPENAI_BASE_URL={{ $base }}
export OPENAI_API_KEY=your-api-key</code></pre>
                        <p class="small">In apps with settings (Cursor, Continue, LibreChat…), choose an OpenAI-compatible provider and paste the Base URL and key.</p>
                    </details>
                    <details class="connect-help">
                        <summary>n8n</summary>
                        <p class="small">Credentials → Add credential → <strong>OpenAI</strong> → paste the key, and set <em>Base URL</em> to <code>{{ $base }}</code>.</p>
                    </details>
                    <details class="connect-help">
                        <summary>Hermes Agent</summary>
                        <pre class="cmd"><code>hermes model</code></pre>
                        <p class="small">Choose a custom OpenAI-compatible endpoint and paste the Base URL and key.</p>
                    </details>
                    <p class="muted small">Replace <code>your-api-key</code> with the key above. Pick any model the gateway lists (for example a GPT, Claude, Gemini or DeepSeek model).</p>
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
                    <ul class="svc-invoices">
                        @foreach ($invoices->take(3) as $invoice)
                            <li><a href="{{ route('dashboard.invoices.download', $invoice) }}">{{ $invoice->number }}</a><span>{{ \App\Support\Money::inr($invoice->total_minor) }}</span><x-status-badge :status="$invoice->status" /></li>
                        @endforeach
                    </ul>
                @endif
                <p class="muted small"><a href="{{ route('dashboard.subscriptions') }}">Renewals &amp; cancellation</a></p>
            </div>
            <div class="panel"><h2>Manage</h2>
                <div class="svc-manage">
                    <a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Add credit or upgrade</a>
                    <a class="button-secondary" href="{{ route('dashboard.tickets.create', ['service' => $service->id]) }}">Replace my key</a>
                </div>
            </div>
            <div class="panel svc-note">
                <h2>Good to know</h2>
                <p class="small">Your monthly credit is used as you call models; prices differ per model. When the credit runs out, requests stop until your plan renews — ask us for a top-up any time.</p>
            </div>
        </aside>
    </div>
@endsection
