@props(['mode', 'token' => null, 'email' => null, 'wait' => 0])

@php
    $supportMessage = rawurlencode('Hi DeveloperJet, I need help with my account.');
@endphp

<section class="auth-card">
    <a class="auth-back-link" href="{{ route('home') }}"><span aria-hidden="true">←</span> Back to home</a>

    @if ($mode === 'register')
        <span class="auth-card-kicker">A GOOD PLACE TO BEGIN</span>
        <h2>Create your account</h2>
        <p class="auth-card-intro">We’ll email you a 6-digit code to verify your address before checkout.</p>
        <form method="POST" action="{{ route('register') }}" class="auth-fields" data-loading-form novalidate>
            @csrf
            <label for="register-name">Your name</label>
            <input id="register-name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120" @error('name') aria-invalid="true" aria-describedby="err-name" @enderror>
            @error('name')<p class="field-error" id="err-name">{{ $message }}</p>@enderror

            <label for="register-email">Email address</label>
            <input id="register-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="err-email" @enderror>
            @error('email')<p class="field-error" id="err-email">{{ $message }}</p>@enderror

            @include('partials.phone-field', ['idPrefix' => 'register', 'value' => null])
            <label for="register-password">Create a password</label>
            <input id="register-password" type="password" name="password" autocomplete="new-password" required minlength="8" placeholder="8+ characters, letters and numbers" @error('password') aria-invalid="true" aria-describedby="err-password" @enderror>
            @error('password')<p class="field-error" id="err-password">{{ $message }}</p>@enderror

            <label for="register-password-confirm">Confirm password</label>
            <input id="register-password-confirm" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8">

            <button class="auth-submit" type="submit"><span class="btn-label">Create account</span><span class="btn-spinner" aria-hidden="true"></span></button>
        </form>
        <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
    @elseif ($mode === 'forgot')
        <span class="auth-card-kicker">LET’S GET YOU BACK IN</span>
        <h2>Forgot your password?</h2>
        <p class="auth-card-intro">Enter your email and we’ll send a link to choose a new password.</p>
        <form method="POST" action="{{ route('password.email') }}" class="auth-fields" data-loading-form novalidate>
            @csrf
            <label for="reset-email">Email address</label>
            <input id="reset-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="err-email" @enderror>
            @error('email')<p class="field-error" id="err-email">{{ $message }}</p>@enderror
            <button class="auth-submit" type="submit"><span class="btn-label">Send reset link</span><span class="btn-spinner" aria-hidden="true"></span></button>
        </form>
        <a class="auth-secondary-action" href="https://wa.me/917688855600?text={{ $supportMessage }}" target="_blank" rel="noopener noreferrer">Need help? Message Sales on WhatsApp <span aria-hidden="true">↗</span></a>
        <p class="auth-switch"><a href="{{ route('login') }}">Back to log in</a></p>
    @elseif ($mode === 'reset')
        <span class="auth-card-kicker">CHOOSE A NEW PASSWORD</span>
        <h2>Reset your password</h2>
        <p class="auth-card-intro">Pick a strong password you haven’t used elsewhere.</p>
        <form method="POST" action="{{ route('password.update') }}" class="auth-fields" data-loading-form novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="rp-email">Email address</label>
            <input id="rp-email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
            <label for="rp-password">New password</label>
            <input id="rp-password" type="password" name="password" autocomplete="new-password" required minlength="8">
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
            <label for="rp-password-confirm">Confirm new password</label>
            <input id="rp-password-confirm" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8">
            <button class="auth-submit" type="submit"><span class="btn-label">Reset password</span><span class="btn-spinner" aria-hidden="true"></span></button>
        </form>
    @elseif ($mode === 'verify')
        <span class="auth-card-kicker">ONE QUICK STEP</span>
        <h2>Verify your email</h2>
        <p class="auth-card-intro">Enter the 6-digit code sent to <strong>{{ $email }}</strong>. It expires in {{ \App\Services\OtpService::TTL_MINUTES }} minutes.</p>
        <form method="POST" action="{{ route('verification.verify') }}" class="auth-fields" data-loading-form novalidate>
            @csrf
            <label for="otp-code">Verification code</label>
            <input id="otp-code" class="otp-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus @error('code') aria-invalid="true" aria-describedby="err-code" @enderror>
            @error('code')<p class="field-error" id="err-code">{{ $message }}</p>@enderror
            <button class="auth-submit" type="submit"><span class="btn-label">Verify email</span><span class="btn-spinner" aria-hidden="true"></span></button>
        </form>
        <form method="POST" action="{{ route('verification.send') }}" data-loading-form>
            @csrf
            <button class="auth-secondary-action auth-link-button" type="submit"><span class="btn-label">Didn’t get it? Resend code</span><span class="btn-spinner" aria-hidden="true"></span></button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="auth-secondary-action auth-link-button" type="submit">Use a different account</button>
        </form>
    @else
        <span class="auth-card-kicker">WELCOME BACK</span>
        <h2>Good to see you.</h2>
        <p class="auth-card-intro">Sign in to manage your cart, orders and services.</p>
        <form method="POST" action="{{ route('login') }}" class="auth-fields" data-loading-form novalidate>
            @csrf
            <label for="login-email">Email address</label>
            <input id="login-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="err-email" @enderror>
            @error('email')<p class="field-error" id="err-email">{{ $message }}</p>@enderror
            <div class="auth-password-label"><label for="login-password">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
            <input id="login-password" type="password" name="password" autocomplete="current-password" required>
            <label class="auth-remember"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button class="auth-submit" type="submit"><span class="btn-label">Log in</span><span class="btn-spinner" aria-hidden="true"></span></button>
        </form>
        <p class="auth-switch">New to DeveloperJet? <a href="{{ route('register') }}">Create an account</a></p>
    @endif
    <div class="auth-support-line"><x-icon name="lock" :size="16" /> Secure sign-in · email verified with a one-time code</div>
</section>
