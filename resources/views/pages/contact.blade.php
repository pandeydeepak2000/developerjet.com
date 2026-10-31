@extends('layouts.app')

@section('title', 'Contact DeveloperJet')
@section('meta_description', 'Talk to DeveloperJet about Hermes CLI, Hermes Workspace, or n8n Managed plans.')

@section('content')
    <section class="inner-hero contact-hero section-shell">
        <div class="inner-hero-copy">
            <span class="eyebrow">A real person is a real option</span>
            <h1>Let’s find your <span>next step.</span></h1>
            <p>Choosing a plan, getting started, or just curious? Tell us a little about it. We’ll make the conversation easy.</p>
            <a class="button-primary" href="tel:+917688855600">Call +91 76888 55600 <span aria-hidden="true">↗</span></a>
        </div>
        <div class="inner-hero-art contact-art" aria-hidden="true">
            <div class="contact-circle circle-one"></div><div class="contact-circle circle-two"></div>
            <div class="contact-note"><span>HELLO, BUILDER</span><strong>We’re<br>listening.</strong><i>✳</i></div>
            <span class="contact-art-caption">GOOD CONVERSATIONS START HERE</span>
        </div>
    </section>

    <section class="contact-options-section section-shell" id="support">
        <div class="section-heading simple-heading">
            <div><span class="eyebrow">Pick what feels easy</span><h2>We’re just<br><span>one tap away.</span></h2></div>
            <p>No forms to get lost in. Reach out in the way that works for you.</p>
        </div>
        <div class="contact-options-grid">
            <article class="contact-option-card">
                <span class="contact-option-icon">☎</span><span class="option-label">CALL US</span>
                <h3>Let’s talk it through.</h3><p>Speak with our team about plans, pricing, or getting started.</p>
                <a href="tel:+917688855600">+91 76888 55600 <span aria-hidden="true">↗</span></a>
            </article>
            <article class="contact-option-card contact-option-whatsapp">
                <span class="contact-option-icon">↗</span><span class="option-label">WHATSAPP</span>
                <h3>Send us a quick note.</h3><p>Share what you’re looking for and we’ll get back to you.</p>
                <a href="https://wa.me/917688855600?text={{ rawurlencode('Hi DeveloperJet, I would like to know more about your plans.') }}" target="_blank" rel="noopener noreferrer">Chat with sales <span aria-hidden="true">↗</span></a>
            </article>
            <article class="contact-option-card contact-option-soft">
                <span class="contact-option-icon">✳</span><span class="option-label">NOT SURE YET?</span>
                <h3>That’s a great place to start.</h3><p>Tell us what you’re building. We can help you choose a sensible next step.</p>
                <a href="{{ route('home') }}#products">Browse the plans <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </section>

    <section class="contact-promise section-shell">
        <div class="promise-sticker">NO<br>BOT<br>MAZE</div>
        <div><span class="eyebrow">A small promise</span><h2>Helpful is the whole point.</h2><p>You’ll get a straightforward conversation with a person who wants to understand what you need—no pressure, no complicated runaround.</p></div>
        <a class="button-secondary" href="https://wa.me/917688855600" target="_blank" rel="noopener noreferrer">Message our team <span aria-hidden="true">↗</span></a>
    </section>

    <x-page-details
        eyebrow="Make the first conversation easy"
        title="A few details help us help you."
        description="You don’t need a polished brief. A little context is plenty."
        :items="[
            ['label' => 'WHAT YOU NEED', 'title' => 'Tell us what you’re building', 'description' => 'Share which tools or workflows you want to get running and what your team needs.'],
            ['label' => 'WHAT YOU’RE CONSIDERING', 'title' => 'Mention a product or plan', 'description' => 'Hermes CLI, Hermes Workspace, or n8n Managed—our team can talk through the options.'],
            ['label' => 'WHAT HAPPENS NEXT', 'title' => 'Sales follows up with details', 'description' => 'We can confirm pricing, included features, VPS configuration, and the next setup steps.'],
        ]"
        cta-title="Prefer to browse first?"
        cta-description="Take a look at the product pages, then reach out when you’re ready."
        cta-label="Browse Hermes plans"
        :cta-url="route('hermes')"
    />
@endsection
