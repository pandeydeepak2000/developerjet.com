@extends('layouts.app')

@section('title', $page['title'])
@section('meta_description', $page['summary'])

@section('content')
    @php
        $managed = $page['access_model'] === 'managed'; // legacy; all current products are self-hosted
        $hasYearly = $plans->contains(fn ($p) => $p->hasPublishedPrice() && $p->priceFor('yearly'));
        $anyIndicative = $plans->contains(fn ($p) => $p->hasPublishedPrice() && ! $p->isApproved());
        $anyPending = $plans->contains(fn ($p) => ! $p->hasPublishedPrice());
        $compareKeys = array_values(array_filter($page['compare_with'] ?? [], fn ($k) => isset($matrix['products'][$k])));
        $stepIcons = ['card', 'clock', 'user', 'settings', 'rocket', 'check'];
        $productName = $matrix['products'][$slug]['name'] ?? $page['title'];
        $productIcon = $matrix['products'][$slug]['icon'] ?? 'server';
    @endphp

    {{-- 1. Hero --}}
    <section class="dj-phero" aria-labelledby="product-title">
        <div class="dj-container dj-phero__grid">
            <div class="dj-phero__copy">
                <p class="dj-eyebrow"><span class="dj-eyebrow__icon"><x-icon :name="$productIcon" :size="16" /></span>{{ $page['eyebrow'] }}</p>
                <h1 id="product-title">{{ $page['headline'] ?? $page['title'] }}</h1>
                <p class="dj-lede">{{ $page['summary'] }}</p>
                @if (! empty($page['hero_points']))
                    <ul class="dj-checks">
                        @foreach ($page['hero_points'] as $point)
                            <li><x-icon name="check" :size="18" /> {{ $point }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="#pricing">View {{ $productName }} plans <x-icon name="arrow-right" :size="18" /></a>
                    @if (! empty($page['compare_slug']))
                        <a class="dj-btn dj-btn--secondary dj-btn--lg" href="{{ route('compare.show', $page['compare_slug']) }}">Compare options</a>
                    @endif
                </div>
                <div class="dj-badges">
                    <span class="dj-badge {{ $managed ? 'dj-badge--blue' : 'dj-badge--navy' }}"><x-icon :name="$managed ? 'shield' : 'key'" :size="15" /> {{ $managed ? 'Restricted access' : 'Self-hosted · full root access' }}</span>
                    <span class="dj-badge"><x-icon name="clock" :size="15" /> Prepared by our team after checkout — not instant</span>
                </div>
                @if (! empty($page['included_access']))
                    <p class="dj-access-note" data-included-access><x-icon :name="$managed ? 'shield' : 'key'" :size="16" /> <span><strong>Included access:</strong> {{ $page['included_access'] }}</span></p>
                @endif
            </div>
            <figure class="dj-phero__art">
                @include('illustrations.'.$page['illustration'], ['alt' => $page['illustration_alt'] ?? $page['title']])
            </figure>
        </div>
        <nav class="dj-subnav" aria-label="On this page">
            <div class="dj-container">
                <ul>
                    <li><a class="dj-subnav__link" href="#pricing">Plans</a></li>
                    <li><a class="dj-subnav__link" href="#how">How it works</a></li>
                    <li><a class="dj-subnav__link" href="#features">Features</a></li>
                    <li><a class="dj-subnav__link" href="#specs">Technical details</a></li>
                    <li><a class="dj-subnav__link" href="#included">What's included</a></li>
                    <li><a class="dj-subnav__link" href="#compare">Compare</a></li>
                    <li><a class="dj-subnav__link" href="#faq">FAQ</a></li>
                </ul>
            </div>
        </nav>
    </section>

    {{-- 2. Pricing --}}
    <section class="dj-section dj-section--surface" id="pricing" aria-labelledby="pricing-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Plans &amp; pricing</p>
                    <h2 id="pricing-title">Choose your {{ $productName }} plan</h2>
                    <p class="dj-section-head__text">
                        Every plan is a VPS dedicated to you, set up by our team and handed over with full SSH/root access.
                    </p>
                </div>
                @if ($hasYearly)
                    <div class="dj-toggle" role="group" aria-label="Choose billing period">
                        <button type="button" class="dj-toggle__btn is-active" data-billing-toggle="monthly" aria-pressed="true">Monthly</button>
                        <button type="button" class="dj-toggle__btn" data-billing-toggle="annual" aria-pressed="false">Yearly</button>
                    </div>
                @endif
            </div>

            {{-- One pricing explanation for the whole group; cards carry only a short "Indicative" tag. --}}
            @if ($anyIndicative || $anyPending || ! empty($page['plan_note']))
                <p class="dj-price-note"><x-icon name="info" :size="18" /> <span>
                    @if ($anyIndicative)Prices marked <strong>Indicative</strong> are confirmed with you before you order, together with billing term, server location and taxes.@endif
                    @if ($anyPending)Plans showing <strong>Pricing to be confirmed</strong> cannot be ordered yet.@endif
                    {{ $page['plan_note'] ?? '' }}
                </span></p>
            @endif

            <x-plan-grid :count="$plans->count()" :label="$productName.' plans'">
                @forelse ($plans as $plan)
                    <x-plan-card :plan="$plan" :product="$productName" :extras="$page['plan_extras'] ?? []" :setup="$page['setup'] ?? null" :support="$page['support_scope'] ?? null" />
                @empty
                    <p class="dj-muted">No plans are published yet. <a href="{{ route('contact') }}">Talk to our team</a>.</p>
                @endforelse
            </x-plan-grid>
        </div>
    </section>

    {{-- 3. How it works --}}
    <section class="dj-section" id="how" aria-labelledby="how-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">How it works</p>
                <h2 id="how-title">How deployment works</h2>
                <p class="dj-section-head__text">Five clear steps from choosing a plan to using it. Step 4 is carried out by our team, so delivery is not instant.</p>
            </div>
            @include('partials.customer-flow', ['product' => $productName])
        </div>
    </section>

    {{-- 4. Key features --}}
    <section class="dj-section dj-section--surface" id="features" aria-labelledby="what-title">
        <div class="dj-container">
            <div class="dj-intro">
                <div>
                    <p class="dj-eyebrow">Key features</p>
                    <h2 id="what-title">What is it?</h2>
                </div>
                <p class="dj-intro__text">{{ $page['what_is'] }}</p>
            </div>
            <div class="dj-feature-grid">
                @foreach ([['who_for', 'Who it is for', 'user'], ['can_do', 'What you can do', 'rocket'], ['benefits', 'Benefits', 'spark']] as [$key, $label, $icon])
                    <article class="dj-card">
                        <span class="dj-card__icon"><x-icon :name="$icon" :size="22" /></span>
                        <h3>{{ $label }}</h3>
                        <ul class="dj-list">
                            @foreach ($page[$key] as $item)<li>{{ $item }}</li>@endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 5. Technical capabilities --}}
    <section class="dj-section" id="specs" aria-labelledby="specs-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">Technical details</p>
                <h2 id="specs-title">Technical capabilities</h2>
                <p class="dj-section-head__text">What you need to know before you order: skills, credentials, resources, access, support and renewals.</p>
            </div>
            <div class="dj-spec-layout">
                <div class="dj-spec-grid">
                    <article class="dj-spec"><h3><x-icon name="book" :size="20" /> Technical knowledge</h3><p>{{ $page['knowledge'] }}</p></article>
                    <article class="dj-spec"><h3><x-icon name="key" :size="20" /> AI API subscription</h3><p>{{ $page['api_subscription'] }}</p></article>
                    <article class="dj-spec"><h3><x-icon name="cpu" :size="20" /> Resources</h3><p>{{ $page['resources'] }}</p></article>
                    <article class="dj-spec"><h3><x-icon name="lock" :size="20" /> How you access it</h3><p>{{ $page['access'] }}</p></article>
                    <article class="dj-spec"><h3><x-icon name="headset" :size="20" /> Support limits</h3><ul class="dj-list">@foreach ($page['support_limits'] as $i)<li>{{ $i }}</li>@endforeach</ul></article>
                    <article class="dj-spec"><h3><x-icon name="clock" :size="20" /> Renewals</h3><p>{{ $page['renewals'] }}</p></article>
                </div>
                <aside class="dj-preview" aria-labelledby="preview-title">
                    <p class="dj-preview__tag">Example</p>
                    <h3 id="preview-title">Configuration preview</h3>
                    <dl>
                        @foreach ($page['config_preview'] as [$k, $v])
                            <div><dt>{{ $k }}</dt><dd>{{ $v }}</dd></div>
                        @endforeach
                    </dl>
                </aside>
            </div>
        </div>
    </section>

    {{-- 6. Included / not included --}}
    <section class="dj-section dj-section--surface" id="included" aria-labelledby="included-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">Scope</p>
                <h2 id="included-title">Exactly what you are buying</h2>
            </div>
            <div class="dj-scope">
                <article class="dj-scope__col dj-scope__col--yes">
                    <h3><x-icon name="check" :size="22" /> What is included</h3>
                    <ul>@foreach ($page['included'] as $i)<li><x-icon name="check" :size="18" /> {{ $i }}</li>@endforeach</ul>
                </article>
                <article class="dj-scope__col dj-scope__col--no">
                    <h3><x-icon name="x" :size="22" /> What is NOT included</h3>
                    <ul>@foreach ($page['not_included'] as $i)<li><x-icon name="x" :size="18" /> {{ $i }}</li>@endforeach</ul>
                </article>
            </div>
        </div>
    </section>

    {{-- 7. Comparison --}}
    <section class="dj-section" id="compare" aria-labelledby="compare-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Compare</p>
                    <h2 id="compare-title">How {{ $productName }} compares</h2>
                    <p class="dj-section-head__text">Pick the product that matches how you work. Every option runs on a VPS dedicated to you.</p>
                </div>
                <div class="dj-actions">
                    @if (! empty($page['compare_slug']))
                        <a class="dj-btn dj-btn--secondary" href="{{ route('compare.show', $page['compare_slug']) }}">Detailed comparison</a>
                    @endif
                    <a class="dj-link" href="{{ route('compare.index') }}">All comparisons <x-icon name="arrow-right" :size="16" /></a>
                </div>
            </div>
            @include('partials.product-matrix', ['keys' => $compareKeys, 'current' => $slug, 'matrix' => $matrix])
        </div>
    </section>

    {{-- 8. FAQ --}}
    <section class="dj-section dj-section--surface" id="faq" aria-labelledby="faq-title">
        <div class="dj-container dj-faq-layout">
            <div class="dj-faq-layout__aside">
                <p class="dj-eyebrow">FAQ</p>
                <h2 id="faq-title">Frequently asked questions</h2>
                <p class="dj-section-head__text">Still deciding? Our team can help you choose a plan and explain exactly how delivery works.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--secondary" href="{{ route('contact') }}"><x-icon name="headset" :size="18" /> Contact support</a>
                    <a class="dj-link" href="{{ route('docs.index') }}">Read the documentation <x-icon name="arrow-right" :size="16" /></a>
                </div>
            </div>
            <div>
                <div class="dj-faq">
                    @foreach ($page['faq'] as [$q, $a])
                        <details class="dj-faq__item" @if ($loop->first) open @endif>
                            <summary>{{ $q }} <x-icon name="chevron-down" :size="20" /></summary>
                            <p>{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
                <p class="dj-footnote">Statements marked “Draft” are being finalised and are confirmed with you before you order.</p>
            </div>
        </div>
    </section>

    {{-- 9. Final CTA --}}
    <x-cta variant="hosting" :title="$page['cta_title'] ?? null" :text="$page['cta_text'] ?? null" primary-url="#pricing" />
@endsection
