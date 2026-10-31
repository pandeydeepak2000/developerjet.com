@extends('layouts.app')

@section('title', 'Terms of Service')
@section('meta_description', 'Read the terms that apply when you browse or use DeveloperJet services.')

@section('content')
    <section class="legal-hero section-shell">
        <span class="eyebrow">The straightforward version</span>
        <h1>Terms of <span>service.</span></h1>
        <p>Clear expectations make for better working relationships. Here are the basics for using DeveloperJet.</p>
        <span class="legal-updated">Last updated: October 2026</span>
    </section>

    <section class="legal-overview section-shell">
        <article class="legal-callout"><span>01</span><h2>Using our services</h2><p>When you use our website or purchase a plan, you agree to use the service lawfully and follow any plan-specific terms we share with you.</p></article>
        <article class="legal-callout"><span>02</span><h2>Plans & billing</h2><p>Website prices are illustrative. Your final price, inclusions, billing cycle, taxes, and any usage limits will be confirmed before a purchase is made.</p></article>
        <article class="legal-callout"><span>03</span><h2>Third-party products</h2><p>Some offerings involve third-party software. Your use of those products may also be subject to their own terms and policies.</p></article>
    </section>

    <section class="legal-content section-shell">
        <div class="legal-content-heading"><span class="eyebrow">The details</span><h2>A few more<br><span>important things.</span></h2></div>
        <div class="legal-copy">
            <h3>Accounts and access</h3>
            <p>You are responsible for providing accurate information and keeping your account credentials secure. Please let us know promptly if you believe access to your account has been compromised.</p>
            <h3>Availability and changes</h3>
            <p>We work to keep services dependable, but availability may be affected by maintenance, third-party services, or events outside our control. We may update features or these terms; material changes will be communicated through appropriate channels.</p>
            <h3>Responsible use</h3>
            <p>You may not use DeveloperJet services to break the law, interfere with another person’s access, or compromise systems. You retain rights to content you provide, and you authorize us to process it only as needed to deliver the service.</p>
            <h3>Questions and contact</h3>
            <p>These terms are provided as general service information and do not replace a signed agreement where one applies. For questions about a plan or these terms, <a href="{{ route('contact') }}">contact our team</a>.</p>
        </div>
    </section>

    <x-page-details
        eyebrow="Plan with clarity"
        title="What to confirm before checkout."
        description="Your final order should clearly show the plan and commercial details you agreed to."
        :items="[
            ['label' => 'PRICE', 'title' => 'Confirm the final amount', 'description' => 'Website prices are illustrative. Confirm the final amount, tax treatment, and billing period before you purchase.'],
            ['label' => 'PLAN', 'title' => 'Review included services', 'description' => 'Check the plan description and any seat, workflow, or usage limits that apply.'],
            ['label' => 'VPS SETUP', 'title' => 'Confirm VPS details', 'description' => 'For Hermes or n8n, discuss the VPS configuration and setup details with Sales before ordering.'],
        ]"
        cta-title="Need help interpreting these terms?"
        cta-description="Contact our team before choosing or purchasing a service."
        cta-label="Contact DeveloperJet"
        :cta-url="route('contact')"
    />
@endsection
