@extends('layouts.admin')

@section('title', 'Staff portal')
@section('heading', 'Settings · Staff portal')

@section('content')
    <section class="admin-card"><h2>Staff login URL</h2>
        <dl class="admin-dl">
            <dt>Current staff login URL</dt><dd><code class="break-all" data-current-url>{{ $currentUrl }}</code></dd>
            <dt>Current slug</dt><dd><code>{{ $slug }}</code></dd>
        </dl>
        <p class="muted small">Every staff role signs in at this one URL. Changing it takes effect immediately: the previous URL returns “not found”, people who are already signed in stay signed in, and nothing needs to be rebuilt. A hidden URL is only an extra layer — passwords, throttling, two-factor and permissions still protect the portal.</p>
        <p class="muted small">Locked out? On the server run <code>php artisan staff:portal --show</code> or <code>php artisan staff:portal --reset</code>.</p>
    </section>

    <section class="admin-card"><h2>Change the login slug</h2>
        <form method="POST" action="{{ route('admin.settings.staff-portal.update') }}" class="admin-form" autocomplete="off" data-staff-portal-form data-check-url="{{ route('admin.settings.staff-portal.check') }}">
            @csrf @method('PUT')
            <label>Custom staff login slug
                <input name="slug" value="{{ old('slug') }}" required minlength="{{ $min }}" maxlength="{{ $max }}" pattern="[a-z0-9]+(-[a-z0-9]+)*" autocapitalize="none" spellcheck="false" data-slug-input aria-describedby="slug-help slug-preview">
            </label>
            <p class="muted small" id="slug-help">{{ $min }}–{{ $max }} characters: lowercase letters, numbers and single hyphens. Reserved words (admin, login, staff…) are refused.</p>
            <p id="slug-preview" aria-live="polite">New staff login URL preview: <code class="break-all" data-slug-preview>{{ $base }}/<em>your-slug</em>/login</code> <span class="small" data-slug-status></span></p>
            @error('slug')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
            <fieldset><legend>Confirm it is you</legend>
                <label>Your password<input type="password" name="password" required autocomplete="current-password"></label>
                @if ($mfa)<label>Authenticator code<input name="code" autocomplete="one-time-code" maxlength="20" required></label>@endif
                @error('password')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
            </fieldset>
            <button class="admin-btn" type="submit">Save changes</button>
        </form>
    </section>

    <section class="admin-card"><h2>Change history</h2>
        @if ($history->isEmpty())<p class="muted">The login URL has never been changed (default slug in use).</p>@else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>When</th><th>By</th><th>From</th><th>To</th><th>Source</th></tr></thead>
            <tbody>@foreach ($history as $h)
                <tr><td data-label="When">{{ $h->created_at?->format('d M Y H:i') }}</td><td data-label="By">{{ $h->user?->name ?? 'Server command' }}</td>
                    <td data-label="From"><code>{{ $h->meta['old_path'] ?? '—' }}</code></td><td data-label="To"><code>{{ $h->meta['new_path'] ?? '—' }}</code></td>
                    <td data-label="Source">{{ $h->meta['source'] ?? '—' }}</td></tr>
            @endforeach</tbody>
        </table></div>@endif
    </section>
    <script>
    (function () {
        var form = document.querySelector('[data-staff-portal-form]'); if (!form) return;
        var input = form.querySelector('[data-slug-input]'), preview = form.querySelector('[data-slug-preview]'), status = form.querySelector('[data-slug-status]');
        var base = @json($base), timer;
        input.addEventListener('input', function () {
            var v = input.value.trim().toLowerCase();
            preview.textContent = base + '/' + (v || 'your-slug') + '/login';
            clearTimeout(timer);
            if (!v) { status.textContent = ''; return; }
            timer = setTimeout(function () {
                fetch(form.dataset.checkUrl + '?slug=' + encodeURIComponent(v), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (d) { status.textContent = d.ok ? '✓ Available' : '✗ ' + d.error; status.className = 'small ' + (d.ok ? 'text-good' : 'admin-error'); })
                    .catch(function () { status.textContent = ''; });
            }, 250);
        });
    })();
    </script>
@endsection
