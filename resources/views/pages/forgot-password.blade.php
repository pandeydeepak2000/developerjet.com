@extends('layouts.app')

@section('title', 'Password help')
@section('meta_description', 'Password assistance page preview for DeveloperJet. Contact sales for account access help.')

@section('content')
    <section class="auth-page auth-page-forgot section-shell">
        <div class="auth-story">
            <span class="eyebrow"><span class="eyebrow-spark">✳</span> NO WORRIES, IT HAPPENS</span>
            <h1>We’ll help you<br><span>find your way.</span></h1>
            <p>Password reset is not enabled on this preview. Reach out to our team and we’ll point you in the right direction.</p>
            <div class="auth-feature-stack">
                <div class="auth-feature"><span>☺</span><div><strong>Speak to our team</strong><small>Sales can help with account access.</small></div></div>
                <div class="auth-feature"><span>↗</span><div><strong>Send us a WhatsApp</strong><small>We’ll help with account access.</small></div></div>
            </div>
            <div class="auth-decoration" aria-hidden="true"><div class="auth-decoration-orbit"></div><span class="auth-decoration-star">?</span><span class="auth-decoration-label">HERE TO HELP</span></div>
        </div>
        <x-auth-card mode="forgot" />
    </section>
    <x-page-details
        eyebrow="Account help"
        title="Password recovery is not active yet."
        description="This visual-only page does not send reset links or change account credentials."
        :items="[
            ['label' => 'NO RESET EMAIL', 'title' => 'The reset button is disabled', 'description' => 'No account or email service is connected to this page.'],
            ['label' => 'NO PASSWORD COLLECTION', 'title' => 'Your details are not submitted', 'description' => 'The email field is a visual placeholder and does not send information anywhere.'],
            ['label' => 'HUMAN HELP', 'title' => 'Ask Sales for assistance', 'description' => 'Use the WhatsApp support action if you need help with account access.'],
        ]"
        cta-title="Need a hand with a DeveloperJet product?"
        cta-description="Browse the current products and plan information."
        cta-label="Explore products"
        :cta-url="route('hermes')"
    />
@endsection
