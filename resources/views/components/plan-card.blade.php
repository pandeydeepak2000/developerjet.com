@props([
    'product',
    'name',
    'description',
    'monthly',
    'annualTotal',
    'annualMonthly',
    'features' => [],
    'highlight',
    'planNote' => null,
    'featured' => false,
])

<article class="plan-card {{ $featured ? 'plan-card-featured' : '' }}" data-plan-card data-annual-total="₹{{ number_format($annualTotal) }}">
    @if ($featured)
        <span class="plan-ribbon">Most popular</span>
    @endif
    <div class="plan-card-top">
        <p class="plan-product">{{ $product }}</p>
        <h3>{{ $name }}</h3>
        <p class="plan-description">{{ $description }}</p>
    </div>
    <div class="plan-highlight">
        <span>Plan highlight</span>
        <strong>{{ $highlight }}</strong>
    </div>
    <div class="plan-price">
        <div class="plan-rate">
            <span class="price-amount" data-monthly="₹{{ number_format($monthly) }}" data-annual="₹{{ number_format($annualMonthly) }}">₹{{ number_format($monthly) }}</span>
            <span class="price-period">/ month</span>
        </div>
        <span class="price-compare" data-monthly-compare hidden>₹{{ number_format($monthly) }} / month on monthly billing</span>
        <span class="billing-note" data-billing-note>Billed monthly</span>
        <p class="annual-total" data-annual-total-note hidden>₹{{ number_format($annualTotal) }} billed for 12 months</p>
        <p class="annual-savings" data-annual-savings-note hidden>
            Save ₹{{ number_format(($monthly * 12) - $annualTotal) }} compared with monthly billing
        </p>
    </div>
    <ul class="plan-features">
        @foreach ($features as $feature)
            <li><span aria-hidden="true">✓</span>{{ $feature }}</li>
        @endforeach
    </ul>
    @if ($planNote)
        <p class="plan-note">{{ $planNote }}</p>
    @endif
    <a class="plan-get-started" href="https://wa.me/917688855600?text={{ rawurlencode('Hi DeveloperJet, I would like to get started with the '.$product.' '.$name.' plan. Please help me with the next steps.') }}" target="_blank" rel="noopener noreferrer">
        Get started <span aria-hidden="true">↗</span>
    </a>
</article>
