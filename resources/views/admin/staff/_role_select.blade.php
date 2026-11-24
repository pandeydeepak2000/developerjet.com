{{-- Role picker that previews the sidebar menus the member will get. --}}
<label for="{{ $id }}">Role</label>
<select id="{{ $id }}" name="role_id" required data-role-preview="{{ $id }}-preview">
    <option value="" disabled @selected((string) old('role_id', $current ?? '') === '')>Role chuniye…</option>
    @foreach ($roles as $r)
        @if ($isSuper || ! $r->is_super)
            <option value="{{ $r->id }}" data-menus="{{ $menusFor($r)->implode(', ') ?: 'Sirf My profile' }}" @selected((string) old('role_id', $current ?? '') === (string) $r->id)>{{ $r->name }}</option>
        @endif
    @endforeach
</select>
<p class="admin-role-preview small" id="{{ $id }}-preview" aria-live="polite"></p>
