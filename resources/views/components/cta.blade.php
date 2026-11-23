@props(['variant' => 'hosting', 'eyebrow' => null, 'title' => null, 'text' => null, 'primaryUrl' => null, 'primaryLabel' => null, 'secondaryUrl' => null, 'secondaryLabel' => null])
@php
    // One CTA system for every public page. Copy per variant follows the owner brief:
    // hosting → Explore Plans / Contact Sales · services → Request Quote · docs → Find Help / Contact Support · ai → Request Early Access.
    $defaults = [
        'hosting' => ['eyebrow' => 'Hosting', 'title' => 'Ready to choose a plan?', 'text' => 'Compare plans, then check out. Our team prepares your environment and your dashboard shows each step.',
            'primary' => [route('plans'), 'Explore Plans'], 'secondary' => [route('contact'), 'Contact Sales']],
        'services' => ['eyebrow' => 'Digital services', 'title' => 'Have a project in mind?', 'text' => 'Tell us what you need. We reply with a clear, scoped quote — nothing is charged until you accept it.',
            'primary' => [route('digital-services'), 'Request Quote'], 'secondary' => [route('contact'), 'Contact us']],
        'docs' => ['eyebrow' => 'Help', 'title' => 'Still need help?', 'text' => 'Search the documentation or open a support ticket — our team replies in your dashboard.',
            'primary' => [route('docs.index'), 'Find Help'], 'secondary' => [route('contact'), 'Contact Support']],
        'ai' => ['eyebrow' => 'AI All-Access · coming soon', 'title' => 'Want early access?', 'text' => 'Request access and we will contact you when AI All-Access opens. Nothing is charged.',
            'primary' => [route('ai-all-access').'#plans', 'Request Early Access'], 'secondary' => [route('contact'), 'Contact Sales']],
        'general' => ['eyebrow' => 'Next step', 'title' => 'Ready when you are', 'text' => 'Explore plans or contact our team.',
            'primary' => [route('plans'), 'Explore Plans'], 'secondary' => [route('contact'), 'Contact Sales']],
    ];
    $d = $defaults[$variant] ?? $defaults['hosting'];
    $id = 'cta-'.$variant;
@endphp
<section {{ $attributes->merge(['class' => 'dj-cta dj-cta--'.$variant]) }} aria-labelledby="{{ $id }}">
    <div class="dj-container">
        <div class="dj-cta__card">
            <div class="dj-cta__copy">
                <p class="dj-cta__eyebrow">{{ $eyebrow ?? $d['eyebrow'] }}</p>
                <h2 id="{{ $id }}">{{ $title ?? $d['title'] }}</h2>
                <p>{{ $text ?? $d['text'] }}</p>
            </div>
            <div class="dj-actions">
                <a class="dj-btn dj-btn--white dj-btn--lg" href="{{ $primaryUrl ?? $d['primary'][0] }}">{{ $primaryLabel ?? $d['primary'][1] }} <x-icon name="arrow-right" :size="18" /></a>
                <a class="dj-btn dj-btn--outline-light dj-btn--lg" href="{{ $secondaryUrl ?? $d['secondary'][0] }}">{{ $secondaryLabel ?? $d['secondary'][1] }}</a>
            </div>
        </div>
    </div>
</section>
