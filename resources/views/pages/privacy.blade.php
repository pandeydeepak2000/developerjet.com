@extends('layouts.app')

@section('title', 'Privacy Policy')
@section('meta_description', 'Learn how DeveloperJet handles information when you browse our website or contact our team.')

@section('content')
    <section class="legal-hero section-shell">
        <span class="eyebrow">Your information matters</span>
        <h1>Privacy, with <span>care.</span></h1>
        <p>This page explains what information we may handle when you visit DeveloperJet or get in touch with us.</p>
        <span class="legal-updated">Last updated: October 2026</span>
    </section>

    <section class="privacy-principles section-shell">
        <div class="privacy-note"><span class="privacy-note-icon">◉</span><span>OUR SIMPLE RULE</span><h2>Only what helps us<br>help you.</h2><p>We aim to collect the minimum information needed to respond, provide a service, and make the experience better.</p></div>
        <div class="privacy-list">
            <article><span>01</span><div><h3>What you share</h3><p>If you contact us, we may receive your phone number, message, and any details you choose to share about your needs.</p></div></article>
            <article><span>02</span><div><h3>Why we use it</h3><p>We use information to reply to questions, discuss requested services, provide support, and maintain or improve our website.</p></div></article>
            <article><span>03</span><div><h3>When it’s shared</h3><p>We do not sell personal information. Information may be shared with service providers when needed to operate a service or when required by law.</p></div></article>
            <article><span>04</span><div><h3>Your choices</h3><p>You can ask us about information you have shared or request that we update or delete it, subject to legal and operational requirements.</p></div></article>
        </div>
    </section>

    <section class="legal-content privacy-details section-shell">
        <div class="legal-content-heading"><span class="eyebrow">A little more detail</span><h2>Keep it clear.<br><span>Keep it secure.</span></h2></div>
        <div class="legal-copy">
            <h3>Cookies and site data</h3>
            <p>Our website may use essential technologies needed for basic operation. If analytics or other optional technologies are introduced, this policy should be updated to explain them and any choices available to visitors.</p>
            <h3>Retention and security</h3>
            <p>We keep information only as long as reasonably needed for the purpose it was collected, subject to applicable requirements. We use reasonable safeguards, though no method of transmission or storage can be guaranteed completely secure.</p>
            <h3>Third-party services</h3>
            <p>Links and integrations may take you to services operated by others. Their privacy practices are governed by their own policies, which we encourage you to review.</p>
            <h3>Contact us</h3>
            <p>For privacy questions or requests, <a href="{{ route('contact') }}">contact our team</a>. We may update this policy as our services change and will revise the date above when we do.</p>
        </div>
    </section>

    <x-page-details
        eyebrow="Your information, plainly explained"
        title="A quick privacy check."
        description="A short overview of how to get in touch about information you have shared."
        :items="[
            ['label' => 'YOU CHOOSE', 'title' => 'Share only what you need', 'description' => 'You decide what details to include when you contact us about a product or support question.'],
            ['label' => 'WE RESPOND', 'title' => 'Information helps us reply', 'description' => 'We use your message and contact details to respond and discuss the service you asked about.'],
            ['label' => 'YOUR REQUEST', 'title' => 'Ask us to review your details', 'description' => 'Contact our team if you have a question or want to request an update to information you shared.'],
        ]"
        cta-title="Have a question about your information?"
        cta-description="Reach our team with a privacy request or question."
        cta-label="Contact our team"
        :cta-url="route('contact')"
    />
@endsection
