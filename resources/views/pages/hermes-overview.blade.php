@extends('layouts.app')

@section('title', 'Hermes CLI & Workspace plans')
@section('meta_description', 'Choose Hermes CLI installed on a VPS or Hermes Workspace installed with Docker. Compare three plans for each.')

@section('content')
    <section class="dj-phero" aria-labelledby="hermes-title">
        <div class="dj-container dj-phero__grid">
            <div class="dj-phero__copy">
                <p class="dj-eyebrow"><span class="dj-eyebrow__icon"><x-icon name="spark" :size="16" /></span>AI HOSTING · HERMES</p>
                <h1 id="hermes-title">Two ways to run the Hermes agent on your own server.</h1>
                <p class="dj-lede">Get Hermes CLI installed on a VPS and connect with SSH or PuTTY, or choose Hermes Workspace, installed and configured with Docker on a VPS and used from your browser. Three plans are available for each.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="{{ route('hermes.cli') }}">Explore Hermes CLI <x-icon name="arrow-right" :size="18" /></a>
                    <a class="dj-btn dj-btn--secondary dj-btn--lg" href="{{ route('hermes.workspace') }}">Explore Workspace</a>
                </div>
                <div class="dj-badges">
                    <span class="dj-badge dj-badge--blue"><x-icon name="shield" :size="15" /> Managed hosting</span>
                    <span class="dj-badge"><x-icon name="server" :size="15" /> A VPS dedicated to you</span>
                </div>
            </div>
            <figure class="dj-phero__art">
                @include('illustrations.hermes-workspace', ['alt' => 'Hermes Workspace in a browser, running with Docker on a dedicated VPS.'])
            </figure>
        </div>
    </section>

    <section class="dj-section dj-section--surface" aria-labelledby="choose-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">Pick your Hermes product</p>
                <h2 id="choose-title">CLI or Workspace — both set up for you</h2>
                <p class="dj-section-head__text">Choose the terminal-based CLI setup or the Docker-based browser Workspace. Each has three VPS configurations.</p>
            </div>
            <div class="dj-grid-2">
                <a class="dj-linkcard" href="{{ route('hermes.cli') }}">
                    <span class="dj-card__icon"><x-icon name="terminal" :size="22" /></span>
                    <span class="dj-cat__tag">3 CLI PLANS · SSH OR PUTTY</span>
                    <h3>Hermes CLI</h3>
                    <p>We install and configure Hermes CLI on your VPS. Connect from your computer and use it in the server terminal.</p>
                    <ul class="dj-checks"><li><x-icon name="check" :size="18" /> No domain needed</li><li><x-icon name="check" :size="18" /> Best for terminal users</li></ul>
                    <span class="dj-cat__cta">Compare CLI plans <x-icon name="arrow-right" :size="16" /></span>
                </a>
                <a class="dj-linkcard" href="{{ route('hermes.workspace') }}">
                    <span class="dj-card__icon"><x-icon name="browser" :size="22" /></span>
                    <span class="dj-cat__tag">3 WORKSPACE PLANS · DOCKER</span>
                    <h3>Hermes Workspace</h3>
                    <p>We install Hermes with Docker, configure it on a VPS, and hand over your Workspace for use in the browser.</p>
                    <ul class="dj-checks"><li><x-icon name="check" :size="18" /> Default address or your verified domain</li><li><x-icon name="check" :size="18" /> Best for a graphical workspace</li></ul>
                    <span class="dj-cat__cta">Compare Workspace plans <x-icon name="arrow-right" :size="16" /></span>
                </a>
            </div>
        </div>
    </section>

    <section class="dj-section" aria-labelledby="hermes-faq-title">
        <div class="dj-container dj-faq-layout">
            <div class="dj-faq-layout__aside">
                <p class="dj-eyebrow">Not sure which one?</p>
                <h2 id="hermes-faq-title">Find the setup that fits</h2>
                <p class="dj-section-head__text">Both options include installation and configuration on a VPS dedicated to you.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--secondary" href="{{ route('compare.show', 'hermes-cli-vs-hermes-workspace') }}">Read the comparison</a>
                </div>
            </div>
            <div class="dj-faq">
                <details class="dj-faq__item" open><summary>What is included with Hermes CLI? <x-icon name="chevron-down" :size="20" /></summary><p>We install and configure Hermes CLI on a VPS. You connect using SSH or PuTTY and use Hermes from the server terminal.</p></details>
                <details class="dj-faq__item"><summary>What is Hermes Workspace? <x-icon name="chevron-down" :size="20" /></summary><p>We install Hermes using Docker, configure it on a VPS, and provide you the Workspace setup to use from a browser.</p></details>
                <details class="dj-faq__item"><summary>How many plans are available? <x-icon name="chevron-down" :size="20" /></summary><p>There are three resource tiers for Hermes CLI and three for Hermes Workspace, with matching VPS configurations and indicative INR pricing.</p></details>
            </div>
        </div>
    </section>
@endsection
