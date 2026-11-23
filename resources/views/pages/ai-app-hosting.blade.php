@extends('layouts.app')

@section('title', 'AI Application Hosting — custom requirements')
@section('meta_description', 'DeveloperJet AI Application Hosting: hosting for your own AI application, scoped with our team around your stack and access needs. No fixed plans — quoted per requirement.')

@section('content')
    @php
        $points = [
            ['cpu', 'Sized around your application', 'We agree the server, runtime and storage your application needs instead of fitting it into a fixed plan.'],
            ['shield', 'Access agreed up front', 'Managed (we look after the server) or unmanaged (you get SSH/root) — decided with you and written into the quotation.'],
            ['key', 'Your models, your keys', 'Your application uses your own AI provider accounts and API keys. We never resell model usage.'],
            ['headset', 'Clear support scope', 'Support covers the hosting we agree to provide. Your application code stays your responsibility unless we quote otherwise.'],
        ];
    @endphp

    <section class="dj-page-hero" aria-labelledby="aih-title">
        <div class="dj-container">
            <div class="dj-page-hero__inner">
                <p class="dj-eyebrow">Developer Hosting · Custom scope</p>
                <h1 id="aih-title">AI Application Hosting</h1>
                <p class="dj-lede">Hosting for an AI application you have built or are building. There are no fixed plans: tell us about your stack and we scope the server, access model and support with you.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="https://wa.me/917688855600?text={{ rawurlencode('Hi DeveloperJet, I would like to discuss AI Application Hosting.') }}" target="_blank" rel="noopener noreferrer">Talk to Sales <x-icon name="arrow-right" :size="18" /></a>
                    <a class="dj-btn dj-btn--secondary dj-btn--lg" href="{{ route('product.show', 'docker-vps') }}">Prefer full control? Docker VPS Hosting</a>
                </div>
            </div>
        </div>
    </section>

    <section class="dj-section" aria-labelledby="aih-how">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">What to expect</p>
                <h2 id="aih-how">Scoped with you, quoted before you commit</h2>
                <p class="dj-section-head__text">Pricing, resources and access are confirmed in writing before anything is ordered. Nothing on this page is a fixed offer.</p>
            </div>
            <div class="dj-cap-grid">
                @foreach ($points as [$icon, $title, $text])
                    <article class="dj-cap">
                        <div class="dj-cap__top"><span class="dj-cap__icon"><x-icon :name="$icon" :size="20" /></span></div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <x-cta variant="hosting" eyebrow="AI Application Hosting" title="Have an AI application to host?" text="Share your stack and expected usage. We reply with a proposed setup and quotation." :primary-url="route('contact')" primary-label="Contact Sales" :secondary-url="route('compare.index')" secondary-label="Compare products" />
@endsection
