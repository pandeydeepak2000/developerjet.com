@extends('layouts.app')

@section('title', 'Password help')
@section('meta_description', 'Reset your DeveloperJet password by email.')

@section('content')
    <section class="auth-page auth-page-forgot section-shell">
        <div class="auth-story">
            <span class="eyebrow">NO WORRIES, IT HAPPENS</span>
            <h1>We’ll help you find your way.</h1>
            <p>Enter your email and we’ll send you a link to choose a new password.</p>
            <div class="auth-feature-stack">
                <div class="auth-feature"><span><x-icon name="check" :size="18" /></span><div><strong>Speak to our team</strong><small>Sales can help with account access.</small></div></div>
                <div class="auth-feature"><span><x-icon name="check" :size="18" /></span><div><strong>Message us</strong><small>We can help you regain access.</small></div></div>
            </div>
        </div>
        <x-auth-card mode="forgot" />
    </section>
    <x-page-details
        eyebrow="Account help"
        title="Recover access to your account."
        description="Reset links expire, and we never reveal whether an email has an account."
        :items="[
            ['label' => 'RESET LINK', 'title' => 'Sent by email', 'description' => 'Links are single-use and expire after 60 minutes.'],
            ['label' => 'PRIVACY', 'title' => 'No account enumeration', 'description' => 'The response is the same whether or not the email is registered.'],
            ['label' => 'HUMAN HELP', 'title' => 'Ask Sales for assistance', 'description' => 'Use the WhatsApp support action if you need help with account access.'],
        ]"
        cta-title="Need a hand with a DeveloperJet product?"
        cta-description="Browse the current products and plan information."
        cta-label="Explore products"
        :cta-url="route('hermes')"
    />
@endsection
