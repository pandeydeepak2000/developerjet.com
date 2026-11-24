@extends('layouts.admin')

@section('title', 'Staff & roles')
@section('heading', 'Staff & roles')

@php
    $u = auth()->user();
    $tab = in_array(request('tab'), ['team', 'add', 'roles'], true) ? request('tab') : 'team';
    if ($errors->any() && in_array(old('form'), ['invite', 'direct'], true)) { $tab = 'add'; }
    $canCreate = $u->hasPermission('staff.create');
    $menusFor = fn ($role) => $role->is_super
        ? collect($sidebarItems)->pluck(0)->unique()->values()
        : collect($sidebarItems)->filter(fn ($i) => $role->permissions->pluck('permission')->contains($i[1]))->pluck(0)->unique()->values();
@endphp

@section('actions')
    @if ($canCreate)<a class="admin-btn" href="{{ route('admin.staff.index', ['tab' => 'add']) }}">+ Add staff</a>@endif
@endsection

@section('content')
    <p class="admin-lead">Har staff member ko ek <strong>role</strong> milta hai. Role ki permissions hi tay karti hain ki login par sidebar mein kaun se menu dikhenge. Support tickets ke liye departments bhi chuniye.</p>

    <nav class="admin-tabs" aria-label="Staff sections">
        <a href="{{ route('admin.staff.index', ['tab' => 'team']) }}" @if ($tab === 'team') aria-current="page" @endif>Team <span>{{ $staff->total() }}</span></a>
        @if ($canCreate)<a href="{{ route('admin.staff.index', ['tab' => 'add']) }}" @if ($tab === 'add') aria-current="page" @endif>Add staff</a>@endif
        <a href="{{ route('admin.staff.index', ['tab' => 'roles']) }}" @if ($tab === 'roles') aria-current="page" @endif>Roles & access <span>{{ $roles->count() }}</span></a>
    </nav>

    @if ($tab === 'team')
        <section class="admin-card admin-card-flush">
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>Member</th><th>Role</th><th>Ticket departments</th><th>Security</th><th>Last sign-in</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>@foreach ($staff as $s)
                    <tr>
                        <td data-label="Member"><a class="admin-strong-link" href="{{ route('admin.staff.show', $s) }}">{{ $s->name }}</a><small class="admin-sub">{{ $s->email }}</small></td>
                        <td data-label="Role"><span class="badge {{ $s->staffRole?->is_super ? 'badge-warn' : 'badge-neutral' }}">{{ $s->staffRole?->name ?? 'No role' }}</span></td>
                        <td data-label="Departments">{{ $s->departments->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td data-label="Security">@if ($s->hasTwoFactor())<span class="badge badge-good">2FA on</span>@else<span class="badge badge-neutral">2FA off</span>@endif</td>
                        <td data-label="Last sign-in">{{ $s->last_staff_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td data-label="Status">@if ($s->is_active)<span class="badge badge-good">Active</span>@else<span class="badge badge-bad">Suspended</span>@endif</td>
                        <td class="admin-row-actions"><a class="admin-btn admin-btn-quiet admin-btn-sm" href="{{ route('admin.staff.show', $s) }}">Manage</a></td>
                    </tr>@endforeach</tbody>
            </table></div>
            {{ $staff->appends(['tab' => 'team'])->links() }}
        </section>

        @if ($canCreate && $invitations->isNotEmpty())
            <section class="admin-card"><h2>Invitations</h2>
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>Email</th><th>Role</th><th>Status</th><th>Expires</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>@foreach ($invitations as $inv)
                        <tr><td data-label="Email">{{ $inv->email }}</td><td data-label="Role">{{ $inv->role?->name }}</td><td data-label="Status"><x-status-badge :status="$inv->status()" /></td><td data-label="Expires">{{ $inv->expires_at->format('d M Y H:i') }}</td>
                            <td class="admin-row-actions">@if ($inv->status() === 'pending')<form method="POST" action="{{ route('admin.staff.invitations.revoke', $inv) }}">@csrf<button class="admin-link-danger" type="submit">Revoke</button></form>@endif</td></tr>
                    @endforeach</tbody>
                </table></div>
            </section>
        @endif
    @endif

    @if ($tab === 'add' && $canCreate)
        <div class="admin-grid-2">
            <section class="admin-card"><h2>Invite by email <span class="badge badge-good">Recommended</span></h2>
                <p class="muted small">Member ko ek link milta hai ({{ config('staff.invitation_hours') }} ghante valid). Wo khud password chunta hai, aapko pata nahi chalta.</p>
                <form method="POST" action="{{ route('admin.staff.invite') }}" class="admin-form" data-loading-form>@csrf
                    <input type="hidden" name="form" value="invite">
                    <label>Full name<input name="name" value="{{ old('form') === 'invite' ? old('name') : '' }}" required maxlength="120"></label>
                    <label>Work email<input type="email" name="email" value="{{ old('form') === 'invite' ? old('email') : '' }}" required maxlength="190"></label>
                    @include('admin.staff._role_select', ['roles' => $roles, 'isSuper' => $isSuper, 'menusFor' => $menusFor, 'id' => 'invite-role'])
                    @include('admin.staff._departments', ['departments' => $departments])
                    <button class="admin-btn" type="submit">Send invitation</button>
                </form>
            </section>

            <section class="admin-card"><h2>Create with temporary password</h2>
                <p class="muted small">Jab email invite kaam na kare tab. Member ko pehli sign-in par password badalna padega aur email code se verify karna hoga.</p>
                <form method="POST" action="{{ route('admin.staff.store') }}" class="admin-form" data-loading-form>@csrf
                    <input type="hidden" name="form" value="direct">
                    <label>Full name<input name="name" value="{{ old('form') === 'direct' ? old('name') : '' }}" required maxlength="120"></label>
                    <label>Work email<input type="email" name="email" value="{{ old('form') === 'direct' ? old('email') : '' }}" required maxlength="190"></label>
                    <label>Temporary password<input type="password" name="password" required autocomplete="new-password"><small class="muted">12+ characters: bada aur chhota akshar, number, symbol.</small></label>
                    @include('admin.staff._role_select', ['roles' => $roles, 'isSuper' => $isSuper, 'menusFor' => $menusFor, 'id' => 'direct-role'])
                    @include('admin.staff._departments', ['departments' => $departments])
                    <button class="admin-btn admin-btn-quiet" type="submit">Create staff account</button>
                </form>
            </section>
        </div>
    @endif

    @if ($tab === 'roles')
        <section class="admin-card">
            <h2>Roles & access</h2>
            <p class="muted small">Har role ke saamne dikhaya gaya hai ki us role ke member ko <strong>sidebar mein kya dikhega</strong>. Permissions badalte hi agle page load par sidebar badal jaata hai.@unless ($isSuper) Sirf Super Admin roles badal sakta hai.@endunless</p>
            <div class="admin-role-list">
            @foreach ($roles as $role)
                @php $granted = $role->permissions->pluck('permission')->all(); $menus = $menusFor($role); @endphp
                <details class="admin-role-card">
                    <summary>
                        <span class="admin-role-title"><strong>{{ $role->name }}</strong>
                            <small>{{ $role->users_count }} member{{ $role->users_count === 1 ? '' : 's' }} · {{ $role->is_super ? 'all permissions' : count($granted).' permissions' }}</small></span>
                        <span class="admin-chips">@forelse ($menus->take(6) as $m)<span class="admin-chip">{{ $m }}</span>@empty<span class="admin-chip admin-chip-muted">Sirf My profile</span>@endforelse @if ($menus->count() > 6)<span class="admin-chip admin-chip-muted">+{{ $menus->count() - 6 }} more</span>@endif</span>
                    </summary>
                    <div class="admin-role-body">
                        <p class="small"><strong>Sidebar menus:</strong> {{ $menus->implode(', ') ?: 'Sirf My profile' }}</p>
                        @if ($isSuper && ! $role->is_super)
                            <form method="POST" action="{{ route('admin.roles.save', $role) }}" class="admin-form">@csrf
                                <label>Role name<input name="name" value="{{ $role->name }}" required maxlength="80"></label>
                                @include('admin.staff._matrix', ['granted' => $granted, 'modules' => $modules, 'sidebarItems' => $sidebarItems])
                                <div class="admin-actions-row"><button class="admin-btn" type="submit">Save role</button></div>
                            </form>
                            @unless ($role->is_system || $role->users_count)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete role {{ $role->name }}?')">@csrf @method('DELETE')<button class="admin-link-danger" type="submit">Delete role</button></form>
                            @endunless
                        @elseif ($role->is_super)
                            <p class="muted small">Super Admin ke paas hamesha har permission hoti hai. Ise badla nahi ja sakta.</p>
                        @else
                            <p class="muted small">{{ implode(', ', $granted) ?: 'No permissions.' }}</p>
                        @endif
                    </div>
                </details>
            @endforeach
            </div>
        </section>
        @if ($isSuper)
            <details class="admin-card admin-disclosure" @if ($errors->any() && old('form') === 'role') open @endif>
                <summary><span><strong>+ New custom role</strong><small>Jaise "Accounts", "Night shift support" — sirf zaroori menus tick kijiye.</small></span></summary>
                <form method="POST" action="{{ route('admin.roles.create') }}" class="admin-form">@csrf
                    <input type="hidden" name="form" value="role">
                    <label>Role name<input name="name" required maxlength="80" placeholder="e.g. Night shift support"></label>
                    @include('admin.staff._matrix', ['granted' => [], 'modules' => $modules, 'sidebarItems' => $sidebarItems])
                    <div class="admin-actions-row"><button class="admin-btn" type="submit">Create role</button></div>
                </form>
            </details>
        @endif
    @endif
@endsection
