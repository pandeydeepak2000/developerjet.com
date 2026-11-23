@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
    <section class="auth-page section-shell">
        <div class="auth-story">
            <span class="eyebrow">ALMOST THERE</span>
            <h1>Check your inbox.</h1>
            <p>Verified accounts keep your services and payments safe. Checkout unlocks as soon as your email is confirmed.</p>
            <div class="auth-feature-stack">
                <div class="auth-feature"><span><x-icon name="check" :size="18" /></span><div><strong>Cart saved</strong><small>Your selected plans are waiting for you.</small></div></div>
                <div class="auth-feature"><span><x-icon name="check" :size="18" /></span><div><strong>Code expires in {{ \App\Services\OtpService::TTL_MINUTES }} minutes</strong><small>You can request a new one anytime.</small></div></div>
            </div>
        </div>
        <x-auth-card mode="verify" :email="$email" :wait="$wait" />
    </section>
@endsection
