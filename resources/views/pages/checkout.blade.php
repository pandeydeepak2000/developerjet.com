@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <section class="shop-page section-shell">
        <h1 class="shop-title">Checkout</h1>
        <form method="POST" action="{{ route('checkout.store') }}" class="shop-grid" data-loading-form>
            @csrf
            <div class="shop-lines">
                @foreach ($summary['lines'] as $line)
                    <article class="shop-line">
                        <div>
                            <p class="plan-product">{{ $line['plan']->category->name }}</p>
                            <h2>{{ $line['plan']->name }}</h2>
                            <p class="shop-meta">{{ \App\Support\BillingCycle::billedPhrase($line['interval']) }}
                                @if ($line['plan']->price_status !== 'approved') · <span class="badge badge-warn">Pricing pending approval</span>@endif
                            </p>
                            @if ($line['plan']->kind === 'hosting')
                                @php($unmanaged = $line['plan']->access_model === 'unmanaged')
                                <p class="shop-meta"><strong>{{ $unmanaged ? 'Unmanaged VPS' : 'Managed hosting' }}.</strong>
                                    {{ $unmanaged ? 'You receive SSH/root access and are responsible for updates, security and your workloads.' : 'DeveloperJet sets up and secures the server. Root access is restricted by service policy.' }}
                                    @if ($line['plan']->resourceProfile)Resources: {{ $line['plan']->resourceProfile->summary() }}@else Resources: to be confirmed @endif.
                                    Delivery follows manual VPS assignment by our team, so it is not instant.</p>
                            @endif
                            @foreach ($line['addons'] as $addon)<p class="shop-meta">+ {{ $addon->name }} ({{ \App\Support\Money::inr($addon->price_minor) }})</p>@endforeach
                        </div>
                        <div class="shop-line-side"><strong>{{ \App\Support\Money::inr($line['line_total_minor']) }}</strong></div>
                    </article>
                @endforeach

                @php($setupLines = collect($summary['lines'])->map(fn ($l) => ['plan' => $l['plan'], 'needs' => \App\Http\Controllers\CheckoutController::setupNeeds($l['plan'])])->filter(fn ($l) => $l['needs']['domain'] || $l['needs']['ai']))
                @php($aiProviders = config('provisioning.ai_providers'))
                @if ($setupLines->isNotEmpty())
                    <div class="panel checkout-setup">
                        <h2>Setup details</h2>
                        <p class="muted">Optional. You can change both later from your dashboard.</p>
                        @foreach ($setupLines as $s)
                            @php($pid = $s['plan']->id)
                            @php($mode = old('setup.'.$pid.'.domain_mode', 'default'))
                            <fieldset class="setup-block">
                                <legend>{{ $s['plan']->category->name }} · {{ $s['plan']->name }}</legend>

                                @if ($s['needs']['domain'])
                                    <p class="setup-label">App address <small>(where you open it in the browser)</small></p>
                                    <div class="setup-choices" data-domain-choice>
                                        <label class="setup-choice"><input type="radio" name="setup[{{ $pid }}][domain_mode]" value="default" @checked($mode !== 'own')>
                                            <span><strong>Use a DeveloperJet address</strong><small>Our team sets up a secure subdomain for you. Nothing to do.</small></span></label>
                                        <label class="setup-choice"><input type="radio" name="setup[{{ $pid }}][domain_mode]" value="own" @checked($mode === 'own')>
                                            <span><strong>Use my own domain</strong><small>e.g. n8n.yourcompany.com. You verify it with one DNS record after ordering.</small></span></label>
                                        <label class="setup-domain">Your domain
                                            <input name="setup[{{ $pid }}][domain]" value="{{ old("setup.$pid.domain") }}" maxlength="190" placeholder="n8n.yourcompany.com" autocomplete="off" inputmode="url">
                                        </label>
                                    </div>
                                @endif

                                @if ($s['needs']['ai'])
                                    <p class="setup-label">AI provider key <small>(optional — add it later if you prefer)</small></p>
                                    <div class="form-grid">
                                        <label>Provider
                                            <select name="setup[{{ $pid }}][ai_provider]">
                                                <option value="">Add later in dashboard</option>
                                                @foreach ($aiProviders as $key => $label)<option value="{{ $key }}" @selected(old("setup.$pid.ai_provider") === $key)>{{ $label }}</option>@endforeach
                                            </select>
                                        </label>
                                        <label>API key
                                            <input type="password" name="setup[{{ $pid }}][ai_api_key]" maxlength="300" autocomplete="off" placeholder="sk-…">
                                        </label>
                                    </div>
                                    <p class="setup-note"><x-icon name="lock" :size="16" /> Stored encrypted and used only to configure your service. We never show it again or email it. AI usage is billed by your provider.</p>
                                @endif
                            </fieldset>
                        @endforeach
                    </div>
                @endif

                @php($bu = auth()->user())
                @php($editBilling = route('dashboard.profile', ['return' => 'checkout']).'#billing')
                <div class="panel billed-to">
                    <div class="billed-to__head">
                        <h2>Billed to</h2>
                        <a class="billed-to__edit" href="{{ $editBilling }}">{{ $bu->billing_address ? 'Edit' : 'Add billing address' }}</a>
                    </div>
                    <div class="billed-to__body">
                        <p><strong>{{ $bu->billing_company ?: $bu->name }}</strong>@if ($bu->billing_company)<br><span class="muted">{{ $bu->name }}</span>@endif</p>
                        <p class="muted">{{ $bu->email }}@if ($bu->phone) · {{ $bu->phone }}@endif</p>
                        @if ($bu->billing_address)
                            <p>{{ $bu->billing_address }}<br>{{ collect([$bu->billing_city, $bu->billing_state, $bu->billing_postcode])->filter()->implode(', ') }}@if ($bu->billing_country) · {{ \App\Http\Controllers\CustomerProfileController::COUNTRIES[$bu->billing_country] ?? $bu->billing_country }}@endif</p>
                        @else
                            <p class="billed-to__missing">No billing address yet. <a href="{{ $editBilling }}">Add it once</a> and it appears on all your invoices.</p>
                        @endif
                        @if ($bu->gstin)<p><span class="badge badge-neutral">GSTIN</span> {{ $bu->gstin }}</p>@endif
                    </div>
                </div>

                <div class="panel">
                    <h2>Payment method</h2>
                    @php($gstLabel = $gstPercent > 0 ? '+ '.rtrim(rtrim(number_format($gstPercent, 2), '0'), '.').'% GST' : null)
                    <div class="pay-options" data-pay-options>
                        @foreach ($gateways as $key => $gateway)
                            <label class="pay-option">
                                <input type="radio" name="payment_method" value="{{ $key }}" data-pay-route="online" @checked($loop->first)>
                                <span class="pay-option__body">
                                    <strong>{{ $gateway->label() }}@if ($gateway->mode() === 'test') <span class="badge badge-warn">Test mode</span>@endif</strong>
                                    <small>UPI, cards and netbanking · Powered by {{ config('payments.razorpay_powered_by', 'JixPay') }}</small>
                                </span>
                                @if ($gstLabel)<span class="pay-option__tag">{{ $gstLabel }}</span>@endif
                            </label>
                        @endforeach
                        <label class="pay-option {{ $walletCovers ? '' : 'is-disabled' }}">
                            <input type="radio" name="payment_method" value="wallet" data-pay-route="wallet" @disabled(! $walletCovers) @checked(! $gateways && $walletCovers)>
                            <span class="pay-option__body">
                                <strong>Wallet balance</strong>
                                <small>{{ \App\Support\Money::inr($wallet->balance_minor) }} available
                                    @unless ($walletCovers) · not enough for this order — <a href="{{ route('dashboard.wallet') }}">add funds</a>@endunless</small>
                            </span>
                            <span class="pay-option__tag is-good">No GST</span>
                        </label>
                    </div>
                    @unless ($gateways)
                        <div class="flash flash-info" role="note">Online payment is not enabled yet. Pay from your wallet, or place the order and pay it later.</div>
                    @endunless
                </div>
            </div>

            <aside class="shop-summary" aria-label="Checkout summary">
                <h2>Review</h2>
                @if ($pricing)
                    {{-- Two price views: online (with GST) and wallet (no GST). The selected payment method decides which is shown. --}}
                    <dl data-review="online" @if (! $gateways && $walletCovers) hidden @endif>
                        <div><dt>Subtotal</dt><dd>{{ \App\Support\Money::inr($pricing['subtotal']) }}</dd></div>
                        @if ($pricing['discount'])<div><dt>Discount</dt><dd>− {{ \App\Support\Money::inr($pricing['discount']) }}</dd></div>@endif
                        <div><dt>{{ $gstPercent > 0 ? 'GST ('.rtrim(rtrim(number_format($gstPercent, 2), '0'), '.').'%)' : 'Tax' }}</dt><dd>{{ $pricing['tax_configured'] ? \App\Support\Money::inr($pricing['tax']) : 'Not configured' }}</dd></div>
                        <div><dt><strong>Total</strong></dt><dd><strong>{{ \App\Support\Money::inr($pricing['total']) }}</strong></dd></div>
                    </dl>
                    @if ($walletPricing)
                        <dl data-review="wallet" @unless (! $gateways && $walletCovers) hidden @endunless>
                            <div><dt>Subtotal</dt><dd>{{ \App\Support\Money::inr($walletPricing['subtotal']) }}</dd></div>
                            @if ($walletPricing['discount'])<div><dt>Discount</dt><dd>− {{ \App\Support\Money::inr($walletPricing['discount']) }}</dd></div>@endif
                            <div><dt>GST</dt><dd>₹0 <small class="muted">(wallet)</small></dd></div>
                            <div><dt><strong>Total</strong></dt><dd><strong>{{ \App\Support\Money::inr($walletPricing['total']) }}</strong></dd></div>
                        </dl>
                    @endif
                    @unless ($pricing['tax_configured'])
                        <p class="muted small">Tax rules are pending configuration; the final tax will be shown before you pay.</p>
                    @endunless
                @endif
                @unless ($approved)
                    <div class="flash flash-info" role="note"><strong>Pricing pending approval.</strong> These prices are illustrative, so online purchase is not enabled for this cart yet. Contact Sales to proceed.</div>
                @endunless
                <label class="choice"><input type="checkbox" name="accept_terms" value="1" required> <span>I accept the <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms</a> and <a href="{{ route('refund') }}" target="_blank" rel="noopener">Refund Policy</a>.</span></label>
                @if ($approved && $summary['unavailable'] === 0)
                    <button class="auth-submit" type="submit">Place order</button>
                @else
                    <button class="auth-submit auth-submit-disabled" type="button" disabled>Order unavailable</button>
                    <a class="auth-secondary-action" href="{{ route('contact') }}">Talk to Sales</a>
                @endif
            </aside>
        </form>
    </section>
@endsection
