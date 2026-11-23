@extends('layouts.app')

@section('title', 'AI Hosting & Developer Infrastructure')
@section('meta_description', 'DeveloperJet runs AI agents, workflow automation and developer tools on dedicated VPS servers — Hermes, n8n, OpenClaw, Claude Code and Docker — with managed setup, clear billing and real support.')

@section('content')
    @php
        $features = [
            ['shield', 'Security first', 'Dedicated servers per customer, verified payments, encrypted secrets and no plaintext root passwords.'],
            ['settings', 'Managed setup', 'For managed plans we prepare the server and install your software. Unmanaged plans hand you full control.'],
            ['code', 'Developer tools', 'SSH access, Docker and Compose, Claude Code and Hermes — the tools developers already use.'],
            ['dashboard', 'Customer dashboard', 'Orders, services, invoices, wallet and support tickets in one place.'],
            ['headset', 'Real support', 'Ticket-based support from the DeveloperJet team, with clear statements of what is covered.'],
            ['card', 'Clear billing', 'INR invoices, a wallet and renewal reminders. Renewals are never charged automatically.'],
            ['activity', 'Deployment tracking', 'Follow each order from checkout to a ready service in your dashboard.'],
        ];
        $faqs = [
            ['What does DeveloperJet do?', 'We run AI agents, workflow automation and developer tools on VPS servers dedicated to you. For managed products we set up and look after the server; for unmanaged products we hand you full SSH/root access.'],
            ['Is delivery instant?', 'No. After your payment is verified, our team prepares your environment: the server is set up and your software is installed and checked. Progress is shown in your dashboard and access details appear there when everything is ready.'],
            ['Do I need my own AI subscription?', 'For AI products, yes. Hermes and OpenClaw use your own AI provider key, and Claude Code needs your own eligible Claude subscription or API credentials. We do not resell AI model access.'],
            ['What is the difference between managed and unmanaged?', 'Managed plans are set up and maintained by our team, and root access is restricted. Unmanaged plans, such as Docker VPS, give you SSH/root access and you look after the server yourself.'],
            ['Are the prices on the website final?', 'Some plans show indicative INR prices that are confirmed before you order. Plans whose pricing has not been approved say “Pricing to be confirmed” and cannot be ordered yet.'],
            ['How do renewals work?', 'We email you before a renewal is due and create a renewal order for you to pay. Stored payment details are never charged automatically.'],
        ];
    @endphp

    {{-- A. Hero --}}
    <section class="dj-hero" aria-labelledby="hero-title">
        <div class="dj-container dj-hero__grid">
            <div class="dj-hero__copy">
                <p class="dj-eyebrow"><span class="dj-eyebrow__icon"><x-icon name="spark" :size="16" /></span>AI HOSTING &amp; DEVELOPER INFRASTRUCTURE</p>
                <h1 id="hero-title">Run AI agents and developer tools on servers built for you.</h1>
                <p class="dj-lede">DeveloperJet hosts Hermes, n8n, OpenClaw, Claude Code and Docker on dedicated VPS servers. Choose managed setup or full control — with billing, deployment tracking and support in one dashboard.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="#products">Explore Hosting <x-icon name="arrow-right" :size="18" /></a>
                    <a class="dj-btn dj-btn--secondary dj-btn--lg" href="{{ route('plans') }}">View All Plans</a>
                </div>
                <ul class="dj-hero__facts">
                    <li><x-icon name="server" :size="20" /> A dedicated VPS for every customer</li>
                    <li><x-icon name="shield" :size="20" /> Managed or unmanaged access</li>
                    <li><x-icon name="dashboard" :size="20" /> Billing, invoices &amp; support in one place</li>
                </ul>
            </div>
            <figure class="dj-hero__art">
                @include('illustrations.home-hero')
            </figure>
        </div>
    </section>

    {{-- B. Product families --}}
    <section class="dj-section" id="products" aria-labelledby="products-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Products</p>
                    <h2 id="products-title">Everything you need to build and run</h2>
                    <p class="dj-section-head__text">AI agents, automation, developer servers and the people to help you ship — from one account.</p>
                </div>
                <a class="dj-link" href="{{ route('plans') }}">All products and plans <x-icon name="arrow-right" :size="16" /></a>
            </div>
            <div class="dj-family-grid">
                @foreach ($families as $family)
                    <x-product-card :family="$family" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- C. AI All-Access spotlight (coming soon) --}}
    <section class="dj-section dj-section--surface" aria-labelledby="aa-home-title">
        <div class="dj-container dj-spot">
            <div class="dj-spot__copy">
                <p class="dj-eyebrow"><span class="dj-eyebrow__icon"><x-icon name="spark" :size="16" /></span>New · coming soon</p>
                <h2 id="aa-home-title">AI All-Access: your AI models in one workspace</h2>
                <p class="dj-section-head__text">We are building a workspace where you connect API keys from your own AI provider accounts and choose the model for each conversation. Bring your own key — usage is billed by your provider, not by us.</p>
                <ul class="dj-checks">
                    <li><x-icon name="check" :size="18" /> Planned: one chat interface across the providers you connect</li>
                    <li><x-icon name="check" :size="18" /> Planned: keys encrypted, write-only and never emailed</li>
                    <li><x-icon name="check" :size="18" /> Draft Starter, Team and Business plans — pricing to be confirmed</li>
                </ul>
                <p class="dj-spot__fine">Does not include ChatGPT Plus, Claude Pro, Gemini Advanced, Grok or any other AI subscription.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary" href="{{ route('ai-all-access') }}">Explore AI All-Access <x-icon name="arrow-right" :size="16" /></a>
                    <a class="dj-btn dj-btn--secondary" href="{{ route('ai-all-access') }}#plans">Request early access</a>
                </div>
            </div>
            <figure class="dj-spot__art">
                @include('illustrations.ai-all-access', ['alt' => 'Your own API keys from several AI providers connect to one DeveloperJet workspace.'])
            </figure>
        </div>
    </section>

    {{-- D. How DeveloperJet works --}}
    <section class="dj-section" aria-labelledby="how-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">How DeveloperJet works</p>
                <h2 id="how-title">From choosing a plan to using it</h2>
                <p class="dj-section-head__text">Five clear steps. Step 4 is carried out by our team, so delivery is not instant — your dashboard shows progress at every stage.</p>
            </div>
            @include('partials.customer-flow')
        </div>
    </section>

    {{-- E. Platform features --}}
    <section class="dj-section dj-section--navy" aria-labelledby="platform-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow dj-eyebrow--light">Platform</p>
                    <h2 id="platform-title">Built like infrastructure, run like a service</h2>
                </div>
                <p class="dj-section-head__text">One platform for ordering, billing, deployment and support — designed so you always know what you are paying for and where your server stands.</p>
            </div>
            <div class="dj-platform-grid">
                @foreach ($features as [$icon, $title, $text])
                    <article class="dj-platform">
                        <span class="dj-platform__icon"><x-icon :name="$icon" :size="22" /></span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </article>
                @endforeach
                <a class="dj-platform dj-platform--link" href="{{ route('docs.index') }}">
                    <span class="dj-platform__icon"><x-icon name="book" :size="22" /></span>
                    <h3>Documentation</h3>
                    <p>Guides for getting started, billing, custom domains and every product.</p>
                    <span class="dj-cat__cta">Read the docs <x-icon name="arrow-right" :size="16" /></span>
                </a>
            </div>
        </div>
    </section>

    {{-- F. Product comparison --}}
    <section class="dj-section" aria-labelledby="compare-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Choose the right tool</p>
                    <h2 id="compare-title">Which product fits how you work?</h2>
                    <p class="dj-section-head__text">A quick guide to how each product is used, who manages it and what you bring yourself.</p>
                </div>
                <a class="dj-btn dj-btn--secondary" href="{{ route('compare.index') }}">Detailed comparisons</a>
            </div>
            @include('partials.product-matrix', ['keys' => array_keys($matrix['products']), 'current' => null, 'matrix' => $matrix])
            <p class="dj-footnote">Looking for n8n? <a href="{{ route('n8n') }}">n8n Automation Hosting</a> runs workflow automation on its own managed VPS.</p>
        </div>
    </section>

    {{-- G. FAQ --}}
    <section class="dj-section dj-section--surface" aria-labelledby="faq-title">
        <div class="dj-container dj-faq-layout">
            <div class="dj-faq-layout__aside">
                <p class="dj-eyebrow">FAQ</p>
                <h2 id="faq-title">Questions customers ask</h2>
                <p class="dj-section-head__text">Straight answers about delivery, pricing, AI credentials and renewals.</p>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--secondary" href="{{ route('contact') }}"><x-icon name="headset" :size="18" /> Talk to our team</a>
                </div>
            </div>
            <div class="dj-faq">
                @foreach ($faqs as [$q, $a])
                    <details class="dj-faq__item" @if ($loop->first) open @endif>
                        <summary>{{ $q }} <x-icon name="chevron-down" :size="20" /></summary>
                        <p>{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- H. Final CTA --}}
    <x-cta variant="hosting" title="Ready to give your tools a home?" />
@endsection
