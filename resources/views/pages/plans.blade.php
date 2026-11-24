@extends('layouts.app')

@section('title', 'Pricing & all plans')
@section('meta_description', 'All DeveloperJet products and plans: Hermes CLI, Hermes Workspace, n8n, the AI Complete Bundle, OpenClaw, Claude Code, Docker VPS, AI All-Access and digital services.')

@section('content')
    @php
        $productRoutes = \App\Http\Controllers\MarketingController::PRODUCT_ROUTES;
        $serviceRoutes = collect(\App\Http\Controllers\ServicesController::PAGES)->mapWithKeys(fn ($d, $page) => [$d['category'] => ['services.show', $page]])->all();
        $linkFor = function ($slug) use ($productRoutes, $serviceRoutes) {
            $r = $productRoutes[$slug] ?? $serviceRoutes[$slug] ?? null;

            return $r ? route($r[0], $r[1] ?? []) : null;
        };
        $visible = $categories->filter(fn ($c) => $c->plans->isNotEmpty());
    @endphp

    <section class="dj-page-hero dj-page-hero--center" aria-labelledby="plans-title">
        <div class="dj-container">
            <div class="dj-page-hero__inner">
                <p class="dj-eyebrow">Pricing</p>
                <h1 id="plans-title">Products and plans</h1>
                <p class="dj-lede">Start with the product that fits your work, then compare its plans. Only plans with an approved price can be ordered.</p>
                <nav class="dj-chipnav" aria-label="Jump to a product's plans">
                    @foreach ($visible as $category)
                        <a class="dj-chip" href="#cat-{{ $category->slug }}">{{ $category->name }}</a>
                    @endforeach
                </nav>
            </div>
        </div>
    </section>

    <section class="dj-section" aria-labelledby="families-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">All products</p>
                <h2 id="families-title">Which product do you need?</h2>
                <p class="dj-section-head__text">Each product links to its own page with plans, features and answers to common questions.</p>
            </div>
            <div class="dj-family-grid">
                @foreach ($families as $family)
                    <x-product-card :family="$family" />
                @endforeach
            </div>
        </div>
    </section>

    @foreach ($visible as $category)
        @php
            $soon = $category->plans->contains(fn ($p) => $p->isComingSoon());
            $indicative = $category->plans->contains(fn ($p) => ! $p->isComingSoon() && $p->hasPublishedPrice() && ! $p->isApproved());
            $pending = ! $soon && $category->plans->contains(fn ($p) => ! $p->hasPublishedPrice() && $p->fulfilment !== 'quote');
        @endphp
        <section class="dj-section {{ $loop->odd ? 'dj-section--surface' : '' }} dj-plans-group" id="cat-{{ $category->slug }}" aria-labelledby="cat-title-{{ $category->slug }}">
            <div class="dj-container">
                <div class="dj-section-head dj-section-head--split">
                    <div>
                        <p class="dj-eyebrow">{{ $category->plans->count() }} {{ \Illuminate\Support\Str::plural('plan', $category->plans->count()) }}{{ $soon ? ' · coming soon' : '' }}</p>
                        <h2 id="cat-title-{{ $category->slug }}">{{ $category->name }}</h2>
                        @if ($category->description)<p class="dj-section-head__text">{{ $category->description }}</p>@endif
                    </div>
                    @if ($url = $linkFor($category->slug))
                        <a class="dj-link" href="{{ $url }}">Full product details <x-icon name="arrow-right" :size="16" /></a>
                    @endif
                </div>
                @if ($soon)
                    <p class="dj-price-note dj-price-note--soon"><x-icon name="info" :size="18" /> <span><strong>Pricing to be confirmed.</strong> Not open for orders. “Request access” records your interest — nothing is charged.</span></p>
                @elseif ($indicative || $pending)
                    <p class="dj-price-note"><x-icon name="info" :size="18" /> <span>
                        @if ($indicative)Prices marked <strong>Indicative</strong> are confirmed with you before you order.@endif
                        @if ($pending)Plans showing <strong>Pricing to be confirmed</strong> cannot be ordered yet.@endif
                    </span></p>
                @endif
                <x-plan-grid :count="$category->plans->count()" :label="$category->name.' plans'">
                    @foreach ($category->plans as $plan)
                        <x-plan-card :plan="$plan" :product="$category->name" :toggle="false" />
                    @endforeach
                </x-plan-grid>
            </div>
        </section>
    @endforeach
    <x-cta variant="hosting" title="Not sure which plan fits?" text="Compare products side by side, or tell our team what you need and we will recommend a plan." :primary-url="route('compare.index')" primary-label="Compare products" />
@endsection
