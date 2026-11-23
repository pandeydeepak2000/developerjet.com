@extends('layouts.app')

@section('title', 'Business Automation — custom workflow automation for your business')
@section('meta_description', 'DeveloperJet Business Automation: custom lead capture, CRM, WhatsApp, email, reporting and n8n workflow automation, scoped and quoted for your business before any work starts.')

@section('content')
    @php
        // Copy rule: these are services we can scope and build — NOT integrations already running for customers.
        // No prices, deliverables, timelines or results are promised here; plans and prices come from Admin → Plans.
        $problems = [
            ['clock', 'Repetitive manual work', 'Copying data between tools, chasing the same follow-ups and sending the same messages by hand.'],
            ['user', 'Leads that slip through', 'Enquiries arrive by form, email and WhatsApp and are not always answered or recorded.'],
            ['grid', 'Disconnected software', 'Your CRM, accounts, store and spreadsheets do not share information with each other.'],
            ['activity', 'Reports that take hours', 'Pulling numbers together every week or month instead of having them prepared for you.'],
        ];
        $industries = [
            ['Retail & e-commerce', 'Order updates, stock alerts and customer follow-ups.'],
            ['Healthcare & clinics', 'Appointment reminders and patient enquiry routing.'],
            ['Education & coaching', 'Admissions enquiries, batch reminders and fee notifications.'],
            ['Real estate', 'Lead capture from portals and timely site-visit follow-ups.'],
            ['Agencies & services', 'Client onboarding, reporting and invoice reminders.'],
            ['Manufacturing & distribution', 'Order processing, dealer updates and data sync.'],
        ];
        $offerings = [
            ['user', 'Lead Capture Automation'], ['chat', 'WhatsApp Workflow Integration'], ['grid', 'CRM Automation'],
            ['mail', 'Email Marketing Workflows'], ['headset', 'Customer Support Automation'], ['clock', 'Appointment Reminders'],
            ['arrow-right', 'Sales Follow-up Automation'], ['card', 'Invoice and Payment Notifications'], ['cart', 'E-commerce Order Automation'],
            ['activity', 'Business Reporting'], ['layers', 'Data Synchronization'], ['spark', 'AI Chatbot Integration'],
            ['code', 'Custom API Integrations'], ['workflow', 'n8n Workflow Development'],
        ];
        $steps = [
            ['mail', 'Share your requirements', 'Fill in the form below. No payment, account or technical detail is needed to start.'],
            ['chat', 'Discovery conversation', 'Our team reviews your request and talks it through with you to understand your tools and process.'],
            ['book', 'Scoped quotation', 'You receive a written scope and quotation. Nothing is charged at this stage.'],
            ['rocket', 'Build, test and hand over', 'Once you accept and the agreed invoice is paid, the work becomes a tracked project in your dashboard.'],
        ];
        $faqs = [
            ['Do I have to pay to submit a request?', 'No. Submitting requirements is free and does not create an order. You only pay if you accept a quotation and then pay the invoice our team sends you.'],
            ['Are these automations ready-made?', 'No. Each automation is scoped and built for your business. The examples on this page are services we can deliver, not integrations already running for you.'],
            ['What does it cost?', 'Every project is quoted after we understand your requirements. The three plans below are draft starting points; prices are published only once they are approved.'],
            ['Which tools can you connect?', 'Many business tools offer APIs or integrations, for example CRMs, email platforms, WhatsApp Business providers, payment gateways and spreadsheets. We confirm what is possible with your specific tools during discovery.'],
            ['Should I send my passwords or API keys?', 'Never. Do not put passwords, API keys or other credentials in this form or in email. If access is needed later, our team will arrange it securely with you.'],
            ['Do I need an account?', 'No. Guests can submit a request. If you are signed in, the request also appears in your dashboard so you can follow updates and review the quotation there.'],
            ['Can you host the automations?', 'Yes, where it suits the project — for example n8n workflows can run on DeveloperJet n8n Automation Hosting. We recommend hosting as part of the quotation.'],
        ];
        $c = fn ($k, $d = '') => old($k, $prefill[$k] ?? $d);
    @endphp

    {{-- 1. Hero --}}
    <section class="dj-phero dj-ba-hero" aria-labelledby="ba-title">
        <div class="dj-container dj-phero__grid">
            <div class="dj-phero__copy">
                <p class="dj-eyebrow"><span class="dj-eyebrow__icon"><x-icon name="workflow" :size="16" /></span>DIGITAL SERVICES · BUSINESS AUTOMATION</p>
                <h1 id="ba-title">Automate the busywork. Keep your team on the work that matters.</h1>
                <p class="dj-lede">We design and build custom automations that connect your leads, customers, software and reports — scoped for your business and quoted before any work begins.</p>
                <ul class="dj-checks">
                    <li><x-icon name="check" :size="18" /> Custom workflows for businesses of any size</li>
                    <li><x-icon name="check" :size="18" /> Written scope and quotation before you commit</li>
                    <li><x-icon name="check" :size="18" /> No payment needed to share your requirements</li>
                </ul>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="#request">Share your requirements <x-icon name="arrow-right" :size="18" /></a>
                    <a class="dj-btn dj-btn--secondary dj-btn--lg" href="#examples">See what we can automate</a>
                </div>
            </div>
            <figure class="dj-phero__art dj-ba-art" aria-hidden="true">
                <div class="dj-ba-flow">
                    <span class="dj-ba-node"><x-icon name="user" :size="20" /> New enquiry</span>
                    <span class="dj-ba-link"></span>
                    <span class="dj-ba-node dj-ba-node--accent"><x-icon name="workflow" :size="20" /> Your workflow</span>
                    <span class="dj-ba-link"></span>
                    <span class="dj-ba-split">
                        <span class="dj-ba-node"><x-icon name="grid" :size="18" /> CRM updated</span>
                        <span class="dj-ba-node"><x-icon name="chat" :size="18" /> Team notified</span>
                        <span class="dj-ba-node"><x-icon name="mail" :size="18" /> Customer replied</span>
                    </span>
                </div>
            </figure>
        </div>
        <nav class="dj-subnav" aria-label="On this page">
            <div class="dj-container">
                <ul>
                    <li><a class="dj-subnav__link" href="#problems">Problems we solve</a></li>
                    <li><a class="dj-subnav__link" href="#industries">Industries</a></li>
                    <li><a class="dj-subnav__link" href="#examples">Examples</a></li>
                    <li><a class="dj-subnav__link" href="#plans">Plans</a></li>
                    <li><a class="dj-subnav__link" href="#how">How it works</a></li>
                    <li><a class="dj-subnav__link" href="#request">Request</a></li>
                    <li><a class="dj-subnav__link" href="#faq">FAQ</a></li>
                </ul>
            </div>
        </nav>
    </section>

    {{-- 2. Problems --}}
    <section class="dj-section" id="problems" aria-labelledby="problems-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">Business problems we solve</p>
                <h2 id="problems-title">Where automation usually helps first</h2>
                <p class="dj-section-head__text">If any of these sound familiar, there is probably a workflow worth automating.</p>
            </div>
            <div class="dj-cap-grid">
                @foreach ($problems as [$icon, $title, $text])
                    <article class="dj-cap">
                        <div class="dj-cap__top"><span class="dj-cap__icon"><x-icon :name="$icon" :size="20" /></span></div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 3. Industries --}}
    <section class="dj-section dj-section--surface" id="industries" aria-labelledby="industries-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">Industry use cases</p>
                <h2 id="industries-title">Built around how your industry works</h2>
                <p class="dj-section-head__text">Typical starting points by industry. Your own process decides what we actually build.</p>
            </div>
            <ul class="dj-ba-industries">
                @foreach ($industries as [$title, $text])
                    <li><strong>{{ $title }}</strong><span>{{ $text }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 4. Automation examples (possible service offerings) --}}
    <section class="dj-section" id="examples" aria-labelledby="examples-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Automation examples</p>
                    <h2 id="examples-title">What we can build for you</h2>
                    <p class="dj-section-head__text">Possible service offerings. Each is scoped for your tools and confirmed in your quotation — none is a ready-made integration.</p>
                </div>
            </div>
            <ul class="dj-ba-offerings">
                @foreach ($offerings as [$icon, $title])
                    <li><span class="dj-ba-offerings__icon"><x-icon :name="$icon" :size="18" /></span>{{ $title }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 5. Plans (draft, from Admin → Plans) --}}
    <section class="dj-section dj-section--surface" id="plans" aria-labelledby="plans-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">Service plans</p>
                <h2 id="plans-title">Three ways to start</h2>
                <p class="dj-section-head__text">Draft plans to help you choose a starting point. Scope and price are always confirmed in a written quotation.</p>
            </div>
            <p class="dj-price-note"><x-icon name="info" :size="18" /> <span><strong>Request a quote.</strong> Automation work is custom-scoped, so prices are agreed with you before anything starts. Nothing is charged until you accept a quotation and pay its invoice.</span></p>
            <div class="dj-plan-grid dj-plan-grid--{{ min(max($plans->count(), 1), 3) }}">
                @forelse ($plans as $plan)
                    <x-plan-card :plan="$plan" product="Business Automation" :toggle="false" :quote-url="route('business-automation', ['plan' => $plan->slug]).'#request'" />
                @empty
                    <p class="dj-muted">Plans will be published here. Use the form below to request a quote.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- 6. How it works --}}
    <section class="dj-section" id="how" aria-labelledby="how-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">How it works</p>
                <h2 id="how-title">From requirements to a working automation</h2>
            </div>
            <ol class="dj-flow dj-flow--four">
                @foreach ($steps as $i => [$icon, $title, $text])
                    <li class="dj-flow__step">
                        <span class="dj-flow__icon"><x-icon :name="$icon" :size="22" /></span>
                        <span class="dj-flow__num">Step {{ $i + 1 }}</span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 7. Requirement form --}}
    <section class="dj-section dj-section--surface" id="request" aria-labelledby="request-title">
        <div class="dj-container dj-ba-form-layout">
            <div class="dj-ba-form-aside">
                <p class="dj-eyebrow">Requirement form</p>
                <h2 id="request-title">Tell us what you want to automate</h2>
                <p class="dj-section-head__text">A few sentences are enough. We reply with questions or a proposed scope — no payment, no obligation.</p>
                <ul class="dj-checks">
                    <li><x-icon name="check" :size="18" /> You get a request reference straight away</li>
                    <li><x-icon name="check" :size="18" /> Our sales team reviews every request</li>
                    <li><x-icon name="lock" :size="18" /> Never include passwords, API keys or credentials</li>
                </ul>
                @auth
                    <p class="dj-muted small">Signed in as {{ auth()->user()->email }} — this request will also appear in your dashboard.</p>
                @endauth
            </div>

            <form class="dj-ba-form" method="POST" action="{{ route('business-automation.store') }}" novalidate data-loading-form>
                @csrf
                <input type="hidden" name="form_token" value="{{ $formToken }}">
                <div class="dj-ba-hp" aria-hidden="true"><label>Fax number <input type="text" name="fax_number" tabindex="-1" autocomplete="off"></label></div>

                @if ($errors->has('form'))<div class="flash flash-error" role="alert">{{ $errors->first('form') }}</div>@endif
                @if ($errors->any() && ! $errors->has('form'))<div class="flash flash-error" role="alert">Please check the highlighted fields.</div>@endif

                <fieldset>
                    <legend>Your contact details</legend>
                    <div class="dj-ba-grid">
                        <div class="dj-ba-field">
                            <label for="ba-name">Your name <span class="req" aria-hidden="true">*</span></label>
                            <input id="ba-name" name="name" type="text" required maxlength="120" autocomplete="name" value="{{ $c('name') }}" @error('name') aria-invalid="true" aria-describedby="err-name" @enderror>
                            @error('name')<p class="field-error" id="err-name">{{ $message }}</p>@enderror
                        </div>
                        <div class="dj-ba-field">
                            <label for="ba-email">Email address <span class="req" aria-hidden="true">*</span></label>
                            <input id="ba-email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ $c('email') }}" @error('email') aria-invalid="true" aria-describedby="err-email" @enderror>
                            @error('email')<p class="field-error" id="err-email">{{ $message }}</p>@enderror
                        </div>
                        <div class="dj-ba-field dj-ba-field--wide">
                            @include('partials.phone-field', ['value' => $prefill['phone'] ?? null, 'idPrefix' => 'ba'])
                        </div>
                        <div class="dj-ba-field">
                            <label for="ba-company">Company name <span class="opt">(optional)</span></label>
                            <input id="ba-company" name="company" type="text" maxlength="150" autocomplete="organization" value="{{ old('company') }}">
                            @error('company')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="dj-ba-field">
                            <label for="ba-website">Existing website <span class="opt">(optional)</span></label>
                            <input id="ba-website" name="website" type="url" maxlength="200" inputmode="url" placeholder="https://" value="{{ old('website') }}" @error('website') aria-invalid="true" aria-describedby="err-website" @enderror>
                            @error('website')<p class="field-error" id="err-website">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Your business</legend>
                    <div class="dj-ba-grid">
                        <div class="dj-ba-field">
                            <label for="ba-business-type">Business type <span class="req" aria-hidden="true">*</span></label>
                            <input id="ba-business-type" name="business_type" type="text" required maxlength="120" placeholder="e.g. Dental clinic, online store" value="{{ old('business_type') }}" @error('business_type') aria-invalid="true" aria-describedby="err-bt" @enderror>
                            @error('business_type')<p class="field-error" id="err-bt">{{ $message }}</p>@enderror
                        </div>
                        <div class="dj-ba-field">
                            <label for="ba-size">Business size <span class="opt">(optional)</span></label>
                            <select id="ba-size" name="business_size">
                                <option value="">Select…</option>
                                @foreach (\App\Models\AutomationRequest::BUSINESS_SIZES as $k => $l)<option value="{{ $k }}" @selected(old('business_size') === $k)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="dj-ba-field dj-ba-field--wide">
                            <label for="ba-tools">Current software or tools <span class="opt">(optional)</span></label>
                            <textarea id="ba-tools" name="current_tools" rows="2" maxlength="1500" placeholder="e.g. Zoho CRM, Google Sheets, Shopify, Tally">{{ old('current_tools') }}</textarea>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Your automation</legend>
                    <div class="dj-ba-grid">
                        <div class="dj-ba-field dj-ba-field--wide">
                            <label for="ba-req">Automation requirements <span class="req" aria-hidden="true">*</span></label>
                            <textarea id="ba-req" name="requirements" rows="6" required minlength="20" maxlength="5000" aria-describedby="ba-req-hint @error('requirements') err-req @enderror" @error('requirements') aria-invalid="true" @enderror>{{ old('requirements') }}</textarea>
                            <p class="field-hint" id="ba-req-hint">What happens today, and what should happen automatically? Do not include passwords or API keys.</p>
                            @error('requirements')<p class="field-error" id="err-req">{{ $message }}</p>@enderror
                        </div>
                        <div class="dj-ba-field dj-ba-field--wide">
                            <label for="ba-integrations">Services to integrate <span class="opt">(optional)</span></label>
                            <textarea id="ba-integrations" name="integrations" rows="2" maxlength="1500" placeholder="e.g. WhatsApp Business, Razorpay, Gmail">{{ old('integrations') }}</textarea>
                        </div>
                        <div class="dj-ba-field">
                            <label for="ba-timeline">Preferred timeline <span class="opt">(optional)</span></label>
                            <select id="ba-timeline" name="timeline">
                                <option value="">Select…</option>
                                @foreach (\App\Models\AutomationRequest::TIMELINES as $k => $l)<option value="{{ $k }}" @selected(old('timeline') === $k)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="dj-ba-field">
                            <label for="ba-budget">Estimated budget <span class="opt">(optional)</span></label>
                            <select id="ba-budget" name="budget">
                                <option value="">Select…</option>
                                @foreach (\App\Models\AutomationRequest::BUDGETS as $k => $l)<option value="{{ $k }}" @selected(old('budget') === $k)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        @if ($plans->isNotEmpty())
                            <div class="dj-ba-field dj-ba-field--wide">
                                <label for="ba-plan">Plan you are interested in <span class="opt">(optional)</span></label>
                                <select id="ba-plan" name="plan">
                                    <option value="">Not sure yet</option>
                                    @foreach ($plans as $p)<option value="{{ $p->slug }}" @selected(old('plan', $selectedPlan) === $p->slug)>{{ $p->name }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                        <div class="dj-ba-field dj-ba-field--wide">
                            <label for="ba-notes">Additional notes <span class="opt">(optional)</span></label>
                            <textarea id="ba-notes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </fieldset>

                <label class="dj-ba-consent">
                    <input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
                    <span>I have not included passwords, API keys or other credentials, and I agree to be contacted about this request (<a href="{{ route('privacy') }}">privacy policy</a>).</span>
                </label>
                @error('consent')<p class="field-error">{{ $message }}</p>@enderror

                <button class="dj-btn dj-btn--primary dj-btn--lg dj-ba-submit" type="submit"><span class="btn-label">Submit requirements</span><span class="btn-spinner" aria-hidden="true"></span></button>
                <p class="dj-muted small">Submitting does not create an order or charge anything.</p>
            </form>
        </div>
    </section>

    {{-- 8. FAQ --}}
    <section class="dj-section" id="faq" aria-labelledby="faq-title">
        <div class="dj-container dj-faq-layout">
            <div class="dj-faq-layout__aside">
                <p class="dj-eyebrow">FAQ</p>
                <h2 id="faq-title">Questions about Business Automation</h2>
                <p class="dj-section-head__text">How requests, quotations and payments work.</p>
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

    {{-- 9. Contact Sales CTA --}}
    <x-cta variant="services" eyebrow="Business Automation" title="Prefer to talk it through first?"
        text="Call or message our sales team and we will help you shape the requirement."
        :primary-url="route('business-automation').'#request'" primary-label="Request a Quote"
        :secondary-url="route('contact')" secondary-label="Contact Sales" />
@endsection
