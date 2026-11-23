{{-- Customer-facing workflow (owner brief §5). Internal supplier purchasing/assignment is documented for staff only
     (docs/03_COMPLETE_WORKFLOW.md); public pages describe what the customer experiences. --}}
@php
    $product = $product ?? null;
    $flow = [
        ['grid', 'Choose a Plan', 'Compare products and pick the plan and billing period that fit your work.'],
        ['settings', 'Configure Your Service', 'Add the details we need, such as your own AI provider key or a domain you already own, where supported.'],
        ['card', 'Complete Checkout', 'Create your account, verify your email and pay. Payment is verified on our servers.'],
        ['server', 'We Prepare Your Environment', 'Our team prepares your server'.($product ? ' and sets up '.$product : '').'. This is not instant — progress shows in your dashboard.'],
        ['check', 'Access Your Service', 'Access details appear in your dashboard when everything is ready. Passwords are never emailed.'],
    ];
@endphp
<ol class="dj-flow">
    @foreach ($flow as $i => [$icon, $title, $text])
        <li class="dj-flow__step {{ $i === 3 ? 'is-manual' : '' }}">
            <span class="dj-flow__icon"><x-icon :name="$icon" :size="22" /></span>
            <span class="dj-flow__num">Step {{ $i + 1 }}</span>
            <h3>{{ $title }}</h3>
            <p>{{ $text }}</p>
        </li>
    @endforeach
</ol>
