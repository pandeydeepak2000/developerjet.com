@extends('layouts.app')

@section('title', 'Create your account')
@section('meta_description', 'Create your DeveloperJet account and verify your email with a one-time code.')

@section('content')
    <section class="auth-page section-shell">
        <div class="auth-story">
            <span class="eyebrow">YOUR NEXT GOOD THING STARTS HERE</span>
            <h1>Create your DeveloperJet account.</h1>
            <p>Get set up with developer tools that feel easy from day one, with a real team to help you find your flow.</p>
            <div class="auth-feature-stack">
                <div class="auth-feature"><span><x-icon name="check" :size="18" /></span><div><strong>AI and developer plans</strong><small>Hermes, n8n, OpenClaw, Claude Code and Docker.</small></div></div>
                <div class="auth-feature"><span><x-icon name="check" :size="18" /></span><div><strong>Verified by email</strong><small>A one-time code confirms your address before checkout.</small></div></div>
            </div>
        </div>
        <x-auth-card mode="register" />
    </section>
    <x-page-details
        eyebrow="Start with the product that fits"
        title="A clear path from idea to setup."
        description="Create an account, verify your email, then add a plan to your cart."
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
