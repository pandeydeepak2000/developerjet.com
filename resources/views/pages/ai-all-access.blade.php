@extends('layouts.app')

@section('title', 'AI All-Access — one workspace for your AI models (coming soon)')
@section('meta_description', 'DeveloperJet AI All-Access is a planned workspace for using supported AI models with your own API keys (bring your own key). In development — pricing to be confirmed, not yet available to order.')

@section('content')
    @php
        // Every capability is PLANNED. Nothing on this page is live; do not change a status without a tested release.
        $capabilities = [
            ['browser', 'Unified chat interface', 'One place to chat with the models your keys give you access to, instead of switching between provider websites.'],
            ['layers', 'Provider and model selection', 'Choose the provider and model for each conversation from the providers you have connected.'],
            ['grid', 'Conversation organisation', 'Keep conversations together by project, with search and history.'],
            ['key', 'Provider connection management', 'Add, replace or remove API keys. Keys are write-only once saved and never displayed or emailed.'],
            ['activity', 'Usage visibility', 'See which provider and model each conversation used. Detailed API usage reporting only where a provider’s API reports it.'],
            ['user', 'Team workspace foundation', 'Invite colleagues into a shared workspace with simple roles, for the Team and Business plans.'],
            ['scale', 'Model comparison', 'Send the same prompt to two models side by side. Planned for after the first release.'],
        ];
        $steps = [
            ['key', 'Create an API key with a provider', 'Use your own account with an AI provider that offers official API access. You agree to that provider’s terms and pricing.'],
            ['lock', 'Connect it to your workspace', 'Paste the key once. It is encrypted before it is stored and can only be replaced or removed — never read back.'],
            ['browser', 'Choose a model and start working', 'Pick a provider and model for each conversation. Your prompts go to the provider you selected, using your key.'],
            ['card', 'The provider bills you for usage', 'Model usage is charged by the provider to your own account. DeveloperJet does not resell or mark up model usage.'],
        ];
        $included = [
            'A web workspace for chatting with models from the providers you connect.',
            'Encrypted, write-only storage of your API keys.',
            'Conversation history and organisation.',
            'Team members and roles on the Team and Business plans.',
            'Ticket-based support from the DeveloperJet team.',
        ];
        $notIncluded = [
            'ChatGPT Plus, Claude Pro, Gemini Advanced, Grok or any other consumer AI subscription.',
            'AI model usage or credits — you pay each provider directly through your own API account.',
            'Signing in with your consumer chat accounts. Only official API keys are supported.',
            'A guarantee that every planned provider or model is available at launch. Each integration is marked live only after it is built and tested.',
            'A server or VPS — this is a hosted workspace, not hosting.',
        ];
        $faqs = [
            ['Is AI All-Access available now?', 'No. It is in development. You can request access now; we will contact you when it opens. Nothing is charged and there is nothing to buy yet.'],
            ['Does it include ChatGPT Plus, Claude Pro, Gemini Advanced or Grok?', 'No. AI All-Access does not include, resell or bundle any consumer AI subscription. It is a workspace for using AI models through API keys from your own provider accounts.'],
            ['What does “bring your own key” mean?', 'You create an API key in your own account with an AI provider and connect it to your workspace. Requests are made with your key, and the provider bills you for the usage under its own pricing and terms.'],
            ['Can I sign in with my ChatGPT, Claude or Gemini account instead of a key?', 'No. Consumer chat logins are not a substitute for an authorised API integration, and we will never ask for your provider password.'],
            ['Which providers and models will be supported?', 'We plan to start with ChatGPT (OpenAI), Claude (Anthropic), Gemini (Google) and Grok (xAI), connected only through their official APIs with your own keys. Each one is marked Planned until its integration has been built and tested; none is live today.'],
            ['How are my API keys protected?', 'Keys are encrypted before they are stored, are write-only (they are never displayed again or emailed), and can be replaced or removed at any time. If you think a key is exposed, revoke it at the provider.'],
            ['How much will it cost?', 'Pricing is not confirmed. The Starter, Team and Business plans are drafts; their names, prices and limits will be published here once approved. Model usage is always billed separately by your provider.'],
            ['What happens when I request access?', 'Your request is saved to your DeveloperJet account and our team can see it. You can follow it under Requests in your dashboard. It is not an order and does not commit you to anything.'],
        ];
        $comparisonRows = $plans->flatMap(fn ($p) => array_keys((array) $p->comparison))->unique()->values();
        $firstPlan = $plans->first();
    @endphp

    {{-- 1. Hero --}}
    <section class="dj-phero" aria-labelledby="aa-title">
        <div class="dj-container dj-phero__grid">
            <div class="dj-phero__copy">
                <p class="dj-eyebrow"><span class="dj-eyebrow__icon"><x-icon name="spark" :size="16" /></span>AI ALL-ACCESS · COMING SOON</p>
                <h1 id="aa-title">One workspace for the AI models you already use.</h1>
                <p class="dj-lede">DeveloperJet AI All-Access is a workspace we are building for working with several AI providers in one place. You connect your own API keys — bring your own key — and choose the model for each conversation.</p>
                <ul class="dj-checks">
                    <li><x-icon name="check" :size="18" /> Your keys, your provider accounts, your usage bills</li>
                    <li><x-icon name="check" :size="18" /> Choose the provider and model per conversation</li>
                    <li><x-icon name="check" :size="18" /> Keys encrypted and never shown again</li>
                </ul>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="#plans">Request early access <x-icon name="arrow-right" :size="18" /></a>
                    <a class="dj-btn dj-btn--secondary dj-btn--lg" href="#byok">How bring-your-own-key works</a>
                </div>
                <div class="dj-badges">
                    <span class="dj-badge dj-badge--soon"><x-icon name="clock" :size="15" /> In development — not yet available to order</span>
                    <span class="dj-badge"><x-icon name="key" :size="15" /> No AI subscription included</span>
                </div>
            </div>
            <figure class="dj-phero__art">
                @include('illustrations.ai-all-access')
            </figure>
        </div>
        <nav class="dj-subnav" aria-label="On this page">
            <div class="dj-container">
                <ul>
                    <li><a class="dj-subnav__link" href="#byok">How it works</a></li>
                    <li><a class="dj-subnav__link" href="#providers">Providers</a></li>
                    <li><a class="dj-subnav__link" href="#capabilities">Planned features</a></li>
                    <li><a class="dj-subnav__link" href="#plans">Plans</a></li>
                    <li><a class="dj-subnav__link" href="#included">What's included</a></li>
                    <li><a class="dj-subnav__link" href="#compare">Compare</a></li>
                    <li><a class="dj-subnav__link" href="#faq">FAQ</a></li>
                </ul>
            </div>
        </nav>
    </section>

    {{-- 2. How BYOK works --}}
    <section class="dj-section" id="byok" aria-labelledby="byok-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">Bring your own key</p>
                <h2 id="byok-title">How it will work</h2>
                <p class="dj-section-head__text">You keep the relationship with each AI provider. DeveloperJet provides the workspace around it.</p>
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

    {{-- 2b. Providers (planned integrations — official APIs only) --}}
    @php $aiProviders = config('ai_providers.providers'); @endphp
    <section class="dj-section dj-section--surface" id="providers" aria-labelledby="providers-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Providers</p>
                    <h2 id="providers-title">ChatGPT, Claude, Gemini and Grok — through your own API keys</h2>
                    <p class="dj-section-head__text">These are the providers we plan to support first. Each connection uses the provider’s official API and your own key. None of these integrations is live yet.</p>
                </div>
            </div>
            <div class="dj-provider-grid">
                @foreach ($aiProviders as $key => $p)
                    <article class="dj-provider" data-provider="{{ $key }}">
                        <div class="dj-provider__head">
                            <span class="dj-provider__mark" style="--mark: {{ $p['colour'] }}" aria-hidden="true">{{ $p['monogram'] }}</span>
                            <div>
                                <h3>{{ $p['product'] }}</h3>
                                <p class="dj-provider__company">by {{ $p['company'] }}</p>
                            </div>
                            <span class="dj-cap__status dj-provider__status">Planned</span>
                        </div>
                        <dl class="dj-provider__facts">
                            <div><dt>Connection</dt><dd>{{ $p['api'] }}</dd></div>
                            <div><dt>Your key</dt><dd>{{ $p['key_from'] }}</dd></div>
                            <div><dt>Often chosen for</dt><dd>{{ $p['good_for'] }}</dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>

            <div class="dj-model-choice">
                <div>
                    <h3>Choosing a model</h3>
                    <p>In the workspace you will pick a provider and a model for each conversation. Only providers you have connected appear, and only the models your key can use. Providers differ in a few important ways:</p>
                </div>
                <dl class="dj-model-choice__list">
                    @foreach (config('ai_providers.differences') as [$term, $text])
                        <div><dt>{{ $term }}</dt><dd>{{ $text }}</dd></div>
                    @endforeach
                </dl>
            </div>
            <p class="dj-fineprint">ChatGPT and OpenAI, Claude and Anthropic, Gemini and Google, and Grok and xAI are trademarks of their respective owners. They are named only to identify the services you can connect; DeveloperJet is not affiliated with or endorsed by them. Consumer subscriptions such as ChatGPT Plus or Claude Pro are not included and cannot be used in place of an API key.</p>
        </div>
    </section>

    {{-- 3. Planned capabilities --}}
    <section class="dj-section" id="capabilities" aria-labelledby="cap-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Planned features</p>
                    <h2 id="cap-title">What we are building</h2>
                    <p class="dj-section-head__text">Every item below is planned and not yet live. Each one moves to “Available” only after it has been built and tested.</p>
                </div>
            </div>
            <div class="dj-cap-grid">
                @foreach ($capabilities as [$icon, $title, $text])
                    <article class="dj-cap">
                        <div class="dj-cap__top">
                            <span class="dj-cap__icon"><x-icon :name="$icon" :size="20" /></span>
                            <span class="dj-cap__status">Planned</span>
                        </div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 4. Plans --}}
    <section class="dj-section" id="plans" aria-labelledby="plans-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">Plans</p>
                <h2 id="plans-title">Draft plans</h2>
                <p class="dj-section-head__text">Three plans are planned for individuals, teams and organisations. Names, limits and prices are drafts and will change.</p>
            </div>
            <p class="dj-price-note dj-price-note--soon"><x-icon name="info" :size="18" /> <span><strong>Pricing to be confirmed.</strong> AI All-Access cannot be bought yet. “Request access” saves your interest to your account — nothing is charged. Model usage is always billed by your own AI provider.</span></p>
            <x-plan-grid :count="$plans->count()" label="AI All-Access plans">
                @forelse ($plans as $plan)
                    <x-plan-card :plan="$plan" product="AI All-Access" :toggle="false" :extras="[]" />
                @empty
                    <p class="dj-muted">Plans will be published here. <a href="{{ route('contact') }}">Contact our team</a> to register interest.</p>
                @endforelse
            </x-plan-grid>

            @if ($plans->count() > 1 && $comparisonRows->isNotEmpty())
                <div class="dj-table-wrap dj-plan-compare" role="region" aria-label="Compare AI All-Access plans" tabindex="0">
                    <table class="dj-table">
                        <caption class="sr-only">Compare AI All-Access draft plans</caption>
                        <thead><tr><th scope="col"><span class="sr-only">Detail</span></th>@foreach ($plans as $plan)<th scope="col">{{ $plan->name }}</th>@endforeach</tr></thead>
                        <tbody>
                            <tr><th scope="row">Price</th>@foreach ($plans as $plan)<td>Pricing to be confirmed</td>@endforeach</tr>
                            @foreach ($comparisonRows as $row)
                                <tr><th scope="row">{{ $row }}</th>@foreach ($plans as $plan)<td>{{ ($plan->comparison[$row] ?? '') !== '' ? $plan->comparison[$row] : '—' }}</td>@endforeach</tr>
                            @endforeach
                            <tr><th scope="row">Availability</th>@foreach ($plans as $plan)<td>Coming soon</td>@endforeach</tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    {{-- 5. Included / not included --}}
    <section class="dj-section dj-section--surface" id="included" aria-labelledby="included-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">Scope</p>
                <h2 id="included-title">What AI All-Access is — and is not</h2>
            </div>
            <div class="dj-scope">
                <article class="dj-scope__col dj-scope__col--yes">
                    <h3><x-icon name="check" :size="22" /> Planned to be included</h3>
                    <ul>@foreach ($included as $i)<li><x-icon name="check" :size="18" /> {{ $i }}</li>@endforeach</ul>
                </article>
                <article class="dj-scope__col dj-scope__col--no">
                    <h3><x-icon name="x" :size="22" /> Not included</h3>
                    <ul>@foreach ($notIncluded as $i)<li><x-icon name="x" :size="18" /> {{ $i }}</li>@endforeach</ul>
                </article>
            </div>
        </div>
    </section>

    {{-- 6. Compare with other DeveloperJet products --}}
    <section class="dj-section" id="compare" aria-labelledby="compare-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Compare</p>
                    <h2 id="compare-title">AI All-Access or a hosted AI agent?</h2>
                    <p class="dj-section-head__text">AI All-Access is a shared web workspace with no server to look after. If you want an AI agent running on a VPS of your own, choose a hosted product instead.</p>
                </div>
                <a class="dj-link" href="{{ route('compare.index') }}">All comparisons <x-icon name="arrow-right" :size="16" /></a>
            </div>
            @include('partials.product-matrix', ['keys' => ['ai-all-access', 'hermes-workspace', 'openclaw', 'claude-code'], 'current' => 'ai-all-access', 'matrix' => $matrix])
        </div>
    </section>

    {{-- 7. FAQ --}}
    <section class="dj-section dj-section--surface" id="faq" aria-labelledby="faq-title">
        <div class="dj-container dj-faq-layout">
            <div class="dj-faq-layout__aside">
                <p class="dj-eyebrow">FAQ</p>
                <h2 id="faq-title">Questions about AI All-Access</h2>
                <p class="dj-section-head__text">Straight answers about subscriptions, keys, providers and pricing.</p>
                <div class="dj-actions">
                    <a class="dj-link" href="{{ route('docs.show', 'ai-all-access') }}">Read the AI All-Access guide <x-icon name="arrow-right" :size="16" /></a>
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

    {{-- 8. CTA --}}
    <x-cta variant="ai" :primary-url="$firstPlan ? route('services.request.create', $firstPlan) : route('contact')" />
@endsection
