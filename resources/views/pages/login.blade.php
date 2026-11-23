@extends('layouts.app')

@section('title', 'Log in')
@section('meta_description', 'Sign in to your DeveloperJet account.')

@section('content')
    <section class="auth-page section-shell">
        <div class="auth-story">
            <span class="eyebrow">YOUR NEXT SESSION STARTS HERE</span>
            <h1>Pick up right where you left off.</h1>
            <p>Your tools and your people are never far away. Need a hand getting in? Our team is happy to help.</p>
            <div class="auth-feature-stack">
                <div class="auth-feature"><span><x-icon name="check" :size="18" /></span><div><strong>Orders, services and invoices</strong><small>Everything for your account in one dashboard.</small></div></div>
                <div class="auth-feature"><span><x-icon name="check" :size="18" /></span><div><strong>Support tickets</strong><small>Track every request with our team.</small></div></div>
            </div>
        </div>
        <x-auth-card mode="login" />
    </section>
    <x-page-details
        eyebrow="Need help accessing your services?"
        title="The team can help you find the right next step."
        description="Sign in securely, or create an account and verify your email with a one-time code."
        :items="[
            ['label' => 'ACCOUNT ACCESS', 'title' => 'Email verified accounts', 'description' => 'New accounts verify their email with a 6-digit code before checkout.'],
            ['label' => 'PASSWORD HELP', 'title' => 'Reset by email', 'description' => 'Use Forgot password to receive a reset link.'],
            ['label' => 'SALES SUPPORT', 'title' => 'Contact Sales for assistance', 'description' => 'Our team can explain the products and discuss how to get started.'],
        ]"
        cta-title="Want to learn about a DeveloperJet plan?"
        cta-description="Explore the products or message our team for guidance."
        cta-label="Explore products"
        :cta-url="route('hermes')"
    />
@endsection
