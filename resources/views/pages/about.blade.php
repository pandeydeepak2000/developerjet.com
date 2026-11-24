@extends('layouts.app')

@section('title', 'About DeveloperJet')
@section('meta_description', 'DeveloperJet, powered by AmbitionHost Pvt. Ltd., runs AI agents, automation and developer tools on dedicated VPS servers with setup done for you and clear billing.')

@section('content')
    <section class="dj-phero" aria-labelledby="about-title">
        <div class="dj-container dj-phero__grid">
            <div class="dj-phero__copy">
                <p class="dj-eyebrow">About DeveloperJet</p>
                <h1 id="about-title">Infrastructure for people who build with AI.</h1>
                <p class="dj-lede">DeveloperJet makes the practical parts of running AI and developer tools lighter: choosing the right setup, getting a server prepared, and finding help when you need it. DeveloperJet is powered by AmbitionHost Pvt. Ltd.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="{{ route('plans') }}">View plans <x-icon name="arrow-right" :size="18" /></a>
                    <a class="dj-btn dj-btn--secondary dj-btn--lg" href="{{ route('contact') }}">Contact us</a>
                </div>
            </div>
            <figure class="dj-phero__art">
                @include('illustrations.home-hero')
            </figure>
        </div>
    </section>

    <section class="dj-section dj-section--surface" aria-labelledby="pov-title">
        <div class="dj-container dj-intro">
            <div>
                <p class="dj-eyebrow">Our point of view</p>
                <h2 id="pov-title">More building. Less busywork.</h2>
            </div>
            <div class="dj-prose">
                <p class="dj-intro__text">The best technology does not ask for all your attention. It gives some of it back.</p>
                <p>We bring together useful developer products and a done-for-you setup: careful server preparation, clear plan choices and people you can actually reach.</p>
                <p>We work for solo builders finding their rhythm, teams connecting their workflows, and anyone who would rather spend the afternoon shipping than sorting out infrastructure.</p>
            </div>
        </div>
    </section>

    <section class="dj-section" aria-labelledby="principles-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">How we work</p>
                <h2 id="principles-title">Principles we hold ourselves to</h2>
            </div>
            <div class="dj-grid-3">
                <article class="dj-card"><span class="dj-card__icon"><x-icon name="info" :size="22" /></span><h3>No mystery steps</h3><p class="dj-muted">We tell you exactly what is delivered, what is manual today and what you are responsible for.</p></article>
                <article class="dj-card"><span class="dj-card__icon"><x-icon name="shield" :size="22" /></span><h3>Secure by default</h3><p class="dj-muted">A dedicated server per customer, verified payments and secrets that are never emailed or shown in plain text.</p></article>
                <article class="dj-card"><span class="dj-card__icon"><x-icon name="headset" :size="22" /></span><h3>Real support</h3><p class="dj-muted">We listen first, then help you find an answer that fits — with honest statements of what support covers.</p></article>
            </div>
        </div>
    </section>

    <x-page-details
        eyebrow="Products, set up by DeveloperJet"
        title="Practical tools for modern teams"
        description="We make it easier to choose useful software and get a VPS prepared for it."
        :items="[
            ['label' => 'AI HOSTING', 'title' => 'Hermes, OpenClaw and n8n', 'description' => 'AI agents and workflow automation installed on a VPS dedicated to you.'],
            ['label' => 'DEVELOPER HOSTING', 'title' => 'Claude Code and Docker', 'description' => 'A server ready for Claude Code, or a Docker VPS — both with full SSH/root access.'],
            ['label' => 'DIGITAL SERVICES', 'title' => 'Web, Laravel and SEO', 'description' => 'Project-based services, quoted from your requirements before any work starts.'],
        ]"
        cta-title="Want to learn more about a product?"
        cta-description="Compare the plans and choose what fits the way you work."
        cta-label="Explore products"
        :cta-url="route('compare.index')"
    />
@endsection
