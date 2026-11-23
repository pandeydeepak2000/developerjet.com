@extends('layouts.admin')

@section('title', 'My security')
@section('heading', 'My security')

@section('content')
    @if ($codes)
        <section class="admin-card" role="alert"><h2>Your recovery codes</h2>
            <p>Store these somewhere safe (a password manager). Each code works <strong>once</strong> if you lose your authenticator. They will <strong>not</strong> be shown again.</p>
            <ul class="admin-codes">@foreach ($codes as $c)<li>{{ $c }}</li>@endforeach</ul>
        </section>
    @endif

    <section class="admin-card"><h2>Two-factor authentication (authenticator app)</h2>
        @if ($required && ! $enabled)<div class="flash flash-info" role="note">Two-factor authentication is required for your account (your organisation requires two-factor for staff, or you hold a privileged role). Set it up to continue.</div>@endif
        @if ($enabled)
            <p><span class="badge badge-good">On</span> You will be asked for a 6-digit code each time you sign in. Recovery codes left: <strong>{{ $remaining }}</strong>@if ($remaining < 3) <span class="badge badge-bad">Generate new codes</span>@endif</p>

            <h3>Recovery codes</h3>
            <p class="muted small">Generating a new set invalidates the old codes.</p>
            <form method="POST" action="{{ route('admin.security.recovery-codes') }}" class="admin-form" autocomplete="off">@csrf
                <label>Current password<input type="password" name="password" required autocomplete="current-password"></label>
                <label>Authenticator code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required></label>
                <button class="admin-btn admin-btn-quiet" type="submit">Generate new recovery codes</button>
            </form>

            @if ($required)
                <p class="muted small">Two-factor is mandatory for your account and cannot be turned off.</p>
            @else
                <h3>Turn off</h3>
                <form method="POST" action="{{ route('admin.security.disable') }}" class="admin-form" autocomplete="off">@csrf
                    <label>Current password<input type="password" name="password" required autocomplete="current-password"></label>
                    <label>Authenticator code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required></label>
                    <button class="admin-btn admin-btn-danger" type="submit">Turn off</button>
                </form>
            @endif
            @error('code')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
        @elseif ($secret)
            <p>1. In your authenticator app choose “Enter a setup key” and add the account <strong>{{ auth()->user()->email }}</strong> with this key (time-based):</p>
            <p><code class="admin-secret">{{ $secret }}</code></p>
            <p class="muted small">Or paste this into an app that accepts links: <code class="break-all">{{ $uri }}</code></p>
            <p class="muted small">This key is shown only until you confirm. After that it is never displayed again.</p>
            <p>2. Enter the 6-digit code it shows to finish.</p>
            <form method="POST" action="{{ route('admin.security.confirm') }}" class="admin-form" autocomplete="off">@csrf
                <label>Authenticator code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus></label>
                @error('code')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
                <button class="admin-btn" type="submit">Confirm and turn on</button>
            </form>
        @else
            <p><span class="badge badge-neutral">Off</span> Add a second step to your sign-in using an authenticator app.</p>
            <form method="POST" action="{{ route('admin.security.start') }}">@csrf<button class="admin-btn" type="submit">Set up</button></form>
        @endif
        <p class="muted small">Lost your authenticator and recovery codes? Another staff member with permission to edit staff (a Super Admin for Super Admin accounts) can reset it; you then enrol again.</p>
    </section>
@endsection
