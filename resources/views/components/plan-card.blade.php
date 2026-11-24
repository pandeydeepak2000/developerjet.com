@props([
    'plan',                 // App\Models\Plan (prices + resourceProfile eager loaded)
    'product',              // Display name of the product family (used in the Sales message, not repeated on the card)
    'extras' => [],         // Product-level inclusions merged into "What's included"
    'note' => null,         // Short product-level note under the inclusions
    'setup' => null,        // Setup line (from product page config), listed first under "What's included"
    'support' => null,      // Support level (from product page config)
    'toggle' => true,       // Whether the monthly/yearly toggle drives this card
    'quoteUrl' => null,     // Optional "Request quote" destination for quote-based plans (e.g. Business Automation form)
])

{{--
    Individual plan card (used inside a product's plan group). Every value comes from the database:
    name/tagline/features/comparison from `plans`, prices from `plan_prices`, resources from `resource_profiles`.
    Pricing caveats are NOT repeated here — the surrounding group shows one pricing explanation.
    The card is a 5-row subgrid (head · price · specs · included · actions) so rows line up across a row of cards.
--}}
@php
    $comingSoon = $plan->isComingSoon();
    $monthlyMinor = $plan->priceFor('monthly');
    $yearlyMinor = $plan->priceFor('yearly');
    $priced = ! $comingSoon && $plan->hasPublishedPrice();
    $quote = ! $comingSoon && ($plan->fulfilment === 'quote' || $plan->highlight === 'Quote on request');
    $monthly = intdiv($monthlyMinor ?? 0, 100);
    $annualTotal = intdiv($yearlyMinor ?? 0, 100);
    $annualMonthly = (int) round($annualTotal / 12);
    $hasYearly = $priced && $annualTotal > 0;
    $profile = $plan->resourceProfile;
    $isVps = $plan->kind === 'hosting';
    $pendingCopy = str_starts_with((string) $plan->highlight, 'Pricing') || $plan->highlight === 'Quote on request';
    $capacity = $isVps && ! $profile && $plan->highlight && ! $pendingCopy ? $plan->highlight : null;
    $managed = $plan->access_model !== 'unmanaged';

    // Specification rows: [icon, label, value|null]. null renders "To be confirmed".
    $specs = [];
    if ($isVps && $capacity) {
        $specs[] = ['workflow', 'Capacity', $capacity];
        $specs[] = ['server', 'VPS size', 'Confirmed before purchase'];
    } elseif ($isVps) {
        $specs[] = ['cpu', 'CPU', $profile?->vcpu ? $profile->vcpu.' vCPU' : null];
        $specs[] = ['memory', 'RAM', $profile?->ram_gb ? $profile->ram_gb.' GB' : null];
        $specs[] = ['disk', 'Storage', $profile?->disk_gb ? trim($profile->disk_gb.' GB '.($profile->disk_type ?: '')) : null];
        if ($profile?->bandwidth_tb) {
            $specs[] = ['network', 'Bandwidth', $profile->bandwidth_tb.' TB / month'];
        }
    } else {
        foreach (array_slice((array) $plan->comparison, 0, 5, true) as $label => $value) {
            $specs[] = [null, $label, $value !== '' && $value !== 'To be confirmed' ? $value : null];
        }
    }
    if ($isVps) {
        $specs[] = [$managed ? 'shield' : 'key', 'Access', $managed ? 'Managed' : 'SSH / root'];
    }

    // Features that only restate a spec row (e.g. "80 GB NVMe storage") are not repeated under "What's included".
    $specNeedles = $isVps ? array_filter([
        $profile?->disk_gb ? $profile->disk_gb.' GB' : null,
        $profile?->bandwidth_tb ? $profile->bandwidth_tb.' TB' : null,
        $profile?->vcpu ? $profile->vcpu.' vCPU' : null,
        $profile?->ram_gb ? $profile->ram_gb.' GB RAM' : null,
    ]) : [];
    $features = array_filter((array) $plan->features, fn ($f) => ! collect($specNeedles)->contains(fn ($n) => str_starts_with($f, $n)));
    // The product-level setup line is shown only when the product has no more specific inclusions of its own.
    $included = array_values(array_unique(array_filter(array_merge($extras ? [] : [$setup], $features, $extras))));
    $sales = 'https://wa.me/917688855600?text='.rawurlencode('Hi DeveloperJet, I would like to know more about the '.$product.' '.$plan->name.' plan.');
    $requestUrl = $comingSoon && config('service_forms.'.$plan->kind) ? route('services.request.create', $plan) : null;
@endphp

<article class="dj-plan" aria-labelledby="plan-{{ $plan->slug }}"
    @if ($priced && $toggle && $hasYearly) data-plan-card data-annual-total="₹{{ number_format($annualTotal) }}" @endif>

    <header class="dj-plan__head">
        <h3 class="dj-plan__name" id="plan-{{ $plan->slug }}">{{ $plan->name }}</h3>
        @if ($plan->tagline)<p class="dj-plan__desc">{{ $plan->tagline }}</p>@endif
    </header>

    <div class="dj-plan__price">
        @if ($priced)
            <div class="dj-plan__amount">
                <span class="price-amount" data-monthly="₹{{ number_format($monthly) }}" data-annual="₹{{ number_format($annualMonthly) }}">{{ \App\Support\Money::inr($monthlyMinor) }}</span>
                <span class="dj-plan__per">/ month</span>
                @unless ($plan->isApproved())<span class="dj-plan__tag" title="Confirmed with you before you order">Indicative</span>@endunless
            </div>
            @if ($hasYearly && $toggle)
                @if ($annualMonthly !== $monthly)
                    <span class="dj-plan__compare" data-monthly-compare hidden>₹{{ number_format($monthly) }} / month on monthly billing</span>
                @endif
                <p class="dj-plan__billing" data-billing-note>Billed monthly</p>
                <p class="dj-plan__annual" data-annual-total-note hidden>₹{{ number_format($annualTotal) }} billed for 12 months</p>
                @if (($monthly * 12) - $annualTotal > 0)
                    <p class="dj-plan__save" data-annual-savings-note hidden>Save ₹{{ number_format(($monthly * 12) - $annualTotal) }} compared with monthly billing</p>
                @endif
            @else
                <p class="dj-plan__billing">Billed monthly{{ $hasYearly ? ' · yearly available' : '' }}</p>
            @endif
            @if (($plan->setup_fee_minor ?? 0) > 0)
                <p class="dj-plan__billing">+ {{ \App\Support\Money::inr($plan->setup_fee_minor) }} one-time setup</p>
            @endif
        @elseif ($quote)
            <p class="dj-plan__amount--pending">Quote on request</p>
            <p class="dj-plan__billing">Agreed with you before work starts</p>
        @else
            <p class="dj-plan__amount--pending plans-price-pending">Pricing to be confirmed</p>
            <p class="dj-plan__billing">{{ $comingSoon ? 'Coming soon · not open for orders' : 'Not open for orders yet' }}</p>
        @endif
    </div>

    <dl class="dj-plan__specs">
        @foreach ($specs as [$icon, $label, $value])
            <div><dt>@if ($icon)<x-icon :name="$icon" :size="16" /> @endif{{ $label }}</dt><dd @unless ($value) class="is-tbc" @endunless>{{ $value ?? 'To be confirmed' }}</dd></div>
        @endforeach
    </dl>

    <div class="dj-plan__body">
        @if ($included)
            <p class="dj-plan__label">{{ $comingSoon ? 'Planned' : "What's included" }}</p>
            <ul class="dj-plan__features">
                @foreach ($included as $item)
                    <li><x-icon name="check" :size="16" />{{ $item }}</li>
                @endforeach
            </ul>
        @endif
        @if ($note)<p class="dj-plan__note">{{ $note }}</p>@endif
        @if ($support)<p class="dj-plan__support"><x-icon name="headset" :size="16" /> <span><strong>Support:</strong> {{ $support }}</span></p>@endif
    </div>

    <div class="dj-plan__actions">
        @if ($plan->canBePurchased())
            <form method="POST" action="{{ route('cart.store') }}" class="dj-plan__form" data-loading-form>
                @csrf
                <input type="hidden" name="plan" value="{{ $plan->slug }}">
                @php $cartIntervals = $plan->cartIntervals(); @endphp
                @if (array_diff($cartIntervals, ['monthly', 'yearly']))
                    <label class="dj-plan__period">Billing period
                        <select name="billing_interval">
                            @foreach ($cartIntervals as $interval)
                                <option value="{{ $interval }}">{{ \App\Support\BillingCycle::label($interval) }} — {{ \App\Support\Money::inr($plan->listPrice($interval)) }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <input type="hidden" name="billing_interval" value="monthly" data-billing-input>
                @endif
                <button class="plan-add-to-cart" type="submit"><span class="btn-label">Add to cart</span><span class="btn-spinner" aria-hidden="true"></span></button>
            </form>
            <a class="plan-get-started" href="{{ $sales }}" target="_blank" rel="noopener noreferrer">Talk to Sales <x-icon name="arrow-right" :size="16" /></a>
        @elseif ($requestUrl)
            <a class="plan-request" href="{{ $requestUrl }}">Request access <x-icon name="arrow-right" :size="16" /></a>
            <a class="plan-get-started" href="{{ route('contact') }}">Contact Sales</a>
        @elseif ($quoteUrl)
            <a class="plan-request" href="{{ $quoteUrl }}">Request Quote <x-icon name="arrow-right" :size="16" /></a>
            <a class="plan-get-started" href="{{ $sales }}" target="_blank" rel="noopener noreferrer">Contact Sales</a>
        @else
            <a class="plan-get-started is-primary" href="{{ $sales }}" target="_blank" rel="noopener noreferrer">{{ $priced ? 'Get started' : 'Contact Sales' }} <x-icon name="arrow-right" :size="16" /></a>
        @endif
    </div>
</article>
