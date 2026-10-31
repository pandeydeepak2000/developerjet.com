@extends('layouts.app')

@section('title', 'Hermes Workspace plans')
@section('meta_description', 'Choose a Hermes Workspace VPS plan. We install Hermes with Docker, configure the server, and hand over your Workspace setup.')

@section('content')
    @php
        $workspacePlans = [
            ['name' => 'Launch', 'description' => 'A capable start for your Hermes Workspace.', 'monthly' => 2499, 'annual_total' => 24990, 'annual_monthly' => 2083, 'highlight' => '2 vCPU · 4 GB RAM', 'features' => ['80 GB NVMe storage', '4 TB monthly bandwidth'], 'featured' => false],
            ['name' => 'Grow', 'description' => 'More room for your team and projects.', 'monthly' => 4999, 'annual_total' => 49990, 'annual_monthly' => 4166, 'highlight' => '4 vCPU · 8 GB RAM', 'features' => ['160 GB NVMe storage', '8 TB monthly bandwidth'], 'featured' => true],
            ['name' => 'Scale', 'description' => 'Extra capacity for a larger Workspace.', 'monthly' => 9999, 'annual_total' => 99990, 'annual_monthly' => 8333, 'highlight' => '8 vCPU · 16 GB RAM', 'features' => ['320 GB NVMe storage', '16 TB monthly bandwidth'], 'featured' => false],
        ];
    @endphp

    <section class="product-page-hero section-shell hermes-page-hero">
        <div class="product-page-copy">
            <span class="eyebrow"><span class="eyebrow-spark">▦</span> HERMES WORKSPACE, INSTALLED WITH DOCKER</span>
            <h1>Your Workspace.<br><span>Ready on VPS.</span></h1>
            <p>We install Hermes with Docker, configure it on a VPS, and hand over your Workspace setup. Pick a resource tier and our team will confirm the final configuration with you.</p>
            <div class="hero-actions">
                <a class="button-primary" href="#pricing">Explore Workspace plans <span aria-hidden="true">↗</span></a>
                <a class="button-secondary" href="{{ route('hermes.cli') }}">See Hermes CLI</a>
            </div>
            <div class="product-hero-proof"><span>DOCKER INSTALLATION</span><i></i><span>VPS CONFIGURATION</span><i></i><span>SETUP INCLUDED</span></div>
        </div>
        <div class="product-hero-art hermes-hero-art" aria-hidden="true">
            <div class="product-art-orbit"></div><div class="product-art-glow"></div>
            <div class="product-terminal">
                <div class="terminal-top"><span class="terminal-dots"><i></i><i></i><i></i></span><span>hermes · workspace</span><span>⌑</span></div>
                <div class="terminal-body">
                    <p><span class="terminal-prompt">➜</span> docker compose up</p>
                    <p class="terminal-muted">Preparing your Workspace...</p>
                    <p><span class="terminal-check">✓</span> Hermes installed</p>
                    <p><span class="terminal-check">✓</span> VPS configured</p>
                    <div class="terminal-ready"><span></span> Workspace is ready</div>
                </div>
            </div>
            <div class="product-art-tag hermes-art-tag"><span>HERMES WORKSPACE</span><strong>DOCKER READY ✳</strong></div>
        </div>
    </section>

    <section class="product-benefits section-shell">
        <div class="section-heading simple-heading">
            <div><span class="eyebrow">Workspace setup, handled</span><h2>Docker on VPS.<br><span>Configured for you.</span></h2></div>
            <p>Choose from three proposed VPS resource tiers. We install Hermes with Docker and configure the Workspace on your server.</p>
        </div>
        <div class="product-benefit-grid">
            <article class="product-benefit-card"><span class="benefit-number">01 / DOCKER</span><span class="benefit-icon">▦</span><h3>Hermes installed with Docker</h3><p>We set up Hermes using Docker on the VPS selected for your Workspace plan.</p></article>
            <article class="product-benefit-card"><span class="benefit-number">02 / CONFIGURED VPS</span><span class="benefit-icon">↗</span><h3>Server setup included</h3><p>We configure the VPS and Workspace, then hand over the setup details to you.</p></article>
            <article class="product-benefit-card"><span class="benefit-number">03 / CHOOSE YOUR RESOURCES</span><span class="benefit-icon">☺</span><h3>Three resource tiers</h3><p>Compare CPU, RAM, NVMe storage, and monthly bandwidth before talking to Sales.</p></article>
        </div>
    </section>

    <section class="product-pricing section-shell" id="pricing">
        <div class="section-heading">
            <div><span class="eyebrow">Hermes Workspace · India</span><h2>Choose your server.<br><span>We’ll set it up.</span></h2></div>
            <div class="section-heading-aside">
                <p>Illustrative INR pricing. Final configuration and price are confirmed with Sales.</p>
                <div class="billing-switch" role="group" aria-label="Choose billing period">
                    <button type="button" class="billing-option is-active" data-billing-toggle="monthly" aria-pressed="true">Monthly</button>
                    <button type="button" class="billing-option" data-billing-toggle="annual" aria-pressed="false">Yearly <span>save 17%</span></button>
                </div>
            </div>
        </div>
        <div class="product-group-heading">
            <div class="product-icon product-icon-hermes">▦</div>
            <div><h3>Hermes Workspace on VPS</h3><p>Hermes installed with Docker and configured on your VPS.</p></div>
            <span class="product-group-label">3 configurations</span>
        </div>
        <div class="plan-grid">
            @foreach ($workspacePlans as $plan)
                <x-plan-card :product="'Hermes Workspace'" :name="$plan['name']" :description="$plan['description']" :monthly="$plan['monthly']" :annual-total="$plan['annual_total']" :annual-monthly="$plan['annual_monthly']" :highlight="$plan['highlight']" :plan-note="'Hermes Docker install and Workspace setup included. Confirm final configuration and pricing with Sales.'" :features="array_merge($plan['features'], ['Hermes installed with Docker', 'VPS and Workspace configured'])" :featured="$plan['featured']" />
            @endforeach
        </div>
        <p class="pricing-disclaimer">Illustrative INR pricing for proposed Hermes Workspace VPS configurations. Billing term, server location, actual resource availability, final inclusions, and applicable taxes are confirmed with Sales before purchase.</p>
    </section>

    <section class="product-faq section-shell">
        <div class="faq-heading">
            <span class="eyebrow">Hermes Workspace, explained</span><h2>Good to<br><span>know.</span></h2>
            <p>Want to check the setup before choosing a plan?</p><a class="button-secondary" href="{{ route('contact') }}">Talk to Sales</a>
        </div>
        <div class="faq-list">
            <details class="faq-item" open><summary>How is Hermes Workspace installed?</summary><p>We install Hermes with Docker on your VPS, configure the Workspace, and hand over the setup details.</p></details>
            <details class="faq-item"><summary>What is the difference from Hermes CLI?</summary><p>Hermes CLI is installed directly on a VPS and used through an SSH or PuTTY terminal connection. Workspace is installed and configured with Docker on a VPS.</p></details>
            <details class="faq-item"><summary>Are the listed prices final?</summary><p>Prices are illustrative INR amounts. Sales confirms the VPS resources, availability, billing term, final inclusions, and applicable taxes before purchase.</p></details>
            <div class="closing-cta-inner">
                <span class="cta-orbit" aria-hidden="true">✦</span>
                <div><span class="eyebrow">A Workspace ready for your team</span><h2>Let’s set up<br><span>your Hermes.</span></h2></div>
                <div class="closing-cta-action"><p>Tell us what you need from your Workspace VPS.</p><a class="button-light" href="{{ route('contact') }}">Talk to our team <span aria-hidden="true">↗</span></a></div>
            </div>
        </div>
    </section>

    <x-page-details
        eyebrow="Hermes Workspace on VPS"
        title="Docker installation, configured for your plan."
        description="Choose a proposed resource tier. We install Hermes with Docker, configure it on the VPS, and confirm the final setup with you."
        :items="[
            ['label' => '01 / CHOOSE', 'title' => 'Select your VPS resources', 'description' => 'Compare CPU, RAM, NVMe storage, and bandwidth across three Workspace plans.'],
            ['label' => '02 / WE INSTALL', 'title' => 'Hermes with Docker', 'description' => 'We install Hermes with Docker and configure the Workspace on your VPS.'],
            ['label' => '03 / HANDOVER', 'title' => 'Your configured Workspace', 'description' => 'We hand over the setup details after the VPS and Workspace are configured.'],
        ]"
        cta-title="Need help choosing a Workspace plan?"
        cta-description="Talk to Sales about resources, Docker setup, and your requirements."
        cta-label="Talk to Sales"
        :cta-url="route('contact')"
    />
@endsection
