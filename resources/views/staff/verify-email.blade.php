@extends('staff.layout')
@section('title', 'Confirm your email')
@section('heading', 'Confirm your email')
@section('content')
    <p class="muted">This staff account's email address has not been verified yet. Enter the 6-digit code for this account to finish signing in. A code is only delivered when email delivery is configured — if none arrives, do not assume one was sent; a Super Admin should check Settings - Email (SMTP) and use the SMTP connection test.</p>
    @if (session('error'))<p class="admin-error" role="alert">{{ session('error') }}</p>@endif
    <form method="POST" action="{{ \App\Support\StaffPortal::url('verify-email.verify') }}" data-loading-form autocomplete="off">
        @csrf
        <label>Email code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus></label>
        @error('code')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
        <button class="admin-btn" type="submit">Verify and continue</button>
    </form>
    <form method="POST" action="{{ \App\Support\StaffPortal::url('verify-email.resend') }}">@csrf<button class="admin-link-btn" type="submit">Send a new code</button></form>
    <p class="muted small"><a href="{{ \App\Support\StaffPortal::url('login') }}">Start over</a></p>
@endsection
