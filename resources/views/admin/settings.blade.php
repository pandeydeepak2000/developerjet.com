@extends('layouts.admin')

@section('title', 'Settings')
@section('heading', 'Settings')

@section('content')
    @if (! $canEdit)<div class="admin-note admin-note-info">Aapke paas settings sirf dekhne ki permission hai.</div>@endif

    <div class="admin-settings">
        <nav class="admin-settings-menu" aria-label="Settings sections">
            @foreach ($groups as $key => [$label, $hint])
                <a href="{{ route('admin.settings.edit', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>
                    <strong>{{ $label }}</strong><small>{{ $hint }}</small>
                </a>
            @endforeach
            @if (auth()->user()->isSuper())
                <a href="{{ route('admin.settings.staff-portal') }}"><strong>Staff login URL</strong><small>Change the private staff sign-in address</small></a>
            @endif
        </nav>

        <div class="admin-settings-body">
            <header class="admin-settings-head">
                <h2>{{ $groups[$tab][0] }}</h2>
                <p class="muted">{{ $groups[$tab][1] }}</p>
            </header>

            @if ($tab === 'payments')
                <div class="admin-stat-row admin-stat-row-2">
                    <div class="admin-stat"><span>Razorpay</span><strong>{{ \App\Support\Setting::bool('gateway.razorpay.enabled') ? 'On · '.ucfirst((string) \App\Support\Setting::get('gateway.razorpay.mode', 'test')) : 'Off' }}</strong></div>
                    <div class="admin-stat"><span>Wallet</span><strong>Always on</strong></div>
                </div>
                <div class="admin-note admin-note-info">
                    <strong>Wallet:</strong> customer wallet se pay kar sakta hai. Wallet top-up requests aap <a href="{{ route('admin.wallet.index') }}">Admin → Wallet</a> mein bank/UPI statement check karke credit karte hain. Iske liye koi setting nahi chahiye.
                </div>
            @endif

            @if ($tab === 'email' && $canEdit)
                <section class="admin-card"><h2>Send a test</h2>
                    <p class="muted small">Telegram admin chat par jaata hai. Email sirf aapke apne staff email par jaata hai. Dono queue se jaate hain, isliye queue worker (cron) chalna zaroori hai.</p>
                    <div class="admin-actions-row">
                        <form method="POST" action="{{ route('admin.settings.test-notification') }}" class="admin-inline-form">@csrf
                            <select name="channel" aria-label="Channel"><option value="telegram">Telegram</option><option value="email">Email (to me)</option><option value="sales_email">Sales notification email</option></select>
                            <button class="admin-btn admin-btn-quiet" type="submit">Send test</button>
                        </form>
                        @if (auth()->user()->isSuper())
                            <form method="POST" action="{{ route('admin.settings.test-smtp') }}" class="admin-inline-form">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Test SMTP connection</button></form>
                        @endif
                    </div>
                    @if (auth()->user()->isSuper())<p class="muted small">SMTP test sirf server se connect aur login check karta hai, koi email nahi bhejta. OTP codes turant jaate hain, unhe queue worker nahi chahiye.</p>@endif
                </section>
            @endif

            @foreach ($sections as $key => $def)
                <section class="admin-card admin-settings-card" id="{{ $key }}">
                    <h2>{{ $def['title'] }}</h2>
                    @isset($def['note'])
                        <details class="admin-help"><summary>Ye section kya karta hai?</summary><p>{{ $def['note'] }}</p></details>
                    @endisset
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
                                @if ($type === 'checkbox')
                                    <label class="admin-toggle span-2">
                                        <input type="checkbox" name="{{ $field }}" value="1" @checked(\App\Support\Setting::bool($name, (bool) $default)) @disabled(! $canEdit)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @else
                                    <label class="{{ $type === 'textarea' ? 'span-2' : '' }}">{{ $label }}
                                        @switch($type)
                                            @case('textarea')<textarea name="{{ $field }}" rows="4" @disabled(! $canEdit)>{{ $current }}</textarea>@break
                                            @case('select')<select name="{{ $field }}" @disabled(! $canEdit)>@foreach ($choices as $v => $l)<option value="{{ $v }}" @selected((string) $current === (string) $v)>{{ $l }}</option>@endforeach</select>@break
                                            @case('secret')<input type="password" name="{{ $field }}" autocomplete="new-password" placeholder="{{ $isSet ? '•••• stored encrypted — leave blank to keep' : 'Not set' }}" @disabled(! $canEdit)>@break
                                            @case('number')<input type="number" name="{{ $field }}" value="{{ $current }}" @disabled(! $canEdit)>@break
                                            @default<input type="text" name="{{ $field }}" value="{{ $current }}" @disabled(! $canEdit)>
                                        @endswitch
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        @if ($canEdit)<div class="admin-form-actions"><button class="admin-btn" type="submit">Save {{ strtolower($def['title']) }}</button></div>@endif
                    </form>
                </section>
            @endforeach
        </div>
    </div>
@endsection
