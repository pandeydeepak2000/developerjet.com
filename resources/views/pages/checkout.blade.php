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

                <div class="panel">
                    <h2>Billing details</h2>
                    <p class="muted">Used on your invoice. GSTIN is optional.</p>
                    <div class="form-grid">
                        <label>Billing name<input name="billing_name" value="{{ old('billing_name', auth()->user()->name) }}" maxlength="120"></label>
                        <label>GSTIN (optional)<input name="gstin" value="{{ old('gstin') }}" maxlength="15" pattern="[0-9A-Z]{15}" placeholder="15 characters"></label>
                        <label class="span-2">Billing address<textarea name="billing_address" rows="3" maxlength="500">{{ old('billing_address') }}</textarea></label>
                    </div>
                </div>

                <div class="panel">
                    <h2>Payment method</h2>
                    @if ($gateways)
                        <div class="choice-list">
                            @foreach ($gateways as $key => $gateway)
                                <label class="choice"><input type="radio" name="payment_method" value="{{ $key }}" @checked($loop->first)> <span>{{ $gateway->label() }}@if ($gateway->mode() === 'test') <span class="badge badge-warn">Test mode</span>@endif</span></label>
                            @endforeach
                        </div>
                    @else
                        <div class="flash flash-info" role="note">No online payment method is enabled yet. You can still place the order; it stays unpaid until a payment method is available or you use your wallet balance.</div>
                    @endif
                    @if ($wallet->balance_minor > 0)
                        <label class="choice"><input type="checkbox" name="use_wallet" value="1"> <span>Apply wallet balance ({{ \App\Support\Money::inr($wallet->balance_minor) }} available)</span></label>
                    @endif
                </div>
            </div>

            <aside class="shop-summary" aria-label="Checkout summary">
                <h2>Review</h2>
                @if ($pricing)
                    <dl>
                        <div><dt>Subtotal</dt><dd>{{ \App\Support\Money::inr($pricing['subtotal']) }}</dd></div>
                        @if ($pricing['discount'])<div><dt>Discount</dt><dd>− {{ \App\Support\Money::inr($pricing['discount']) }}</dd></div>@endif
                        <div><dt>Tax</dt><dd>{{ $pricing['tax_configured'] ? \App\Support\Money::inr($pricing['tax']) : 'Not configured' }}</dd></div>
                        <div><dt><strong>Total</strong></dt><dd><strong>{{ \App\Support\Money::inr($pricing['total']) }}</strong></dd></div>
                    </dl>
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
