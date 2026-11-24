@extends('layouts.admin')

@section('title', $member->name)
@section('heading', 'Staff · '.$member->name)
@section('actions')<a class="admin-btn admin-btn-quiet" href="{{ route('admin.staff.index') }}">All staff</a>@endsection

@section('content')
    <section class="admin-card"><h2>Account</h2>
        <dl class="admin-dl">
            <dt>Email</dt><dd>{{ $member->email }}</dd>
            <dt>Role</dt><dd>{{ $member->isAdmin() ? 'Owner (legacy)' : $member->staffRole?->name }}</dd>
            <dt>Departments</dt><dd>{{ $member->departments->pluck('name')->implode(', ') ?: '—' }}</dd>
            <dt>Status</dt><dd>@if ($member->is_active)<span class="badge badge-good">Active</span>@else<span class="badge badge-bad">Suspended</span> {{ $member->suspended_at?->format('d M Y H:i') }} — {{ $member->suspension_reason ?? 'no reason recorded' }}@endif</dd>
            <dt>Email verified</dt><dd>{{ $member->email_verified_at ? 'Yes' : 'Not yet (verified by code at first sign-in)' }}</dd>
            <dt>Password change</dt><dd>{{ $member->password_change_required ? 'Required at next sign-in' : 'Not required' }}</dd>
            <dt>Two-factor</dt><dd>{{ $member->hasTwoFactor() ? 'On · '.$recoveryLeft.' recovery code(s) left' : 'Off' }}</dd>
            <dt>Last staff sign-in</dt><dd>{{ $member->last_staff_login_at?->format('d M Y H:i') ?? '—' }} {{ $member->last_staff_login_ip ? '· '.$member->last_staff_login_ip : '' }}</dd>
        </dl>
    </section>

    @php
        $menusFor = fn ($role) => $role->is_super
            ? collect($sidebarItems)->pluck(0)->unique()->values()
            : collect($sidebarItems)->filter(fn ($i) => $role->permissions->pluck('permission')->contains($i[1]))->pluck(0)->unique()->values();
        $currentMenus = $member->staffRole ? $menusFor($member->staffRole->loadMissing('permissions')) : collect();
    @endphp
    <section class="admin-card"><h2>Role &amp; access</h2>
        <p class="small"><strong>Login par sidebar mein dikhta hai:</strong> {{ $currentMenus->implode(', ') ?: 'Sirf My profile' }}</p>
        @if ($canEdit)
            <form method="POST" action="{{ route('admin.staff.update', $member) }}" class="admin-form" data-loading-form>@csrf @method('PUT')
                @include('admin.staff._role_select', ['roles' => $roles, 'isSuper' => $isSuper, 'menusFor' => $menusFor, 'id' => 'member-role', 'current' => $member->role_id])
                @include('admin.staff._departments', ['departments' => $departments, 'selected' => $member->departments->pluck('id')->all()])
                <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked($member->is_active)> Account active</label>
                <p class="muted small">Save karne se pehle password confirm karna hoga. Badlav audit log mein record hota hai.</p>
                <div class="admin-actions-row"><button class="admin-btn" type="submit">Save role &amp; access</button></div>
            </form>
        @endif
    </section>

    @if ($canManage && $member->id !== auth()->id())
    <section class="admin-card"><h2>Access controls</h2>
        <p class="muted small">These actions ask you to confirm your password first and are recorded in the audit log.</p>
        <div class="admin-actions-row">
            @if ($member->is_active)
                <form method="POST" action="{{ route('admin.staff.suspend', $member) }}">@csrf
                    <label>Reason<input name="reason" required maxlength="255" placeholder="e.g. left the company"></label>
                    <button class="admin-btn admin-btn-danger" type="submit">Suspend</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.staff.reactivate', $member) }}">@csrf<button class="admin-btn" type="submit">Reactivate</button></form>
            @endif
            <form method="POST" action="{{ route('admin.staff.revoke-sessions', $member) }}">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Sign out all sessions</button></form>
            <form method="POST" action="{{ route('admin.staff.reset-access', $member) }}">@csrf
                <label class="admin-check"><input type="checkbox" name="send_reset_link" value="1" checked> Email a password reset link</label>
                <label class="admin-check"><input type="checkbox" name="reset_mfa" value="1"> Also reset two-factor</label>
                <button class="admin-btn admin-btn-quiet" type="submit">Reset access</button>
            </form>
            @if ($member->hasTwoFactor())
                <form method="POST" action="{{ route('admin.staff.reset-mfa', $member) }}" onsubmit="return confirm('Reset two-factor for this staff member?')">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Reset two-factor only</button></form>
            @endif
        </div>
    </section>
    @endif

    <section class="admin-card"><h2>Login history</h2>
        @if ($logins->isEmpty())<p class="muted">No sign-in activity recorded.</p>@else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>When</th><th>Event</th><th>IP</th></tr></thead>
            <tbody>@foreach ($logins as $l)<tr><td data-label="When">{{ $l->created_at?->format('d M Y H:i') }}</td><td data-label="Event">{{ str_replace(['admin.', '_'], ['', ' '], $l->action) }}</td><td data-label="IP">{{ $l->ip ?? '—' }}</td></tr>@endforeach</tbody>
        </table></div>@endif
    </section>

    <section class="admin-card"><h2>Security events</h2>
        @if ($events->isEmpty())<p class="muted">No security events recorded.</p>@else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>When</th><th>Event</th><th>By</th><th>IP</th></tr></thead>
            <tbody>@foreach ($events as $e)<tr><td data-label="When">{{ $e->created_at?->format('d M Y H:i') }}</td><td data-label="Event">{{ $e->action }}</td><td data-label="By">{{ $e->user?->name ?? 'System' }}</td><td data-label="IP">{{ $e->ip ?? '—' }}</td></tr>@endforeach</tbody>
        </table></div>@endif
    </section>
@endsection
