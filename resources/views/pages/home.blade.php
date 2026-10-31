@extends('layouts.app')

@section('title', 'Developer tools, ready when you are')
@section('meta_description', 'Discover DeveloperJet: thoughtful developer tools, managed with care and backed by real people.')

@section('content')
    <section class="hero-section section-shell">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-spark">✳</span> Tools that move at your speed</span>
            <h1>Make room for<br>your <span>best work.</span></h1>
            <p class="hero-lede">Your favorite developer tools, minus the setup spirals. We make the powerful stuff feel refreshingly simple.</p>
            <div class="hero-actions">
                <a class="button-primary" href="#products">Explore products <span aria-hidden="true">↗</span></a>
                <a class="button-secondary" href="{{ route('contact') }}">Talk to a human</a>
            </div>
            <div class="hero-proof">
                <span class="proof-avatars" aria-hidden="true"><i>D</i><i>J</i><i>+</i></span>
                <span>Real people. Thoughtful support.<br><strong>Here when you need us.</strong></span>
            </div>
        </div>
        <div class="hero-visual" aria-label="DeveloperJet toolkit preview">
            <div class="visual-glow"></div>
            <div class="orbit orbit-one"></div>
            <div class="orbit orbit-two"></div>
            <div class="terminal-card">
                <div class="terminal-top">
                    <span class="terminal-dots"><i></i><i></i><i></i></span>
                    <span>your-next-idea · zsh</span>
                    <span class="terminal-lock">⌑</span>
                </div>
                <div class="terminal-body">
                    <p><span class="terminal-prompt">➜</span> <span class="terminal-path">~/project</span> jet start</p>
                    <p class="terminal-muted">Checking your workspace...</p>
                    <p><span class="terminal-check">✓</span> Hermes plan is selected</p>
                    <p><span class="terminal-check">✓</span> Setup is configured</p>
                    <p><span class="terminal-check">✓</span> You’re good to build</p>
                    <div class="terminal-ready"><span></span> All systems feeling good</div>
                </div>
            </div>
            <div class="floating-note note-top"><span class="note-icon note-violet">✳</span><span><strong>Less setup</strong><small>More momentum</small></span></div>
            <div class="floating-note note-bottom"><span class="note-icon note-green">✓</span><span><strong>Human help</strong><small>One call away</small></span></div>
            <div class="hero-stamp"><span>MADE FOR</span><strong>DOERS</strong><span>✦ &nbsp; NOT BUSYWORK</span></div>
        </div>
        <div class="hero-bottom-line"><span>YOUR WORKFLOW, WITH THE WIND AT ITS BACK</span><span>SCROLL TO FIND YOUR FLOW ↓</span></div>
    </section>

    <section class="home-products section-shell" id="products">
        <div class="section-heading">
            <div><span class="eyebrow">Meet your toolkit</span><h2>Pick your next<br><span>good thing.</span></h2></div>
            <p class="home-products-intro">Compare Hermes CLI and Docker-based Workspace plans, plus managed n8n VPS setup. Clear details and a real person whenever you need one.</p>
        </div>
        <div class="home-product-grid">
            <a class="home-product-card home-product-hermes" href="{{ route('hermes') }}">
                <div class="home-product-top"><span class="product-icon product-icon-hermes">⌘</span><span class="product-group-label">CLI + WORKSPACE</span></div>
                <span class="home-product-kicker">TWO HERMES SETUPS · SIX PLANS</span>
                <h3>Hermes</h3>
                <p>Choose CLI installed for SSH or PuTTY access, or Hermes Workspace installed with Docker on a VPS.</p>
                <span class="home-product-cta">Explore Hermes <span aria-hidden="true">↗</span></span>
                <span class="home-product-art hermes-product-art" aria-hidden="true">⌘</span>
            </a>
            <a class="home-product-card home-product-n8n" href="{{ route('n8n') }}">
                <div class="home-product-top"><span class="product-icon product-icon-n8n">↗</span><span class="product-group-label">MANAGED AUTOMATION</span></div>
                <span class="home-product-kicker">FOR ALL THE THINGS THAT CONNECT</span>
                <h3>n8n Managed</h3>
                <p>Put your workflows to work with n8n installed on a managed VPS, helpful setup, and less infrastructure to keep an eye on.</p>
                <span class="home-product-cta">Explore n8n <span aria-hidden="true">↗</span></span>
                <span class="home-product-art n8n-product-art" aria-hidden="true">⤴</span>
            </a>
        </div>
    </section>

    <section class="home-explainer section-shell">
        <div class="section-heading simple-heading">
            <div><span class="eyebrow">What we actually do</span><h2>Products from us.<br><span>Less setup for you.</span></h2></div>
            <p>DeveloperJet offers managed software and VPS setup plans. Pick a product, compare its configuration, and talk to our team to get started.</p>
        </div>
        <div class="home-explainer-grid">
            <article class="home-explainer-card"><span>01 / HERMES</span><h3>CLI + Workspace</h3><p>Three Hermes CLI plans and three Docker-based Workspace plans, each set up on a VPS for you.</p><a href="{{ route('hermes') }}">Compare all Hermes plans <span aria-hidden="true">→</span></a></article>
            <article class="home-explainer-card"><span>02 / VPS SETUP</span><h3>n8n installed on a VPS</h3><p>Choose a managed n8n plan. Your n8n instance is installed on a VPS set up for your plan; our sales team confirms the configuration with you.</p><a href="{{ route('n8n') }}">See n8n plans <span aria-hidden="true">→</span></a></article>
            <article class="home-explainer-card"><span>03 / GETTING STARTED</span><h3>Choose a plan. Talk to us.</h3><p>Every plan’s Get started button opens a WhatsApp conversation with Sales for the next steps.</p><a href="{{ route('contact') }}">How to reach us <span aria-hidden="true">→</span></a></article>
        </div>
    </section>

    <section class="values-section section-shell">
        <div class="values-intro">
            <span class="eyebrow">The DeveloperJet difference</span>
            <h2>We take care of<br><span>the in-between.</span></h2>
            <p>Great tools should make the work feel lighter. Our job is to make the getting-started part feel that way too.</p>
            <a class="text-link" href="{{ route('about') }}">A bit more about us <span aria-hidden="true">→</span></a>
        </div>
        <div class="values-grid">
            <article class="value-card value-card-dark">
                <span class="value-number">01</span><span class="value-icon">✳</span>
                <h3>Setup, made human</h3><p>Clear onboarding that gets you from “where do I start?” to “I’m in.”</p>
            </article>
            <article class="value-card">
                <span class="value-number">02</span><span class="value-icon">↗</span>
                <h3>Less to babysit</h3><p>Managed foundations help you spend less time on upkeep and more time building.</p>
            </article>
            <article class="value-card">
                <span class="value-number">03</span><span class="value-icon">☺</span>
                <h3>People, not tickets</h3><p>When you need a hand, a real person is ready to talk it through.</p>
            </article>
            <article class="value-card value-card-accent">
                <span class="value-number">04</span><span class="value-icon">⌁</span>
                <h3>Room to grow</h3><p>Start with what you need now. There’s space to level up as you do.</p>
            </article>
        </div>
    </section>

    <section class="faq-section section-shell">
        <div class="faq-heading">
            <span class="eyebrow">Good questions</span>
            <h2>Before you<br><span>take off.</span></h2>
            <p>Want a hand picking a product? We’re only a call away.</p>
            <a class="button-secondary" href="https://wa.me/917688855600?text={{ rawurlencode('Hi DeveloperJet, please help me choose a product plan.') }}" target="_blank" rel="noopener noreferrer">Chat with Sales <span aria-hidden="true">↗</span></a>
        </div>
        <div class="faq-list">
            <details class="faq-item" open>
                <summary>Where do I find product plans?</summary>
                <p>Visit the dedicated <a href="{{ route('hermes') }}">Hermes</a> or <a href="{{ route('n8n') }}">n8n Managed</a> page to compare plans, billing options, and features.</p>
            </details>
            <details class="faq-item">
                <summary>Are the prices final?</summary>
                <p>Prices shown on product pages are illustrative INR amounts. Our team confirms final pricing, plan inclusions, and applicable taxes with you.</p>
            </details>
            <details class="faq-item">
                <summary>Can someone help me choose?</summary>
                <p>Of course. Message our Sales team and we’ll help match a plan to your workflow.</p>
            </details>
            <div class="closing-cta-inner">
                <span class="cta-orbit" aria-hidden="true">✦</span>
                <div><span class="eyebrow">Your next good idea is waiting</span><h2>Let’s get you<br><span>moving.</span></h2></div>
                <div class="closing-cta-action"><p>Tell us what you’re building. We’ll help you find your flow.</p><a class="button-light" href="{{ route('contact') }}">Let’s talk <span aria-hidden="true">↗</span></a></div>
            </div>
        </div>
    </section>

    <x-page-details
        eyebrow="A simple way forward"
        title="From browsing to up and running."
        description="Know what you’re choosing and who to contact at every step."
        :items="[
            ['label' => 'CHOOSE A PRODUCT', 'title' => 'Start with your use case', 'description' => 'Choose Hermes CLI with SSH or PuTTY access, Hermes Workspace with Docker, or n8n installed on a VPS.'],
            ['label' => 'COMPARE PLANS', 'title' => 'See what each plan includes', 'description' => 'Compare listed features and illustrative prices, then confirm the final details with our team.'],
            ['label' => 'GET STARTED', 'title' => 'Sales helps with next steps', 'description' => 'Click Get started on your chosen plan to message Sales on WhatsApp.'],
        ]"
        cta-title="Looking for a clear recommendation?"
        cta-description="Tell us what you want to run. Our team can walk you through the available plans."
        cta-label="Talk to Sales"
        :cta-url="route('contact')"
    />
@endsection
