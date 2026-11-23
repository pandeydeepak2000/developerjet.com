@extends('layouts.app')

@section('title', 'Compare products')
@section('meta_description', 'Plain-language comparisons of DeveloperJet products: Hermes, OpenClaw, n8n, Claude Code, Docker VPS, managed and unmanaged.')

@section('content')
    <section class="dj-page-hero dj-page-hero--center" aria-labelledby="compare-title">
        <div class="dj-container">
            <div class="dj-page-hero__inner">
                <p class="dj-eyebrow">Compare products</p>
                <h1 id="compare-title">Which product is right for me?</h1>
                <p class="dj-lede">Short, honest comparisons. We describe how each product is delivered and what you are responsible for — nothing more.</p>
            </div>
        </div>
    </section>

    <section class="dj-section" aria-labelledby="compare-list-title">
        <div class="dj-container">
            <h2 id="compare-list-title" class="sr-only">All comparisons</h2>
            <div class="dj-grid-3">
                @foreach ($comparisons as $slug => $c)
                    <a class="dj-linkcard" href="{{ route('compare.show', $slug) }}">
                        <span class="dj-card__icon"><x-icon name="scale" :size="22" /></span>
                        <h3>{{ $c['title'] }}</h3>
                        <p>{{ $c['intro'] }}</p>
                        <span class="dj-cat__cta">Read comparison <x-icon name="arrow-right" :size="16" /></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="dj-section dj-section--surface" aria-labelledby="matrix-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">At a glance</p>
                <h2 id="matrix-title">Every product side by side</h2>
            </div>
            @include('partials.product-matrix', ['keys' => array_keys(config('product_matrix.products')), 'current' => null, 'matrix' => config('product_matrix')])
        </div>
    </section>
    <x-cta variant="hosting" />
@endsection
