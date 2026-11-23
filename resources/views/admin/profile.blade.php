@extends('layouts.admin')

@section('title', 'My profile')
@section('heading', 'My profile')

@section('content')
    @if ($user->password_change_required)
        <div class="flash flash-info" role="note">You must choose a new password before using the admin area.</div>
    @endif
    <section class="admin-card"><h2>Account</h2>
        <dl class="admin-dl">
            <dt>Email</dt><dd>{{ $user->email }} <span class="muted small">(cannot be changed here)</span></dd>
            <dt>Role</dt><dd>{{ $user->isAdmin() ? 'Owner' : $user->staffRole?->name }}</dd>
            <dt>Departments</dt><dd>{{ $user->departments->pluck('name')->implode(', ') ?: '—' }}</dd>
            <dt>Two-factor</dt><dd>{{ $user->hasTwoFactor() ? 'On' : 'Off' }} · <a href="{{ route('admin.security') }}">Manage</a></dd>
            <dt>Last staff sign-in</dt><dd>{{ $user->last_staff_login_at?->format('d M Y H:i') ?? '—' }} {{ $user->last_staff_login_ip ? '· '.$user->last_staff_login_ip : '' }}</dd>
        </dl>
        <form method="POST" action="{{ route('admin.profile.update') }}" class="admin-form">@csrf @method('PUT')
            <label>Display name<input name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name"></label>
            <button class="admin-btn admin-btn-quiet" type="submit">Save name</button>
        </form>
    </section>

    <section class="admin-card" id="password"><h2>Change password</h2>
        <p class="muted small">At least 12 characters with upper- and lower-case letters, a number and a symbol. Your other staff sessions are signed out.</p>
        <form method="POST" action="{{ route('admin.profile.password') }}" class="admin-form" autocomplete="off">@csrf @method('PUT')
            <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>New password<input type="password" name="password" required autocomplete="new-password"></label>
            <label>Confirm new password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
            @error('current_password')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
            @error('password')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
            <button class="admin-btn" type="submit">Change password</button>
        </form>
    </section>

    <section class="admin-card"><h2>Sessions</h2>
        <p class="muted small">Sign out every other browser that is signed in to the staff portal with your account.</p>
        <form method="POST" action="{{ route('admin.profile.sessions') }}">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Sign out other sessions</button></form>
    </section>

    <section class="admin-card"><h2>Recent sign-in activity</h2>
        @if ($history->isEmpty())<p class="muted">No sign-in activity recorded yet.</p>@else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>When</th><th>Event</th><th>IP</th></tr></thead>
            <tbody>@foreach ($history as $h)<tr><td data-label="When">{{ $h->created_at?->format('d M Y H:i') }}</td><td data-label="Event">{{ str_replace(['admin.', '_'], ['', ' '], $h->action) }}</td><td data-label="IP">{{ $h->ip ?? '—' }}</td></tr>@endforeach</tbody>
        </table></div>@endif
    </section>
@endsection
