@extends('layouts.app')

@section('title', 'Create your account')
@section('meta_description', 'Account creation page preview for DeveloperJet. Contact sales to get started.')

@section('content')
    <section class="auth-page section-shell">
        <div class="auth-story">
            <span class="eyebrow"><span class="eyebrow-spark">✳</span> YOUR NEXT GOOD THING STARTS HERE</span>
            <h1>Make a little<br><span>room to build.</span></h1>
            <p>Get set up with developer tools that feel easy from day one, with a real team to help you find your flow.</p>
            <div class="auth-feature-stack">
                <div class="auth-feature"><span>⌘</span><div><strong>Find your plan</strong><small>Hermes and n8n, made simple.</small></div></div>
                <div class="auth-feature"><span>☺</span><div><strong>Get help from a human</strong><small>We’ll help with your next steps.</small></div></div>
            </div>
            <div class="auth-decoration auth-decoration-register" aria-hidden="true"><div class="auth-decoration-orbit"></div><span class="auth-decoration-star">↗</span><span class="auth-decoration-label">READY WHEN YOU ARE</span></div>
        </div>
        <x-auth-card mode="register" />
    </section>
    <x-page-details
        eyebrow="Start with the product that fits"
        title="A clear path from idea to setup."
        description="Account registration is visual-only for now. Sales can walk you through the product and purchase steps."
        :items="[
            ['label' => 'HERMES', 'title' => 'CLI and Workspace plans', 'description' => 'Choose CLI installed for SSH or PuTTY access, or Workspace installed with Docker on a VPS.'],
            ['label' => 'n8n', 'title' => 'n8n on a managed VPS', 'description' => 'Review n8n plans and confirm the VPS configuration with Sales.'],
            ['label' => 'YOUR NEXT STEP', 'title' => 'Get started with Sales', 'description' => 'Choose a plan and use its Get started button to message Sales on WhatsApp.'],
        ]"
        cta-title="Want to see the available plans?"
        cta-description="Compare the products before talking to Sales."
        cta-label="View Hermes plans"
        :cta-url="route('hermes')"
    />
@endsection
