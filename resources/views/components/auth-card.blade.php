@props(['mode'])

@php
    $salesMessage = match ($mode) {
        'register' => 'Hi DeveloperJet, I would like to create an account. Please help me get started.',
        'forgot' => 'Hi DeveloperJet, I need help with account access.',
        default => 'Hi DeveloperJet, I need help signing in to my account.',
    };
@endphp

<section class="auth-card">
    <a class="auth-back-link" href="{{ route('home') }}"><span aria-hidden="true">←</span> Back to home</a>
    @if ($mode === 'register')
        <span class="auth-card-kicker">A GOOD PLACE TO BEGIN</span>
        <h2>Create your account</h2>
        <p class="auth-card-intro">Tell us a little about yourself. Our team will help you take the next step.</p>
        <div class="auth-fields">
            <label for="register-name">Your name</label>
            <input id="register-name" type="text" name="name" autocomplete="name" placeholder="How should we call you?">
            <label for="register-email">Email address</label>
            <input id="register-email" type="email" name="email" autocomplete="email" placeholder="you@example.com">
            <label for="register-password">Create a password</label>
            <input id="register-password" type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters">
        </div>
        <a class="auth-submit" href="https://wa.me/917688855600?text={{ rawurlencode($salesMessage) }}" target="_blank" rel="noopener noreferrer">Get started with Sales <span aria-hidden="true">↗</span></a>
        <p class="auth-safe-note">This is a UI preview. Your details and password are not submitted or sent to WhatsApp.</p>
        <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
    @elseif ($mode === 'forgot')
        <span class="auth-card-kicker">LET’S GET YOU BACK IN</span>
        <h2>Forgot your password?</h2>
        <p class="auth-card-intro">Password reset isn’t available here yet. Our team can help you with account access.</p>
        <div class="auth-fields">
            <label for="reset-email">Email address</label>
            <input id="reset-email" type="email" name="email" autocomplete="email" placeholder="you@example.com">
        </div>
        <button class="auth-submit auth-submit-disabled" type="button" disabled>Reset link unavailable</button>
        <a class="auth-secondary-action" href="https://wa.me/917688855600?text={{ rawurlencode($salesMessage) }}" target="_blank" rel="noopener noreferrer">Ask Sales for help on WhatsApp <span aria-hidden="true">↗</span></a>
        <p class="auth-safe-note">Password reset is a visual-only screen; no reset email will be sent.</p>
        <p class="auth-switch"><a href="{{ route('login') }}">Back to log in</a></p>
    @else
        <span class="auth-card-kicker">WELCOME BACK</span>
        <h2>Good to see you.</h2>
        <p class="auth-card-intro">Sign-in assistance is just a message away. We’ll help you get where you need to go.</p>
        <div class="auth-fields">
            <label for="login-email">Email address</label>
            <input id="login-email" type="email" name="email" autocomplete="email" placeholder="you@example.com">
            <div class="auth-password-label"><label for="login-password">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
            <input id="login-password" type="password" name="password" autocomplete="current-password" placeholder="Your password">
        </div>
        <a class="auth-submit" href="https://wa.me/917688855600?text={{ rawurlencode($salesMessage) }}" target="_blank" rel="noopener noreferrer">Continue with Sales <span aria-hidden="true">↗</span></a>
        <p class="auth-safe-note">This is a UI preview. Your email and password are not submitted or sent to WhatsApp.</p>
        <p class="auth-switch">New to DeveloperJet? <a href="{{ route('register') }}">Create an account</a></p>
    @endif
    <div class="auth-support-line"><span>✳</span> Real people, right here when you need us.</div>
</section>
