@extends('layouts.app')

@section('title', 'AI All-Access — one API key for GPT, Claude, Gemini and more')
@section('meta_description', 'DeveloperJet AI All-Access: one OpenAI- and Anthropic-compatible API key for GPT, Claude, Gemini, DeepSeek, Qwen and more, with a monthly usage credit. Works with Claude Code, Cursor and n8n. Plans from ₹999 a month.')

@section('content')
    @php
        $steps = [
            ['grid', 'Choose a plan', 'Pick the monthly usage credit that fits how much you use AI models.'],
            ['card', 'Pay securely', 'Razorpay or your DeveloperJet wallet. GST invoice in your dashboard.'],
            ['key', 'Get your API key', 'Our team creates your key with your plan\'s credit. It appears in your dashboard with the base URL.'],
            ['terminal', 'Use any model', 'Paste the key into Claude Code, Cursor, n8n or your own code and pick a model.'],
        ];
        $models = [
            ['GPT', 'OpenAI', '#10A37F'], ['Claude', 'Anthropic', '#D97757'], ['Gemini', 'Google', '#4285F4'],
            ['DeepSeek', 'DeepSeek', '#4D6BFE'], ['Qwen', 'Alibaba', '#6F42C1'], ['Grok', 'xAI', '#111827'],
            ['Kimi', 'Moonshot AI', '#0EA5E9'], ['GLM', 'Zhipu AI', '#2563EB'], ['MiniMax', 'MiniMax', '#DB2777'],
        ];
        $tools = [
            ['terminal', 'Claude Code', 'Set the Anthropic base URL and your key.'],
            ['code', 'Cursor & VS Code tools', 'Add an OpenAI-compatible provider.'],
            ['workflow', 'n8n', 'OpenAI credential with a custom base URL.'],
            ['layers', 'OpenAI & Anthropic SDKs', 'Change base_url — nothing else.'],
            ['spark', 'Hermes Agent & OpenClaw', 'Point them at the same endpoint.'],
            ['browser', 'LibreChat, Continue & more', 'Any app that speaks the OpenAI API.'],
        ];
        $included = [
            'One API key that works with models from many providers.',
            'A monthly usage credit, sized by your plan.',
            'OpenAI- and Anthropic-compatible endpoints.',
            'Your key, base URL and setup steps in your dashboard.',
            'GST invoice and ticket support from the DeveloperJet team.',
        ];
        $notIncluded = [
            'ChatGPT Plus, Claude Pro, Gemini Advanced or any consumer chat subscription.',
            'Unlimited usage — requests stop when the monthly credit is used up (top-ups on request).',
            'A server or VPS — for that, choose one of our hosting products.',
            'A guarantee that every model is always available; model lists change over time.',
        ];
        $faqs = [
            ['What exactly do I get?', 'One API key plus a base URL. With it you can call GPT, Claude, Gemini, DeepSeek, Qwen and other models from your own tools, up to your plan\'s monthly usage credit.'],
            ['Is this the same as ChatGPT Plus or Claude Pro?', 'No. It is API access for tools and code, not a consumer chat subscription. You use it in apps like Claude Code, Cursor and n8n, or in your own software.'],
            ['How does the monthly credit work?', 'Each request uses a little of your credit; bigger models and longer answers cost more. When the credit is used up, requests stop until your plan renews — ask us any time for a top-up or a bigger plan.'],
            ['How fast do I get my key?', 'After payment our team creates your key with your plan\'s credit. It appears in your dashboard, usually within a few business hours, and we email you when it is ready.'],
            ['Can I use it with Claude Code?', 'Yes. Set ANTHROPIC_BASE_URL and ANTHROPIC_AUTH_TOKEN as shown in your dashboard, then run claude.'],
            ['Is GST charged?', 'Yes, 18% GST applies to online payments, as on all DeveloperJet plans. Paying fully from your wallet follows the wallet rules shown at checkout.'],
            ['Can I cancel?', 'Yes. Plans renew monthly or yearly and you can cancel future renewals from your dashboard. Used credit is not refundable.'],
        ];
        $comparisonRows = $plans->flatMap(fn ($p) => array_keys((array) $p->comparison))->unique()->values();
    @endphp

    {{-- 1. Hero --}}
    <section class="dj-phero" aria-labelledby="aa-title">
        <div class="dj-container dj-phero__grid">
            <div class="dj-phero__copy">
                <p class="dj-eyebrow"><span class="dj-eyebrow__icon"><x-icon name="spark" :size="16" /></span>AI ALL-ACCESS</p>
                <h1 id="aa-title">One API key for every top AI model.</h1>
                <p class="dj-lede">GPT, Claude, Gemini, DeepSeek, Qwen and more through a single key, with a monthly usage credit. No separate accounts with every provider — just plug the key into Claude Code, Cursor, n8n or your own code.</p>
                <ul class="dj-checks">
                    <li><x-icon name="check" :size="18" /> OpenAI- and Anthropic-compatible API</li>
                    <li><x-icon name="check" :size="18" /> Monthly usage credit from ₹999</li>
                    <li><x-icon name="check" :size="18" /> Key, base URL and setup steps in your dashboard</li>
                </ul>
                <div class="dj-actions">
                    <a class="dj-btn dj-btn--primary dj-btn--lg" href="#plans">See plans <x-icon name="arrow-right" :size="18" /></a>
                    <a class="dj-btn dj-btn--secondary dj-btn--lg" href="#how">How it works</a>
                </div>
                <div class="dj-badges">
                    <span class="dj-badge"><x-icon name="key" :size="15" /> One key · many models</span>
                    <span class="dj-badge"><x-icon name="clock" :size="15" /> Key delivered after payment — not instant</span>
                </div>
            </div>
            <figure class="dj-phero__art">
                @include('illustrations.ai-all-access')
            </figure>
        </div>
        <nav class="dj-subnav" aria-label="On this page">
            <div class="dj-container">
                <ul>
                    <li><a class="dj-subnav__link" href="#how">How it works</a></li>
                    <li><a class="dj-subnav__link" href="#models">Models</a></li>
                    <li><a class="dj-subnav__link" href="#tools">Works with</a></li>
                    <li><a class="dj-subnav__link" href="#plans">Plans</a></li>
                    <li><a class="dj-subnav__link" href="#included">What's included</a></li>
                    <li><a class="dj-subnav__link" href="#compare">Compare</a></li>
                    <li><a class="dj-subnav__link" href="#faq">FAQ</a></li>
                </ul>
            </div>
        </nav>
    </section>

    {{-- 2. How it works --}}
    <section class="dj-section" id="how" aria-labelledby="how-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">How it works</p>
                <h2 id="how-title">From order to your first request</h2>
                <p class="dj-section-head__text">You choose the credit; we set up the key; you use it anywhere.</p>
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

    {{-- 3. Models --}}
    <section class="dj-section dj-section--surface" id="models" aria-labelledby="models-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Models</p>
                    <h2 id="models-title">The models developers actually use</h2>
                    <p class="dj-section-head__text">Pick the model per request — a fast, cheap one for simple jobs, a top model for hard ones. All with the same key.</p>
                </div>
            </div>
            <div class="dj-provider-grid">
                @foreach ($models as [$model, $company, $colour])
                    <article class="dj-provider">
                        <div class="dj-provider__head">
                            <span class="dj-provider__mark" style="--mark: {{ $colour }}" aria-hidden="true">{{ mb_substr($model, 0, 1) }}</span>
                            <div>
                                <h3>{{ $model }}</h3>
                                <p class="dj-provider__company">by {{ $company }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="dj-fineprint">Model availability depends on the providers and can change over time. Names such as GPT/OpenAI, Claude/Anthropic, Gemini/Google and Grok/xAI are trademarks of their owners, used only to identify the models; DeveloperJet is not affiliated with or endorsed by them.</p>
        </div>
    </section>

    {{-- 4. Works with --}}
    <section class="dj-section" id="tools" aria-labelledby="tools-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Works with</p>
                    <h2 id="tools-title">Plug it into the tools you already use</h2>
                    <p class="dj-section-head__text">Two settings — a base URL and your key. Your dashboard shows copy-paste steps for each tool.</p>
                </div>
            </div>
            <div class="dj-cap-grid">
                @foreach ($tools as [$icon, $title, $text])
                    <article class="dj-cap">
                        <div class="dj-cap__top"><span class="dj-cap__icon"><x-icon :name="$icon" :size="20" /></span></div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 5. Plans --}}
    <section class="dj-section dj-section--surface" id="plans" aria-labelledby="plans-title">
        <div class="dj-container">
            <div class="dj-section-head">
                <p class="dj-eyebrow">Plans</p>
                <h2 id="plans-title">Choose your monthly AI credit</h2>
                <p class="dj-section-head__text">Every plan includes every model. Bigger plans add more credit and more keys for your team.</p>
            </div>
            <x-plan-grid :count="$plans->count()" label="AI All-Access plans">
                @forelse ($plans as $plan)
                    <x-plan-card :plan="$plan" product="AI All-Access" :toggle="true" :extras="[]" />
                @empty
                    <p class="dj-muted">Plans will be published here. <a href="{{ route('contact') }}">Contact our team</a>.</p>
                @endforelse
            </x-plan-grid>

            @if ($plans->count() > 1 && $comparisonRows->isNotEmpty())
                <div class="dj-table-wrap dj-plan-compare" role="region" aria-label="Compare AI All-Access plans" tabindex="0">
                    <table class="dj-table">
                        <caption class="sr-only">Compare AI All-Access plans</caption>
                        <thead><tr><th scope="col"><span class="sr-only">Detail</span></th>@foreach ($plans as $plan)<th scope="col">{{ $plan->name }}</th>@endforeach</tr></thead>
                        <tbody>
                            <tr><th scope="row">Price</th>@foreach ($plans as $plan)<td>{{ $plan->priceFor('monthly') ? \App\Support\Money::inr($plan->priceFor('monthly')).' / month' : '—' }}</td>@endforeach</tr>
                            @foreach ($comparisonRows as $row)
                                <tr><th scope="row">{{ $row }}</th>@foreach ($plans as $plan)<td>{{ ($plan->comparison[$row] ?? '') !== '' ? $plan->comparison[$row] : '—' }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <p class="dj-fineprint">Prices exclude GST. Credit is shown in US dollars because model usage is priced in dollars. Yearly billing is 12 × the monthly price.</p>
        </div>
    </section>

    {{-- 6. Included / not included --}}
    <section class="dj-section" id="included" aria-labelledby="included-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--center">
                <p class="dj-eyebrow">Scope</p>
                <h2 id="included-title">What AI All-Access is — and is not</h2>
            </div>
            <div class="dj-scope">
                <article class="dj-scope__col dj-scope__col--yes">
                    <h3><x-icon name="check" :size="22" /> Included</h3>
                    <ul>@foreach ($included as $i)<li><x-icon name="check" :size="18" /> {{ $i }}</li>@endforeach</ul>
                </article>
                <article class="dj-scope__col dj-scope__col--no">
                    <h3><x-icon name="x" :size="22" /> Not included</h3>
                    <ul>@foreach ($notIncluded as $i)<li><x-icon name="x" :size="18" /> {{ $i }}</li>@endforeach</ul>
                </article>
            </div>
        </div>
    </section>

    {{-- 7. Compare --}}
    <section class="dj-section dj-section--surface" id="compare" aria-labelledby="compare-title">
        <div class="dj-container">
            <div class="dj-section-head dj-section-head--split">
                <div>
                    <p class="dj-eyebrow">Compare</p>
                    <h2 id="compare-title">AI All-Access or a hosted AI agent?</h2>
                    <p class="dj-section-head__text">AI All-Access gives you model access with no server to look after. Want an AI agent running on your own VPS? Pair it with a hosted product — the same key works there.</p>
                </div>
                <a class="dj-link" href="{{ route('compare.index') }}">All comparisons <x-icon name="arrow-right" :size="16" /></a>
            </div>
            @include('partials.product-matrix', ['keys' => ['ai-all-access', 'hermes-workspace', 'openclaw', 'claude-code'], 'current' => 'ai-all-access', 'matrix' => $matrix])
        </div>
    </section>

    {{-- 8. FAQ --}}
    <section class="dj-section" id="faq" aria-labelledby="faq-title">
        <div class="dj-container dj-faq-layout">
            <div class="dj-faq-layout__aside">
                <p class="dj-eyebrow">FAQ</p>
                <h2 id="faq-title">Questions about AI All-Access</h2>
                <p class="dj-section-head__text">Credit, models, tools and billing — answered plainly.</p>
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

    {{-- 9. CTA --}}
    <x-cta variant="ai" />
@endsection
