@extends('staff.layout')
@section('title', 'Accept invitation')
@section('heading', 'Join the staff portal')
@section('content')
    <p class="muted">Invitation for <strong>{{ $invitation->email }}</strong> ({{ $invitation->role->name }}). Choose your password — at least 12 characters with upper- and lower-case letters, a number and a symbol.</p>
    <form method="POST" action="{{ \App\Support\StaffPortal::url('invitation.accept', ['token' => $token]) }}" data-loading-form>
        @csrf
        <label>Full name<input name="name" value="{{ old('name', $invitation->name) }}" required maxlength="120" autocomplete="name"></label>
        <label>Password<input type="password" name="password" required autocomplete="new-password"></label>
        <label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        @if ($errors->any())<p class="admin-error" role="alert">{{ $errors->first() }}</p>@endif
        <button class="admin-btn" type="submit">Create my staff account</button>
    </form>
@endsection
