@extends('layouts.app')

@section('title', 'Refund Policy')
@section('meta_description', 'Understand DeveloperJet’s refund policy for digital services and VPS plans.')

@section('content')
    <section class="legal-hero refund-hero section-shell">
        <span class="eyebrow">Clear from the start</span>
        <h1>Our refund <span>policy.</span></h1>
        <p>We want you to know what to expect before choosing a digital service or managed plan.</p>
        <span class="legal-updated">Last updated: October 2026</span>
    </section>

    <section class="refund-statement-section section-shell">
        <div class="refund-statement">
            <span class="refund-symbol">↗</span>
            <span class="eyebrow">Please note</span>
            <h2>Activated digital services and VPS plans are non-refundable.</h2>
            <p>Once a digital service is activated or a VPS has been provisioned and set up for your plan, the purchase is non-refundable.</p>
        </div>
        <div class="refund-side-note">
            <span class="refund-side-icon">i</span>
            <h3>Before you subscribe</h3>
            <p>Please review the plan details, billing period, and any usage limits with our team before confirming your purchase.</p>
            <a href="{{ route('hermes') }}">Review plans <span aria-hidden="true">→</span></a>
        </div>
    </section>

    <section class="refund-help-section section-shell">
        <div><span class="eyebrow">Something doesn’t look right?</span><h2>We’re happy to<br><span>look into it.</span></h2></div>
        <div class="refund-help-copy"><p>If you believe you were charged in error or have an issue with activation or VPS setup, contact us as soon as possible. We’ll review the details and help resolve the issue. This does not create an exception to the non-refundable policy for activated digital services or provisioned VPS plans.</p><div class="refund-contact-actions"><a class="button-primary" href="https://wa.me/917688855600?text={{ rawurlencode('Hi DeveloperJet, I need help with a billing or activation issue.') }}" target="_blank" rel="noopener noreferrer">Message our team <span aria-hidden="true">↗</span></a></div></div>
    </section>

    <x-page-details
        eyebrow="Make an informed choice"
        title="Before placing an order."
        description="Please take a moment to review the plan and service details before confirming."
        :items="[
            ['label' => 'REVIEW', 'title' => 'Check your selected plan', 'description' => 'Confirm the product, features, usage limits, and support level you selected.'],
            ['label' => 'BILLING', 'title' => 'Confirm your billing period', 'description' => 'Yearly prices are billed annually. Confirm the final amount and applicable taxes with Sales.'],
            ['label' => 'VPS SETUP', 'title' => 'Discuss VPS setup details', 'description' => 'For Hermes or n8n, confirm the VPS configuration and setup expectations before purchase.'],
        ]"
        cta-title="Need clarification before you subscribe?"
        cta-description="Our team can walk through the plan and its details before you decide."
        cta-label="Ask Sales"
        :cta-url="route('contact')"
    />
@endsection
