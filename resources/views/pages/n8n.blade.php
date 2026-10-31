@extends('layouts.app')

@section('title', 'n8n Managed plans')
@section('meta_description', 'Explore n8n plans set up on a managed VPS, with helpful setup, backups, and support from DeveloperJet.')

@section('content')
    @php
        $n8nPlans = [
            ['name' => 'Launch', 'description' => 'Automate the small things first.', 'monthly' => 2499, 'annual_total' => 24990, 'annual_monthly' => 2083, 'highlight' => 'Up to 5 active workflows', 'features' => ['n8n installed on a managed VPS', 'Backups and updates'], 'featured' => false],
            ['name' => 'Grow', 'description' => 'For workflows that are going places.', 'monthly' => 5999, 'annual_total' => 59990, 'annual_monthly' => 4999, 'highlight' => 'Up to 25 active workflows', 'features' => ['n8n installed on a managed VPS', 'Priority support'], 'featured' => true],
            ['name' => 'Scale', 'description' => 'More headroom for bigger ideas.', 'monthly' => 11999, 'annual_total' => 119990, 'annual_monthly' => 9999, 'highlight' => 'Up to 100 active workflows', 'features' => ['n8n installed on a managed VPS', 'Guided workflow onboarding'], 'featured' => false],
        ];
    @endphp

    <section class="product-page-hero section-shell n8n-page-hero">
        <div class="product-page-copy">
            <span class="eyebrow"><span class="eyebrow-spark">↗</span> MANAGED AUTOMATION, WITHOUT THE SERVER CHORES</span>
            <h1>Let your workflows<br><span>do their thing.</span></h1>
            <p>Your n8n instance is installed on a VPS set up for your plan. We’ll help configure and manage the server, so you can spend less time tending to infrastructure and more time connecting the things you use.</p>
            <div class="hero-actions">
                <a class="button-primary" href="#pricing">Explore n8n plans <span aria-hidden="true">↗</span></a>
                <a class="button-secondary" href="{{ route('contact') }}">Talk to Sales</a>
            </div>
            <div class="product-hero-proof"><span>INSTALLED ON VPS</span><i></i><span>BACKUPS & UPDATES</span><i></i><span>HUMAN SUPPORT</span></div>
        </div>
        <div class="product-hero-art n8n-hero-art" aria-hidden="true">
            <div class="product-art-orbit"></div><div class="product-art-glow"></div>
            <div class="workflow-board">
                <div class="workflow-node workflow-trigger"><span>✳</span><small>TRIGGER</small><strong>New event</strong></div>
                <div class="workflow-line"><i></i><i></i><i></i></div>
                <div class="workflow-node workflow-action"><span>↗</span><small>AUTOMATION</small><strong>Do the thing</strong></div>
                <div class="workflow-line"><i></i><i></i><i></i></div>
                <div class="workflow-node workflow-done"><span>✓</span><small>ALL SET</small><strong>Flow complete</strong></div>
            </div>
            <div class="product-art-tag n8n-art-tag"><span>YOUR BUSYWORK</span><strong>ON AUTOPILOT ↗</strong></div>
        </div>
    </section>

    <section class="product-benefits section-shell">
        <div class="section-heading simple-heading">
            <div><span class="eyebrow">Automation should feel freeing</span><h2>Connect more.<br><span>Manage less.</span></h2></div>
            <p>A dependable managed foundation helps you build useful workflows without making server maintenance another job.</p>
        </div>
        <div class="product-benefit-grid">
            <article class="product-benefit-card">
                <span class="benefit-number">01 / READY TO RUN</span><span class="benefit-icon">↗</span>
                <h3>Installed on your VPS</h3><p>Your n8n instance runs on a VPS. We help with installation and server setup; VPS size and configuration are confirmed with you before purchase.</p>
            </article>
            <article class="product-benefit-card">
                <span class="benefit-number">02 / THE IMPORTANT BITS</span><span class="benefit-icon">◷</span>
                <h3>Backups and updates</h3><p>Keep the routine upkeep in view with a plan that includes backups and updates.</p>
            </article>
            <article class="product-benefit-card">
                <span class="benefit-number">03 / HERE TO HELP</span><span class="benefit-icon">☺</span>
                <h3>Human guidance</h3><p>Talk to a real person about plan capacity, onboarding, or getting your automations moving.</p>
            </article>
        </div>
    </section>

    <section class="product-pricing section-shell" id="pricing">
        <div class="section-heading">
            <div><span class="eyebrow">n8n Managed plans</span><h2>Make room for<br><span>more automation.</span></h2></div>
            <div class="section-heading-aside">
                <p>Illustrative INR pricing. We’ll confirm your plan details with you.</p>
                <div class="billing-switch" role="group" aria-label="Choose billing period">
                    <button type="button" class="billing-option is-active" data-billing-toggle="monthly" aria-pressed="true">Monthly</button>
                    <button type="button" class="billing-option" data-billing-toggle="annual" aria-pressed="false">Yearly <span>save 17%</span></button>
                </div>
            </div>
        </div>
        <div class="product-group-heading">
            <div class="product-icon product-icon-n8n">↗</div>
            <div><h3>n8n Managed</h3><p>Pick a little runway or a lot of headroom.</p></div>
            <span class="product-group-label">3 plans</span>
        </div>
        <div class="plan-grid">
            @foreach ($n8nPlans as $plan)
                <x-plan-card :product="'n8n Managed'" :name="$plan['name']" :description="$plan['description']" :monthly="$plan['monthly']" :annual-total="$plan['annual_total']" :annual-monthly="$plan['annual_monthly']" :highlight="$plan['highlight']" :plan-note="'VPS size confirmed before purchase.'" :features="$plan['features']" :featured="$plan['featured']" />
            @endforeach
        </div>
        <p class="pricing-disclaimer">Illustrative launch pricing in INR. Final inclusions and pricing are confirmed with our sales team. Applicable taxes and usage limits may vary.</p>
    </section>

    <section class="product-faq section-shell">
        <div class="faq-heading">
            <span class="eyebrow">A few n8n questions</span><h2>We’ve got<br><span>you.</span></h2>
            <p>Not sure how much room your workflows need? Let’s talk.</p><a class="button-secondary" href="{{ route('contact') }}">Talk to Sales</a>
        </div>
        <div class="faq-list">
            <details class="faq-item" open><summary>Is n8n installed on a VPS?</summary><p>Yes. The n8n instance is installed on a VPS configured for your plan. Our team confirms the VPS size, configuration, and plan details with you before purchase.</p></details>
            <details class="faq-item"><summary>Can I switch plans as I grow?</summary><p>Talk with our team about changing plans as your workflow needs grow. We’ll confirm availability and any pricing changes before you switch.</p></details>
            <details class="faq-item"><summary>Are the listed prices final?</summary><p>These are illustrative INR amounts. Our team confirms your final price, plan inclusions, and applicable taxes before purchase.</p></details>
            <div class="closing-cta-inner">
                <span class="cta-orbit" aria-hidden="true">✦</span>
                <div><span class="eyebrow">Let the busywork take care of itself</span><h2>Ready to put your<br><span>flows to work?</span></h2></div>
                <div class="closing-cta-action"><p>Let’s talk about the workflows you want to get moving.</p><a class="button-light" href="{{ route('contact') }}">Talk to our team <span aria-hidden="true">↗</span></a></div>
            </div>
        </div>
    </section>

    <x-page-details
        eyebrow="What managed n8n means"
        title="n8n on a VPS, with the setup made clear."
        description="DeveloperJet sells managed n8n plans. The service includes installation on a VPS; confirm server specifications and final pricing with Sales."
        :items="[
            ['label' => '01 / VPS', 'title' => 'Installed on a VPS', 'description' => 'Your n8n instance is installed on a VPS selected for the agreed plan.'],
            ['label' => '02 / PLAN DETAILS', 'title' => 'Agree the server configuration', 'description' => 'VPS size and configuration depend on your requirements and are confirmed before purchase.'],
            ['label' => '03 / GET STARTED', 'title' => 'Sales coordinates next steps', 'description' => 'Use Get started on your chosen plan to message Sales and discuss the setup.'],
        ]"
        cta-title="Have questions about the VPS before choosing?"
        cta-description="Talk to Sales about VPS configuration, plan limits, and setup."
        cta-label="Discuss VPS options"
        :cta-url="route('contact')"
    />
@endsection
