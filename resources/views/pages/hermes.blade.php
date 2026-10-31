@extends('layouts.app')

@section('title', 'Hermes CLI plans')
@section('meta_description', 'Get Hermes CLI installed and configured on a VPS, then connect and use it over SSH or PuTTY.')

@section('content')
    @php
        $hermesPlans = [
            ['name' => 'Launch', 'description' => 'A capable home for a personal Hermes setup.', 'monthly' => 2499, 'annual_total' => 24990, 'annual_monthly' => 2083, 'highlight' => '2 vCPU · 4 GB RAM', 'features' => ['80 GB NVMe storage', '4 TB monthly bandwidth', 'Hermes setup guidance'], 'featured' => false],
            ['name' => 'Grow', 'description' => 'More room for regular workloads and projects.', 'monthly' => 4999, 'annual_total' => 49990, 'annual_monthly' => 4166, 'highlight' => '4 vCPU · 8 GB RAM', 'features' => ['160 GB NVMe storage', '8 TB monthly bandwidth', 'Hermes setup guidance'], 'featured' => true],
            ['name' => 'Scale', 'description' => 'Extra headroom for heavier workloads.', 'monthly' => 9999, 'annual_total' => 99990, 'annual_monthly' => 8333, 'highlight' => '8 vCPU · 16 GB RAM', 'features' => ['320 GB NVMe storage', '16 TB monthly bandwidth', 'Hermes setup guidance'], 'featured' => false],
        ];
    @endphp

    <section class="product-page-hero section-shell hermes-page-hero">
        <div class="product-page-copy">
            <span class="eyebrow"><span class="eyebrow-spark">⌘</span> HERMES CLI, INSTALLED FOR YOU</span>
            <h1>Your CLI setup.<br><span>Ready to connect.</span></h1>
            <p>We install and configure Hermes CLI on your VPS. Connect to your server through SSH or PuTTY and start using your setup; choose a plan based on the resources you need.</p>
            <div class="hero-actions">
                <a class="button-primary" href="#pricing">Explore CLI plans <span aria-hidden="true">↗</span></a>
                <a class="button-secondary" href="{{ route('hermes.workspace') }}">See Hermes Workspace</a>
            </div>
            <div class="product-hero-proof"><span>HERMES CLI INSTALLED</span><i></i><span>SSH OR PUTTY ACCESS</span><i></i><span>SETUP INCLUDED</span></div>
        </div>
        <div class="product-hero-art hermes-hero-art" aria-hidden="true">
            <div class="product-art-orbit"></div><div class="product-art-glow"></div>
            <div class="product-terminal">
                <div class="terminal-top"><span class="terminal-dots"><i></i><i></i><i></i></span><span>hermes · cli</span><span>⌑</span></div>
                <div class="terminal-body">
                    <p><span class="terminal-prompt">➜</span> ssh your-vps</p>
                    <p class="terminal-muted">Connecting to your server...</p>
                    <p><span class="terminal-check">✓</span> Hermes CLI installed</p>
                    <p><span class="terminal-check">✓</span> Setup configured</p>
                    <div class="terminal-ready"><span></span> Ready to use</div>
                </div>
            </div>
            <div class="product-art-tag hermes-art-tag"><span>SSH OR PUTTY</span><strong>CONNECT & BUILD ✳</strong></div>
        </div>
    </section>

    <section class="product-benefits section-shell">
        <div class="section-heading simple-heading">
            <div><span class="eyebrow">Installed and configured for you</span><h2>Connect in.<br><span>Get to work.</span></h2></div>
            <p>We prepare the VPS and install Hermes CLI, so you can connect remotely and use the command line from your own machine.</p>
        </div>
        <div class="product-benefit-grid">
            <article class="product-benefit-card">
                <span class="benefit-number">01 / INSTALLED</span><span class="benefit-icon">⌘</span>
                <h3>Hermes CLI setup included</h3><p>We install and configure Hermes CLI on the VPS you choose, then help you get started.</p>
            </article>
            <article class="product-benefit-card">
                <span class="benefit-number">02 / CONNECT YOUR WAY</span><span class="benefit-icon">▦</span>
                <h3>SSH or PuTTY</h3><p>Connect to the configured VPS remotely using an SSH client or PuTTY and use Hermes CLI in your terminal.</p>
            </article>
            <article class="product-benefit-card">
                <span class="benefit-number">03 / VPS RESOURCES</span><span class="benefit-icon">☺</span>
                <h3>Choose your configuration</h3><p>Compare CPU, RAM, NVMe storage, and monthly bandwidth, then confirm availability with Sales.</p>
            </article>
        </div>
    </section>

    <section class="product-pricing section-shell" id="pricing">
        <div class="section-heading">
            <div><span class="eyebrow">Hermes CLI · India</span><h2>Pick your resources.<br><span>Build from there.</span></h2></div>
            <div class="section-heading-aside">
                <p>Illustrative INR pricing. Final configuration and price are confirmed with Sales.</p>
                <div class="billing-switch" role="group" aria-label="Choose billing period">
                    <button type="button" class="billing-option is-active" data-billing-toggle="monthly" aria-pressed="true">Monthly</button>
                    <button type="button" class="billing-option" data-billing-toggle="annual" aria-pressed="false">Yearly <span>save 17%</span></button>
                </div>
            </div>
        </div>
        <div class="product-group-heading">
            <div class="product-icon product-icon-hermes">⌘</div>
            <div><h3>Hermes CLI on VPS</h3><p>Hermes installed and configured, ready for SSH or PuTTY access.</p></div>
            <span class="product-group-label">3 configurations</span>
        </div>
        <div class="plan-grid">
            @foreach ($hermesPlans as $plan)
                <x-plan-card :product="'Hermes CLI'" :name="$plan['name']" :description="$plan['description']" :monthly="$plan['monthly']" :annual-total="$plan['annual_total']" :annual-monthly="$plan['annual_monthly']" :highlight="$plan['highlight']" :plan-note="'Hermes CLI install and setup included. Confirm final configuration and pricing with Sales.'" :features="array_merge($plan['features'], ['Hermes CLI installed and configured', 'Connect using SSH or PuTTY'])" :featured="$plan['featured']" />
            @endforeach
        </div>
        <p class="pricing-disclaimer">Illustrative INR pricing for Hermes CLI installed on a VPS. Billing term, server location, actual resource availability, final inclusions, and applicable taxes are confirmed with Sales before purchase.</p>
    </section>

    <section class="product-faq section-shell">
        <div class="faq-heading">
            <span class="eyebrow">Hermes CLI, explained</span><h2>Good to<br><span>know.</span></h2>
            <p>Want to figure out which configuration fits? Let’s talk.</p><a class="button-secondary" href="{{ route('contact') }}">Talk to Sales</a>
        </div>
        <div class="faq-list">
            <details class="faq-item" open><summary>How do I use Hermes CLI after setup?</summary><p>We install and configure Hermes CLI on your VPS. Connect from your computer using SSH or PuTTY, then use Hermes in the server terminal.</p></details>
            <details class="faq-item"><summary>Can I choose yearly billing?</summary><p>Yes. The yearly switch shows an indicative 12-month total and equivalent monthly rate. Confirm the billing term and final quote with Sales before ordering.</p></details>
            <details class="faq-item"><summary>Need Hermes installed with Docker instead?</summary><p>Hermes Workspace is installed and configured with Docker on a VPS. <a href="{{ route('hermes.workspace') }}">Explore Hermes Workspace plans</a>.</p></details>
            <div class="closing-cta-inner">
                <span class="cta-orbit" aria-hidden="true">✦</span>
                <div><span class="eyebrow">A better setup starts here</span><h2>Ready to use<br><span>Hermes CLI?</span></h2></div>
                <div class="closing-cta-action"><p>Tell us about your workload. We’ll help you compare the proposed configurations.</p><a class="button-light" href="{{ route('contact') }}">Talk to our team <span aria-hidden="true">↗</span></a></div>
            </div>
        </div>
    </section>

    <x-page-details
        eyebrow="Hermes CLI on VPS"
        title="We set it up. You connect and use it."
        description="Choose a resource tier. We install and configure Hermes CLI on the VPS, then you connect from your computer using SSH or PuTTY."
        :items="[
            ['label' => '01 / CHOOSE A PLAN', 'title' => 'Pick your VPS resources', 'description' => 'Compare vCPU, RAM, NVMe storage, and bandwidth across three CLI plans.'],
            ['label' => '02 / WE SET IT UP', 'title' => 'Hermes CLI installed', 'description' => 'We install and configure Hermes CLI on your VPS before handing over connection details.'],
            ['label' => '03 / YOU CONNECT', 'title' => 'Use SSH or PuTTY', 'description' => 'Connect to your VPS remotely and run Hermes CLI from the server terminal.'],
        ]"
        cta-title="Want help choosing a CLI plan?"
        cta-description="Talk to Sales about the VPS resources, billing term, and Hermes CLI setup."
        cta-label="Talk to Sales"
        :cta-url="route('contact')"
    />
@endsection
