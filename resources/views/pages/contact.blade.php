@extends('layouts.app')

@section('title', 'Contact & Support')
@section('meta_description', 'Contact DeveloperJet sales and support about AI agents, n8n automation, Claude Code, Docker VPS and digital services.')

@section('content')
    <section class="dj-page-hero" aria-labelledby="contact-title">
        <div class="dj-container">
            <div class="dj-page-hero__inner">
                <p class="dj-eyebrow">Contact &amp; support</p>
                <h1 id="contact-title">Talk to the DeveloperJet team</h1>
                <p class="dj-lede">Choosing a plan, waiting on a server, or need help with an existing service? Reach the team in the way that suits you.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="tel:+917688855600"><x-icon name="phone" :size="18" /> Call +91 76888 55600</a>
                    @auth
                        <a class="dj-btn dj-btn--secondary dj-btn--lg" href="{{ route('dashboard.tickets.create') }}">Open a support ticket</a>
                    @else
                        <a class="dj-btn dj-btn--secondary dj-btn--lg" href="{{ route('login') }}">Log in for support</a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    <section class="dj-section" id="support" aria-labelledby="options-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">Ways to reach us</p>
                <h2 id="options-title">Pick the channel that fits</h2>
            </div>
            <div class="dj-grid-3">
                <article class="dj-card">
                    <span class="dj-card__icon"><x-icon name="phone" :size="22" /></span>
                    <h3>Sales by phone</h3>
                    <p class="dj-muted">Speak with our team about plans, pricing and getting started.</p>
                    <p class="dj-card__action"><a class="dj-link" href="tel:+917688855600">Call +91 76888 55600 <x-icon name="arrow-right" :size="16" /></a></p>
                </article>
                <article class="dj-card">
                    <span class="dj-card__icon"><x-icon name="chat" :size="22" /></span>
                    <h3>Message on WhatsApp</h3>
                    <p class="dj-muted">Share what you are looking for and we will get back to you.</p>
                    <p class="dj-card__action"><a class="dj-link" href="https://wa.me/917688855600?text={{ rawurlencode('Hi DeveloperJet, I would like to know more about your plans.') }}" target="_blank" rel="noopener noreferrer">Start a conversation <x-icon name="arrow-right" :size="16" /></a></p>
                </article>
                <article class="dj-card">
                    <span class="dj-card__icon"><x-icon name="headset" :size="22" /></span>
                    <h3>Customer support</h3>
                    <p class="dj-muted">Existing customers can open and track support tickets from the dashboard.</p>
                    <p class="dj-card__action"><a class="dj-link" href="{{ route('dashboard.tickets') }}">Go to support tickets <x-icon name="arrow-right" :size="16" /></a></p>
                </article>
            </div>
        </div>
    </section>

    <section class="dj-section dj-section--surface" aria-labelledby="self-title">
        <div class="dj-container dj-intro">
            <div>
                <p class="dj-eyebrow">Self-service</p>
                <h2 id="self-title">Many answers are already in the docs</h2>
            </div>
            <div>
                <p class="dj-intro__text">Our documentation covers getting started, billing and the wallet, renewals, connecting your own domain and each product.</p>
                <div class="dj-actions dj-actions--top">
                    <a class="dj-btn dj-btn--secondary" href="{{ route('docs.index') }}"><x-icon name="book" :size="18" /> Browse documentation</a>
                    <a class="dj-link" href="{{ route('compare.index') }}">Compare products <x-icon name="arrow-right" :size="16" /></a>
                </div>
            </div>
        </div>
    </section>

    <x-page-details
        eyebrow="Make the first conversation easy"
        title="A few details help us help you"
        description="You do not need a polished brief. A little context is plenty."
        :items="[
            ['label' => 'WHAT YOU NEED', 'title' => 'Tell us what you are building', 'description' => 'Share which tools or workflows you want to run and what your team needs.'],
            ['label' => 'WHAT YOU ARE CONSIDERING', 'title' => 'Mention a product or plan', 'description' => 'Hermes, n8n, OpenClaw, Claude Code, Docker VPS or a digital service.'],
            ['label' => 'WHAT HAPPENS NEXT', 'title' => 'We follow up with details', 'description' => 'We confirm pricing, inclusions, the server configuration and the next setup steps.'],
        ]"
        cta-title="Prefer to browse first?"
        cta-description="Take a look at the plans, then reach out when you are ready."
        cta-label="View all plans"
        :cta-url="route('plans')"
    />
@endsection
