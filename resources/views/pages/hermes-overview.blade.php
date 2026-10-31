@extends('layouts.app')

@section('title', 'Hermes CLI & Workspace plans')
@section('meta_description', 'Choose Hermes CLI installed on a VPS or Hermes Workspace installed with Docker. Compare three plans for each.')

@section('content')
    <section class="product-page-hero section-shell hermes-page-hero">
        <div class="product-page-copy">
            <span class="eyebrow"><span class="eyebrow-spark">⌘</span> TWO WAYS TO RUN HERMES</span>
            <h1>Choose your<br><span>Hermes setup.</span></h1>
            <p>Get Hermes CLI installed on a VPS and connect with SSH or PuTTY, or choose Hermes Workspace, installed and configured with Docker on a VPS. Three plans are available for each.</p>
            <div class="hero-actions">
                <a class="button-primary" href="{{ route('hermes.cli') }}">Explore Hermes CLI <span aria-hidden="true">↗</span></a>
                <a class="button-secondary" href="{{ route('hermes.workspace') }}">Explore Workspace</a>
            </div>
            <div class="product-hero-proof"><span>3 CLI PLANS</span><i></i><span>3 WORKSPACE PLANS</span><i></i><span>SETUP INCLUDED</span></div>
        </div>
        <div class="product-hero-art hermes-hero-art" aria-hidden="true">
            <div class="product-art-orbit"></div><div class="product-art-glow"></div>
            <div class="product-terminal">
                <div class="terminal-top"><span class="terminal-dots"><i></i><i></i><i></i></span><span>hermes · choose setup</span><span>⌑</span></div>
                <div class="terminal-body">
                    <p><span class="terminal-prompt">➜</span> hermes plan</p>
                    <p class="terminal-muted">Choose how to connect...</p>
                    <p><span class="terminal-check">✓</span> CLI · SSH or PuTTY</p>
                    <p><span class="terminal-check">✓</span> Workspace · Docker</p>
                    <div class="terminal-ready"><span></span> Setup handled for you</div>
                </div>
            </div>
            <div class="product-art-tag hermes-art-tag"><span>YOUR HERMES SETUP</span><strong>READY TO RUN ✳</strong></div>
        </div>
    </section>

    <section class="home-products section-shell">
        <div class="section-heading">
            <div><span class="eyebrow">Pick your Hermes product</span><h2>CLI or Workspace.<br><span>Both set up for you.</span></h2></div>
            <p class="home-products-intro">Choose the terminal-based CLI setup or a Docker-based Workspace deployment. Each has three VPS configurations.</p>
        </div>
        <div class="home-product-grid">
            <a class="home-product-card home-product-hermes" href="{{ route('hermes.cli') }}">
                <div class="home-product-top"><span class="product-icon product-icon-hermes">⌘</span><span class="product-group-label">3 CLI PLANS</span></div>
                <span class="home-product-kicker">CONNECT WITH SSH OR PUTTY</span>
                <h3>Hermes CLI</h3>
                <p>We install and configure Hermes CLI on your VPS. Connect from your computer and use it in the server terminal.</p>
                <span class="home-product-cta">Compare CLI plans <span aria-hidden="true">↗</span></span>
                <span class="home-product-art hermes-product-art" aria-hidden="true">⌘</span>
            </a>
            <a class="home-product-card home-product-n8n" href="{{ route('hermes.workspace') }}">
                <div class="home-product-top"><span class="product-icon product-icon-hermes">▦</span><span class="product-group-label">3 WORKSPACE PLANS</span></div>
                <span class="home-product-kicker">DOCKER SETUP ON YOUR VPS</span>
                <h3>Hermes Workspace</h3>
                <p>We install Hermes with Docker, configure it on a VPS, and hand over your Workspace setup.</p>
                <span class="home-product-cta">Compare Workspace plans <span aria-hidden="true">↗</span></span>
                <span class="home-product-art n8n-product-art" aria-hidden="true">▦</span>
            </a>
        </div>
    </section>

    <section class="faq-section section-shell">
        <div class="faq-heading">
            <span class="eyebrow">Not sure which one?</span>
            <h2>Find the setup<br><span>that fits.</span></h2>
            <p>Both options include installation and configuration on a VPS.</p>
            <a class="button-secondary" href="{{ route('contact') }}">Talk to Sales</a>
        </div>
        <div class="faq-list">
            <details class="faq-item" open><summary>What is included with Hermes CLI?</summary><p>We install and configure Hermes CLI on a VPS. You connect using SSH or PuTTY and use Hermes from the server terminal.</p></details>
            <details class="faq-item"><summary>What is Hermes Workspace?</summary><p>We install Hermes using Docker, configure it on a VPS, and provide you the Workspace setup.</p></details>
            <details class="faq-item"><summary>How many plans are available?</summary><p>There are three resource tiers for Hermes CLI and three for Hermes Workspace, with matching proposed VPS configurations and indicative INR pricing.</p></details>
        </div>
    </section>
@endsection
