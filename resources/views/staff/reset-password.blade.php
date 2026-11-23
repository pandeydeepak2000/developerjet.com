@extends('staff.layout')
@section('title', 'Choose a new password')
@section('heading', 'Choose a new password')
@section('content')
    <p class="muted">At least 12 characters with upper- and lower-case letters, a number and a symbol. All your staff sessions will be signed out.</p>
    <form method="POST" action="{{ \App\Support\StaffPortal::url('password.update') }}" data-loading-form>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email<input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"></label>
        <label>New password<input type="password" name="password" required autocomplete="new-password"></label>
        <label>Confirm new password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        @if ($errors->any())<p class="admin-error" role="alert">{{ $errors->first() }}</p>@endif
        <button class="admin-btn" type="submit">Reset password</button>
    </form>
@endsection
