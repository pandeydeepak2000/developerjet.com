{{-- AI All-Access service on the admin order page: deliver / replace the API key, suspend / unsuspend. --}}
@php
    $delivered = in_array($service->status, ['active', 'suspended'], true);
    $cfg = $service->config ?? [];
    $credit = $cfg['credit'] ?? ($service->plan?->comparison['Monthly usage credit'] ?? null);
    $keys = $service->plan?->comparison['API keys'] ?? null;
    $canDeliver = $u->hasPermission('deployments.deploy');
@endphp
<section class="admin-card" @if ($loop->first) id="setup" @endif>
    <div class="dj-order-hero">
        <div>
            <h2 style="margin:0">{{ $service->label }}</h2>
            <p class="muted small" style="margin:4px 0 0">AI All-Access · {{ $credit ? $credit.' monthly usage credit' : 'credit as per plan' }}{{ $keys ? ' · '.$keys.' API key(s)' : '' }}</p>
        </div>
        <x-status-badge :status="$service->status" />
    </div>

    <ol class="dj-stepper" aria-label="Setup steps">
        <li class="{{ $delivered ? 'is-done' : 'is-current' }}"><b>{{ $delivered ? '✓' : '1' }}</b> Create key at ModelGo</li>
        <li class="{{ $delivered ? 'is-done' : '' }}"><b>{{ $delivered ? '✓' : '2' }}</b> Paste &amp; deliver</li>
        <li class="{{ $delivered ? 'is-done' : '' }}"><b>{{ $delivered ? '✓' : '3' }}</b> Customer uses it</li>
    </ol>

    @unless ($delivered)
        <div class="admin-setup-note" style="margin-bottom:12px">
            In the <a class="admin-strong-link" href="https://console.modelgo.ai" target="_blank" rel="noopener">ModelGo console</a> create an API key for this customer with a monthly spend cap of <strong>{{ $credit ?? 'the plan credit' }}</strong>{{ $keys && (int) $keys > 1 ? ' (up to '.$keys.' keys)' : '' }}, then paste it below.
        </div>
    @endunless

    @if ($delivered)
        <dl class="admin-setup-facts">
            <div><dt>API key</dt><dd>{{ $service->aiConfigured() ? '•••• saved (encrypted)' : 'not saved' }}</dd></div>
            <div><dt>Base URL</dt><dd><code>{{ $cfg['api_base_url'] ?? '—' }}</code></dd></div>
            @if (! empty($cfg['anthropic_base_url']))<div><dt>Anthropic URL</dt><dd><code>{{ $cfg['anthropic_base_url'] }}</code></dd></div>@endif
            <div><dt>Credit</dt><dd>{{ $credit ?? '—' }} / month</dd></div>
        </dl>
    @endif

    @if ($canDeliver && $service->order && \App\Support\OrderStatus::isPaidState($service->order->status))
        <form method="POST" action="{{ route('admin.services.access', $service) }}" class="admin-form admin-setup-form dj-step-card" data-submit-once="Saving…" autocomplete="off">@csrf
            <h3><span class="admin-step-num">{{ $delivered ? '↻' : '2' }}</span> {{ $delivered ? 'Replace key or URL' : 'Deliver API access' }}</h3>
            <div class="admin-form-grid">
                <label class="span-2">API key
                    <input type="password" name="api_key" autocomplete="new-password" @unless ($delivered) required @endunless placeholder="{{ $delivered ? '•••• saved — leave blank to keep' : 'Paste the key created at ModelGo' }}">
                    <small class="muted">Stored encrypted. The customer sees it in their dashboard; it is never emailed.</small>
                </label>
                <label>Base URL (OpenAI-compatible)
                    <input name="base_url" value="{{ old('base_url', $cfg['api_base_url'] ?? '') }}" placeholder="https://… (from the ModelGo console)" required>
                </label>
                <label>Anthropic base URL (optional)
                    <input name="anthropic_base_url" value="{{ old('anthropic_base_url', $cfg['anthropic_base_url'] ?? '') }}" placeholder="for Claude Code, if different">
                </label>
                <label>Monthly credit shown to customer
                    <input name="credit" value="{{ old('credit', $credit) }}" placeholder="$20">
                </label>
            </div>
            <div class="admin-form-actions"><button class="admin-btn" type="submit">{{ $delivered ? 'Save changes' : 'Deliver to customer' }}</button></div>
        </form>
    @endif

    @if ($delivered && $u->hasPermission('servers.edit') && $canDeliver)
        <div class="dj-deploy-row" style="margin-top:12px">
            <form method="POST" action="{{ route('admin.services.access-status', $service) }}" class="admin-inline-form" data-submit-once="Saving…"
                  onsubmit="return confirm(@js($service->status === 'active' ? 'Suspend this AI access? Also disable the key in the ModelGo console.' : 'Unsuspend this AI access?'))">@csrf
                <button class="admin-btn {{ $service->status === 'active' ? 'admin-btn-danger' : '' }}" type="submit">{{ $service->status === 'active' ? 'Suspend' : 'Unsuspend' }}</button></form>
        </div>
    @endif
</section>
