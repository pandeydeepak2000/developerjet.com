<fieldset class="admin-fieldset">
    <legend>Ticket departments <small class="muted">(kaun se support tickets dikhenge)</small></legend>
    <div class="admin-check-grid">
        @forelse ($departments as $d)
            <label class="admin-check"><input type="checkbox" name="departments[]" value="{{ $d->id }}" @checked(in_array($d->id, (array) ($selected ?? old('departments', []))))> {{ $d->name }}</label>
        @empty
            <p class="muted small">Abhi koi department nahi hai.</p>
        @endforelse
    </div>
</fieldset>
