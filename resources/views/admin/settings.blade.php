@extends('layouts.admin')

@section('title', 'Settings')
@section('heading', 'Settings')

@section('content')
    @if (! $canEdit)<div class="flash flash-info">You have read-only access to settings.</div>@endif
    @if ($canEdit)
    <section class="admin-card"><h2>Notification test</h2><p class="muted small">Telegram goes to the configured admin chat. Email goes only to your own staff address. Both are queued: run the queue worker.</p>
        <form method="POST" action="{{ route('admin.settings.test-notification') }}" class="admin-inline-form">@csrf<select name="channel" aria-label="Channel"><option value="telegram">Telegram</option><option value="email">Email (to me)</option><option value="sales_email">Sales notification email</option></select><button class="admin-btn admin-btn-quiet" type="submit">Send test</button></form>
        @if (auth()->user()->isSuper())<form method="POST" action="{{ route('admin.settings.test-smtp') }}" class="admin-inline-form">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Test SMTP connection (no email sent)</button></form><p class="muted small">The SMTP test only opens and authenticates a connection to the saved host and reports missing settings (smtp.host, smtp.username, smtp.password, smtp.from_address) — it sends nothing. Queued email still needs the queue worker: php artisan queue:work. OTP codes are sent synchronously and do not need the worker.</p>@endif</section>
    @endif
    <div class="admin-settings-nav">@foreach ($schema as $key => $def)<a href="#{{ $key }}">{{ $def['title'] }}</a>@endforeach</div>
    @foreach ($schema as $key => $def)
        <section class="admin-card" id="{{ $key }}">
            <h2>{{ $def['title'] }}</h2>
            @isset($def['note'])<p class="muted small">{{ $def['note'] }}</p>@endisset
            <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-form" autocomplete="off">
                @csrf @method('PUT')
                <input type="hidden" name="section" value="{{ $key }}">
                <div class="admin-form-grid">
                    @foreach ($def['fields'] as $f)
                        @php
                            [$name, $label, $type] = $f;
                            $field = str_replace('.', '__', $name);
                            $choices = $f[4] ?? [];
                            $default = $f[5] ?? null;
                            $current = old($field, \App\Support\Setting::get($name, $type === 'secret' ? null : $default));
                            $isSet = $type === 'secret' && \App\Support\Setting::has($name);
                        @endphp
                        <label class="{{ $type === 'textarea' ? 'span-2' : '' }}">{{ $label }}
                            @switch($type)
                                @case('textarea')<textarea name="{{ $field }}" rows="4" @disabled(! $canEdit)>{{ $current }}</textarea>@break
                                @case('checkbox')<input type="checkbox" name="{{ $field }}" value="1" @checked(\App\Support\Setting::bool($name, (bool) $default)) @disabled(! $canEdit)>@break
                                @case('select')<select name="{{ $field }}" @disabled(! $canEdit)>@foreach ($choices as $v => $l)<option value="{{ $v }}" @selected((string) $current === (string) $v)>{{ $l }}</option>@endforeach</select>@break
                                @case('secret')<input type="password" name="{{ $field }}" autocomplete="new-password" placeholder="{{ $isSet ? '•••• stored encrypted — leave blank to keep' : 'Not set' }}" @disabled(! $canEdit)>@break
                                @case('number')<input type="number" name="{{ $field }}" value="{{ $current }}" @disabled(! $canEdit)>@break
                                @default<input type="text" name="{{ $field }}" value="{{ $current }}" @disabled(! $canEdit)>
                            @endswitch
                        </label>
                    @endforeach
                </div>
                @if ($canEdit)<div class="admin-form-actions"><button class="admin-btn" type="submit">Save {{ strtolower($def['title']) }}</button></div>@endif
            </form>
        </section>
    @endforeach
@endsection
