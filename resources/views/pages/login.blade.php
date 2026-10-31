@extends('layouts.app')

@section('title', 'Log in')
@section('meta_description', 'Log-in page preview for DeveloperJet. Contact sales for account assistance.')

@section('content')
    <section class="auth-page section-shell">
        <div class="auth-story">
            <span class="eyebrow"><span class="eyebrow-spark">✳</span> YOUR NEXT SESSION STARTS HERE</span>
            <h1>Pick up right<br><span>where you left off.</span></h1>
            <p>Your tools and your people are never far away. Need a hand getting in? Our team is happy to help.</p>
            <div class="auth-feature-stack">
                <div class="auth-feature"><span>✓</span><div><strong>Thoughtful tools</strong><small>Everything in its right place.</small></div></div>
                <div class="auth-feature"><span>↗</span><div><strong>Real human support</strong><small>Talk it through with our team.</small></div></div>
            </div>
            <div class="auth-decoration" aria-hidden="true"><div class="auth-decoration-orbit"></div><span class="auth-decoration-star">✳</span><span class="auth-decoration-label">GOOD TO HAVE YOU BACK</span></div>
        </div>
        <x-auth-card mode="login" />
    </section>
    <x-page-details
        eyebrow="Need help accessing your services?"
        title="The team can help you find the right next step."
        description="These sign-in screens are a visual preview and do not authenticate users."
        :items="[
            ['label' => 'ACCOUNT ACCESS', 'title' => 'No sign-in service is connected', 'description' => 'This page does not validate account credentials or open a user dashboard.'],
            ['label' => 'PASSWORD HELP', 'title' => 'No reset email is sent', 'description' => 'Use the password-help screen to see the support options available in this preview.'],
            ['label' => 'SALES SUPPORT', 'title' => 'Contact Sales for assistance', 'description' => 'Our team can explain the products and discuss how to get started.'],
        ]"
        cta-title="Want to learn about a DeveloperJet plan?"
        cta-description="Explore the products or message our team for guidance."
        cta-label="Explore products"
        :cta-url="route('hermes')"
    />
@endsection
