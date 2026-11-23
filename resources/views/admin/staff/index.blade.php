@extends('layouts.admin')

@section('title', 'Staff & roles')
@section('heading', 'Staff & roles')

@section('content')
    @php $u = auth()->user(); @endphp
    <section class="admin-card"><h2>Staff accounts</h2>
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Departments</th><th>Active</th>@if ($u->hasPermission('staff.edit'))<th>Edit</th>@endif</tr></thead>
            <tbody>@foreach ($staff as $s)
                <tr>
                    <td data-label="Name"><a href="{{ route('admin.staff.show', $s) }}">{{ $s->name }}</a>@if ($s->hasTwoFactor()) <span class="badge badge-good" title="Two-factor on">2FA</span>@endif</td><td data-label="Email">{{ $s->email }}</td><td data-label="Role">{{ $s->staffRole?->name }}</td>
                    <td data-label="Departments">{{ $s->departments->pluck('name')->implode(', ') ?: '—' }}</td><td data-label="Active">{{ $s->is_active ? 'Yes' : 'Suspended' }}</td>
                    @if ($u->hasPermission('staff.edit'))
                    <td><form method="POST" action="{{ route('admin.staff.update', $s) }}" class="admin-inline-form">@csrf @method('PUT')
                        <select name="role_id" aria-label="Role">@foreach ($roles as $r)<option value="{{ $r->id }}" @selected($s->role_id === $r->id)>{{ $r->name }}</option>@endforeach</select>
                        <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked($s->is_active)> Active</label>
                        @foreach ($departments as $d)<label class="admin-check"><input type="checkbox" name="departments[]" value="{{ $d->id }}" @checked($s->departments->contains($d->id))> {{ $d->name }}</label>@endforeach
                        <button class="admin-btn admin-btn-quiet" type="submit">Save</button></form>
                        @if ($s->hasTwoFactor() && $s->id !== $u->id)<form method="POST" action="{{ route('admin.staff.reset-mfa', $s) }}" onsubmit="return confirm('Reset two-factor for {{ $s->name }}?')">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Reset 2FA</button></form>@endif</td>
                    @endif
                </tr>@endforeach</tbody>
        </table></div>
        {{ $staff->links() }}
    </section>

    @if ($u->hasPermission('staff.create'))
    <section class="admin-card"><h2>Invite staff member (recommended)</h2>
        <p class="muted small">The invitee receives a single-use link (valid {{ config('staff.invitation_hours') }} hours) and chooses their own password. Nobody else ever knows it.</p>
        <form method="POST" action="{{ route('admin.staff.invite') }}" class="admin-form admin-form-grid" data-loading-form>@csrf
            <label>Name<input name="name" required maxlength="120"></label><label>Email<input type="email" name="email" required maxlength="190"></label>
            <label>Role<select name="role_id">@foreach ($roles as $r)@if ($isSuper || ! $r->is_super)<option value="{{ $r->id }}">{{ $r->name }}</option>@endif @endforeach</select></label>
            <p class="muted small">Role choose karte hi us role ke sidebar menus neeche Roles &amp; permissions me dikhenge — wahi permissions se control hota hai member ko kaun sa menu milega.</p>
            <fieldset class="span-2"><legend>Ticket departments</legend>@foreach ($departments as $d)<label class="admin-check"><input type="checkbox" name="departments[]" value="{{ $d->id }}"> {{ $d->name }}</label>@endforeach</fieldset>
            <div class="span-2"><button class="admin-btn" type="submit">Send invitation</button></div>
        </form>
        @if ($invitations->isNotEmpty())
            <h3>Open and recent invitations</h3>
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>Email</th><th>Role</th><th>Status</th><th>Expires</th><th></th></tr></thead>
                <tbody>@foreach ($invitations as $inv)
                    <tr><td data-label="Email">{{ $inv->email }}</td><td data-label="Role">{{ $inv->role?->name }}</td><td data-label="Status">{{ ucfirst($inv->status()) }}</td><td data-label="Expires">{{ $inv->expires_at->format('d M Y H:i') }}</td>
                        <td>@if ($inv->status() === 'pending')<form method="POST" action="{{ route('admin.staff.invitations.revoke', $inv) }}">@csrf<button class="admin-link-danger" type="submit">Revoke</button></form>@endif</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>

    <section class="admin-card"><h2>Add staff member directly</h2>
        <form method="POST" action="{{ route('admin.staff.store') }}" class="admin-form admin-form-grid" data-loading-form>@csrf
            <label>Name<input name="name" required maxlength="120"></label><label>Email<input type="email" name="email" required></label>
            <label>Temporary password (12+ chars, upper/lower case, number, symbol; must be changed at first sign-in)<input type="password" name="password" required autocomplete="new-password"></label>
            <label>Role<select name="role_id">@foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></label>
            <fieldset class="span-2"><legend>Ticket departments</legend>@foreach ($departments as $d)<label class="admin-check"><input type="checkbox" name="departments[]" value="{{ $d->id }}"> {{ $d->name }}</label>@endforeach</fieldset>
            <div class="span-2"><button class="admin-btn" type="submit">Create staff account</button></div>
        </form>
    </section>
    @endif

    <section class="admin-card"><h2>Roles & permissions</h2>
        @unless ($isSuper)<p class="muted">Only a Super Admin can configure roles. Showing current grants.</p>@endunless
        @foreach ($roles as $role)
            @php $granted = $role->permissions->pluck('permission')->all(); @endphp
            <details class="admin-role">
                <summary><strong>{{ $role->name }}</strong> <small>{{ $role->users_count }} member(s){{ $role->is_super ? ' · all permissions' : '' }}</small></summary>
                @if ($isSuper && ! $role->is_super)
                    <form method="POST" action="{{ route('admin.roles.save', $role) }}" class="admin-form">@csrf
                        <label>Role name<input name="name" value="{{ $role->name }}" required maxlength="80"></label>
                        @include('admin.staff._matrix', ['granted' => $granted, 'modules' => $modules])
                        <button class="admin-btn" type="submit">Save role</button>
                    </form>
                    @unless ($role->is_system || $role->users_count)
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete role?')">@csrf @method('DELETE')<button class="admin-link-danger" type="submit">Delete role</button></form>
                    @endunless
                @else
                    <p class="muted small">{{ $role->is_super ? 'Every permission.' : (implode(', ', $granted) ?: 'No permissions.') }}</p>
                @endif
                <p class="muted small"><strong>Sidebar me dikhega:</strong> {{ $role->is_super ? collect($sidebarItems)->pluck(0)->implode(', ') : (collect($sidebarItems)->filter(fn ($i) => in_array($i[1], $granted))->pluck(0)->implode(', ') ?: 'Sirf My profile') }}</p>
            </details>
        @endforeach
        @if ($isSuper)
            <details class="admin-role"><summary><strong>+ New custom role</strong></summary>
                <form method="POST" action="{{ route('admin.roles.create') }}" class="admin-form">@csrf
                    <label>Role name<input name="name" required maxlength="80"></label>
                    @include('admin.staff._matrix', ['granted' => [], 'modules' => $modules])
                    <button class="admin-btn" type="submit">Create role</button>
                </form>
            </details>
        @endif
    </section>
@endsection
