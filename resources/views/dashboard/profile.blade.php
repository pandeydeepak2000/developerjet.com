@extends('layouts.dashboard')

@section('title', 'Profile & security')
@section('heading', 'Profile & security')
@section('subheading', 'Your contact details and sign-in security.')

@section('dash')
    @if (! $user->phone)
        <div class="flash flash-warning" role="status">
            <strong>Please add your phone number.</strong> It is now required for new accounts so our team can reach you about your services and billing. Your account keeps working in the meantime.
        </div>
    @endif

    <div class="panel">
        <h2>Contact details</h2>
        <form method="POST" action="{{ route('dashboard.profile.update') }}" class="auth-fields profile-form" data-loading-form novalidate>
            @csrf @method('PUT')
            <label for="profile-name">Full name</label>
            <input id="profile-name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name" @error('name') aria-invalid="true" @enderror>
            @error('name')<p class="field-error">{{ $message }}</p>@enderror

            <label for="profile-email">Email address</label>
            <input id="profile-email" value="{{ $user->email }}" readonly aria-describedby="profile-email-hint">
            <p class="field-hint" id="profile-email-hint">Verified {{ $user->email_verified_at?->format('d M Y') ?? '' }}. To change your email address, open a support ticket.</p>

            @include('partials.phone-field', ['idPrefix' => 'profile', 'value' => $user->phone])

            <div><button class="button-primary" type="submit">Save details</button></div>
        </form>
    </div>

    <div class="panel">
        <h2>Change password</h2>
        <form method="POST" action="{{ route('dashboard.profile.password') }}" class="auth-fields profile-form" data-loading-form novalidate>
            @csrf @method('PUT')
            <label for="current-password">Current password</label>
            <input id="current-password" type="password" name="current_password" autocomplete="current-password" required @error('current_password', 'password') aria-invalid="true" @enderror>
            @error('current_password', 'password')<p class="field-error">{{ $message }}</p>@enderror

            <label for="new-password">New password</label>
            <input id="new-password" type="password" name="password" autocomplete="new-password" required minlength="8" placeholder="8+ characters, letters and numbers" @error('password', 'password') aria-invalid="true" @enderror>
            @error('password', 'password')<p class="field-error">{{ $message }}</p>@enderror

            <label for="new-password-confirm">Confirm new password</label>
            <input id="new-password-confirm" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8">

            <div><button class="button-primary" type="submit">Change password</button></div>
        </form>
        <p class="muted small">Changing your password signs out your other sessions. Forgot it? Use <a href="{{ route('password.request') }}">password reset</a>.</p>
    </div>
@endsection
