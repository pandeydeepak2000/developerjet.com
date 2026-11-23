@extends('layouts.admin')

@section('title', 'Confirm access')
@section('heading', 'Confirm it is you')

@section('content')
    <section class="admin-card"><h2>Sensitive action</h2>
        <p class="muted small">Re-enter your password{{ $mfa ? ' and an authenticator (or recovery) code' : '' }}. You will not be asked again for {{ config('staff.reauth_minutes') }} minutes.</p>
        <form method="POST" action="{{ route('admin.confirm-access.store') }}" class="admin-form" autocomplete="off">@csrf
            <label>Password<input type="password" name="password" required autocomplete="current-password" autofocus></label>
            @if ($mfa)<label>Authenticator or recovery code<input name="code" autocomplete="one-time-code" maxlength="20" required></label>@endif
            @error('password')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
            <button class="admin-btn" type="submit">Confirm</button>
        </form>
    </section>
@endsection
