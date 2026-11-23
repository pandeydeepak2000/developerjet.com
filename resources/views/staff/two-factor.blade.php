@extends('staff.layout')
@section('title', 'Two-factor check')
@section('heading', 'Two-factor check')
@section('content')
    <p class="muted">Enter the 6-digit code from your authenticator app, or one of your recovery codes (format XXXXX-XXXXX).</p>
    <form method="POST" action="{{ \App\Support\StaffPortal::url('two-factor.verify') }}" data-loading-form autocomplete="off">
        @csrf
        <label>Authenticator or recovery code<input name="code" autocomplete="one-time-code" maxlength="20" required autofocus></label>
        @error('code')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
        <button class="admin-btn" type="submit">Verify</button>
    </form>
    <p class="muted small"><a href="{{ \App\Support\StaffPortal::url('login') }}">Start over</a></p>
@endsection
