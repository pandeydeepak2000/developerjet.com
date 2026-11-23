@extends('layouts.app')

@section('title', 'Your cart')

@section('content')
    <section class="shop-page section-shell">
        <h1 class="shop-title">Your cart</h1>

        @if (! $summary['lines'])
            <div class="empty-state">
                <h2>Your cart is empty</h2>
                <p>Choose a plan to get started.</p>
                <a class="button-primary" href="{{ route('plans') }}">Browse plans <span aria-hidden="true">↗</span></a>
            </div>
        @else
            <div class="shop-grid">
                <div class="shop-lines">
                    @foreach ($summary['lines'] as $line)
                        <article class="shop-line {{ $line['available'] ? '' : 'is-unavailable' }}">
                            <div>
                                <p class="plan-product">{{ $line['plan']->category->name }}</p>
                                <h2>{{ $line['plan']->name }}</h2>
                                <p class="shop-meta">{{ \App\Support\BillingCycle::billedPhrase($line['interval']) }}@if ($line['plan']->highlight) · {{ $line['plan']->highlight }}@endif</p>
                                @unless ($line['available'])
                                    <p class="field-error">This plan is no longer available to purchase. Please remove it.</p>
                                @endunless
                                @foreach ($line['addons'] as $addon)
                                    <p class="shop-meta">+ {{ $addon->name }} ({{ \App\Support\Money::inr($addon->price_minor) }})</p>
                                @endforeach
                            </div>
                            <div class="shop-line-side">
                                <strong>{{ $line['available'] ? \App\Support\Money::inr($line['line_total_minor']) : '—' }}</strong>
                                <form method="POST" action="{{ route('cart.destroy', $line['item']->id) }}" data-loading-form>
                                    @csrf @method('DELETE')
                                    <button class="link-button" type="submit" aria-label="Remove {{ $line['plan']->name }} from cart">Remove</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                <aside class="shop-summary" aria-label="Order summary">
                    <h2>Summary</h2>
                    <dl>
                        <div><dt>Subtotal</dt><dd>{{ \App\Support\Money::inr($summary['total_minor']) }}</dd></div>
                        <div><dt>Taxes</dt><dd>Calculated at checkout</dd></div>
                    </dl>
                    <p class="shop-meta">Prices are indicative and confirmed with Sales before activation. AI provider API usage is not included.</p>
                    @if ($summary['unavailable'] === 0)
                        <a class="button-primary shop-checkout" href="{{ route('checkout') }}">Proceed to checkout <span aria-hidden="true">↗</span></a>
                        @guest<p class="shop-meta">You’ll be asked to log in or create a verified account first.</p>@endguest
                    @else
                        <p class="field-error">Remove unavailable plans to continue.</p>
                    @endif
                </aside>
            </div>
        @endif
    </section>
@endsection
