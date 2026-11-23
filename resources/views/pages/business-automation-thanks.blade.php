@extends('layouts.app')

@section('title', 'Business Automation request received')

@section('content')
    <section class="dj-page-hero" aria-labelledby="thanks-title">
        <div class="dj-container">
            <div class="dj-page-hero__inner dj-ba-thanks">
                <p class="dj-eyebrow">Business Automation</p>
                <h1 id="thanks-title">{{ $c['duplicate'] ? 'We already have your request' : 'Thank you — your request is in' }}</h1>
                <p class="dj-lede">
                    @if ($c['duplicate'])
                        This looks the same as a request you sent recently, so we have not created a second one.
                    @else
                        Our sales team will review your requirements and contact you with questions or a proposed scope.
                    @endif
                </p>
                <p class="dj-ba-ref">Your request reference: <strong data-lead-reference>{{ $c['reference'] }}</strong></p>
                <p class="dj-muted">Please quote this reference if you contact us. A confirmation email has been queued to the address you gave; if it does not arrive, your request is still saved.</p>
                @if ($c['redacted'])
                    <div class="flash flash-info" role="status">Something in your message looked like a password or API key, so we removed it before saving. Please never send credentials — we will arrange any access securely with you.</div>
                @endif
                <div class="dj-actions">
                    @if ($c['account'])
                        <a class="dj-btn dj-btn--primary" href="{{ route('dashboard.automation.show', $c['id']) }}">View in your dashboard</a>
                    @endif
                    <a class="dj-btn dj-btn--secondary" href="{{ route('business-automation') }}">Back to Business Automation</a>
                    <a class="dj-link" href="{{ route('contact') }}">Contact Sales <x-icon name="arrow-right" :size="16" /></a>
                </div>
                <p class="dj-muted small">Nothing has been charged. A quotation is sent separately and nothing is payable unless you accept it.</p>
            </div>
        </div>
    </section>
@endsection
