@extends('staff.layout')
@section('title', 'Staff sign in')
@section('heading', 'Staff sign in')
@section('content')
    <p class="muted">Authorised DeveloperJet staff only. Sign-ins are logged.</p>
    <form method="POST" action="{{ \App\Support\StaffPortal::url('login.store') }}" data-loading-form>
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="190"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        @error('email')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
        <button class="admin-btn" type="submit">Sign in</button>
    </form>
    <p class="muted small"><a href="{{ \App\Support\StaffPortal::url('password.request') }}">Forgot your password?</a></p>
@endsection
