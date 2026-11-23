@extends('staff.layout')
@section('title', 'Reset staff password')
@section('heading', 'Reset your password')
@section('content')
    <p class="muted">Enter your staff email. If it belongs to an active staff account, we will send a single-use reset link.</p>
    <form method="POST" action="{{ \App\Support\StaffPortal::url('password.email') }}" data-loading-form>
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="190"></label>
        @error('email')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
        <button class="admin-btn" type="submit">Send reset link</button>
    </form>
    <p class="muted small"><a href="{{ \App\Support\StaffPortal::url('login') }}">Back to sign in</a></p>
@endsection
