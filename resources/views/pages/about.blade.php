@extends('layouts.app')

@section('title', 'About DeveloperJet')
@section('meta_description', 'Learn how DeveloperJet helps developers spend less time on setup and more time building.')

@section('content')
    <section class="inner-hero section-shell">
        <div class="inner-hero-copy">
            <span class="eyebrow">A little about us</span>
            <h1>We believe good tools should feel like <span>good company.</span></h1>
            <p>DeveloperJet exists to make the practical parts of building feel lighter: getting set up, staying organized, and finding help when you need it.</p>
        </div>
        <div class="inner-hero-art about-art" aria-hidden="true">
            <div class="about-sun"></div><div class="about-card about-card-back">GOOD TOOLS<br><strong>GOOD PEOPLE</strong></div>
            <div class="about-card about-card-front"><span>THE JET WAY</span><strong>Make it<br>feel easy.</strong><i>✳</i></div>
            <span class="art-caption">LESS FRICTION, MORE FLOW</span>
        </div>
    </section>

    <section class="story-section section-shell">
        <div class="story-lead">
            <span class="eyebrow">Our point of view</span>
            <h2>More building.<br><span>Less busywork.</span></h2>
        </div>
        <div class="story-copy">
            <p class="story-highlight">The best technology doesn’t ask for all your attention. It gives some of it back.</p>
            <p>We bring together useful developer products and a friendly, managed experience. That means thoughtful setup, clear plan choices, and people you can actually reach.</p>
            <p>We’re here for solo makers finding their rhythm, teams connecting their workflow, and anyone who would rather spend the afternoon shipping than sorting out infrastructure.</p>
        </div>
    </section>

    <section class="principles-section section-shell">
        <div class="section-heading simple-heading">
            <div><span class="eyebrow">The way we work</span><h2>Small details.<br><span>Big difference.</span></h2></div>
            <p>Helpful by design, from your first question to your next big release.</p>
        </div>
        <div class="principles-grid">
            <article class="principle-card"><span>01 / CLARITY</span><h3>No mystery steps</h3><p>We keep the choices understandable and tell you what to expect.</p></article>
            <article class="principle-card"><span>02 / CARE</span><h3>Real support</h3><p>We listen first, then help you find an answer that fits.</p></article>
            <article class="principle-card"><span>03 / MOMENTUM</span><h3>Built for doing</h3><p>Less overhead means more room for the work you came here to do.</p></article>
        </div>
        <div class="inline-cta">
            <div><strong>Curious what could fit your workflow?</strong><span>We’d love to hear what you’re building.</span></div>
            <a class="button-primary" href="{{ route('contact') }}">Say hello <span aria-hidden="true">↗</span></a>
        </div>
    </section>

    <x-page-details
        eyebrow="Products, managed by DeveloperJet"
        title="Practical tools for modern teams."
        description="We make it easier to choose useful software and get a VPS configured for your needs."
        :items="[
            ['label' => 'HERMES CLI', 'title' => 'CLI installed on VPS', 'description' => 'We install Hermes CLI and configure a VPS for you to connect through SSH or PuTTY.'],
            ['label' => 'HERMES WORKSPACE', 'title' => 'Docker-based Workspace', 'description' => 'We install Hermes with Docker, configure it on a VPS, and hand over your Workspace setup.'],
            ['label' => 'AUTOMATION VPS', 'title' => 'n8n on a VPS', 'description' => 'Managed plans for n8n installed on a VPS configured for your plan, with details confirmed before purchase.'],
        ]"
        cta-title="Want to learn more about a product?"
        cta-description="Compare the plans and choose what fits the way you work."
        cta-label="Explore products"
        :cta-url="route('hermes')"
    />
@endsection
