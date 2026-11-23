@extends('layouts.app')

@section('title', $def['title'])
@section('meta_description', $def['lead'])

@section('content')
    @php
        $icons = ['wordpress' => 'globe', 'laravel-development' => 'code', 'web-development' => 'browser', 'seo' => 'search'];
        $steps = [
            ['chat', 'Request a quote', 'Tell us about your project using a short requirements form.'],
            ['book', 'Receive a scoped quote', 'We review your requirements and send a quote with scope and timeline.'],
            ['check', 'Accept and start', 'Accepting opens a project with milestones in your dashboard. Nothing is charged automatically.'],
            ['activity', 'Review and approve', 'Approve each milestone or request revisions until it is right.'],
        ];
    @endphp

    <section class="dj-page-hero" aria-labelledby="svc-title">
        <div class="dj-container">
            <div class="dj-page-hero__inner">
                <p class="dj-eyebrow"><span class="dj-eyebrow__icon"><x-icon :name="$icons[$page] ?? 'grid'" :size="16" /></span>Digital services</p>
                <h1 id="svc-title">{{ $def['title'] }}</h1>
                <p class="dj-lede">{{ $def['lead'] }} Every package is quoted from your requirements before any work starts.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="#packages">View packages <x-icon name="arrow-right" :size="18" /></a>
                    <a class="dj-btn dj-btn--secondary dj-btn--lg" href="{{ route('digital-services') }}">All digital services</a>
                </div>
            </div>
        </div>
    </section>

    <section class="dj-section" id="packages" aria-labelledby="pkg-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">Packages</p>
                <h2 id="pkg-title">Choose a starting point</h2>
                <p class="dj-section-head__text">Package names and scope come from our catalogue. Final scope, timeline and price are agreed in your quote.</p>
            </div>
            <div class="dj-plan-grid dj-plan-grid--{{ min(max($plans->count(), 1), 3) }}">
                @foreach ($plans as $plan)
                    {{-- Same five-row structure as x-plan-card: head · price · scope · not included · actions. --}}
                    <article class="dj-plan" aria-labelledby="plan-{{ $plan->slug }}">
                        <header class="dj-plan__head">
                            <h3 class="dj-plan__name" id="plan-{{ $plan->slug }}">{{ $plan->name }}</h3>
                            <p class="dj-plan__desc">{{ $plan->tagline ?? 'Scope and price are confirmed after we review your requirements.' }}</p>
                        </header>
                        <div class="dj-plan__price">
                            @if ($plan->canBePurchased() && $plan->isApproved())
                                <p class="dj-plan__amount"><span class="price-amount">{{ \App\Support\Money::inr($plan->priceFor('monthly')) }}</span></p>
                            @else
                                <p class="dj-plan__amount--pending">Quote on request</p>
                                <p class="dj-plan__billing">Agreed with you before work starts</p>
                            @endif
                        </div>
                        <div class="dj-plan__body">
                            @if ($plan->features)
                                <p class="dj-plan__label">Typical scope</p>
                                <ul class="dj-plan__features">@foreach ($plan->features as $f)<li><x-icon name="check" :size="16" />{{ $f }}</li>@endforeach</ul>
                            @endif
                        </div>
                        <div class="dj-plan__body">
                            @if ($plan->not_included)
                                <p class="dj-plan__label">Not included</p>
                                <ul class="dj-plan__features dj-plan__features--no">@foreach ($plan->not_included as $f)<li><x-icon name="x" :size="16" />{{ $f }}</li>@endforeach</ul>
                            @endif
                        </div>
                        <div class="dj-plan__actions">
                            <a class="plan-request" href="{{ route('services.request.create', $plan) }}">Request a quote</a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($page === 'seo')
                <p class="dj-footnote"><x-icon name="info" :size="16" /> SEO outcomes depend on many factors outside our control. We report only real, connected data and never guarantee rankings or traffic.</p>
            @endif
        </div>
    </section>

    <section class="dj-section dj-section--surface" aria-labelledby="svc-how-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">How it works</p>
                <h2 id="svc-how-title">From requirements to delivery</h2>
            </div>
            <ol class="dj-flow dj-flow--four">
                @foreach ($steps as $i => [$icon, $title, $text])
                    <li class="dj-flow__step">
                        <span class="dj-flow__icon"><x-icon :name="$icon" :size="22" /></span>
                        <span class="dj-flow__num">Step {{ $i + 1 }}</span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <x-cta variant="services" primary-url="#packages" />
@endsection
