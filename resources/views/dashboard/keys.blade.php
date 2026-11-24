@extends('layouts.dashboard')

@section('title', 'AI API keys')
@section('heading', 'AI API keys')
@section('subheading', 'Save your AI provider keys once and use them on any service.')

@section('dash')
    <div class="dash-grid keys-grid">
        <section class="dash-card dash-card--wide">
            <div class="dash-card__head"><h2>Saved keys</h2></div>
            @forelse ($keys as $key)
                <div class="key-row">
                    <span class="key-row__icon"><x-icon name="key" :size="20" /></span>
                    <span class="key-row__body">
                        <strong>{{ $key->label }}</strong>
                        <small>{{ $key->providerLabel() }} · <code>{{ $key->masked() }}</code> · added {{ $key->created_at->format('d M Y') }}</small>
                        <small>@if ($usage[$key->id]->isNotEmpty())Used by: {{ $usage[$key->id]->pluck('label')->implode(', ') }}@else Not used by a service yet @endif</small>
                    </span>
                    <form method="POST" action="{{ route('dashboard.keys.destroy', $key) }}" onsubmit="return confirm('Remove this key from your account? Services already set up keep their current key until you change it.');">
                        @csrf @method('DELETE')
                        <button class="dash-chip-link is-danger" type="submit">Remove</button>
                    </form>
                </div>
            @empty
                <div class="dash-empty">
                    <span class="dash-empty__icon"><x-icon name="key" :size="26" /></span>
                    <h3>No keys saved yet</h3>
                    <p>Add a key from OpenAI, Anthropic, Google Gemini or OpenRouter. Hermes, OpenClaw and Claude Code use it to answer your requests.</p>
                </div>
            @endforelse
        </section>

        <section class="dash-card">
            <div class="dash-card__head"><h2>Add a key</h2></div>
            <form method="POST" action="{{ route('dashboard.keys.store') }}" class="key-form" autocomplete="off" data-loading-form>
                @csrf
                <label>Provider
                    <select name="provider" required>
                        @foreach ($providers as $k => $l)<option value="{{ $k }}" @selected(old('provider') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </label>
                <label>Name <small class="muted">(optional)</small>
                    <input name="label" value="{{ old('label') }}" maxlength="80" placeholder="e.g. Company OpenAI key">
                </label>
                <label>API key
                    <input type="password" name="secret" required maxlength="300" autocomplete="new-password" placeholder="Paste your key">
                    @error('secret')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <button class="button-primary" type="submit">Save key</button>
            </form>
            <p class="setup-note"><x-icon name="lock" :size="16" /> Encrypted at rest. After saving we only show the last 4 characters. We never email it or show it to our staff.</p>
        </section>

        <section class="dash-card">
            <div class="dash-card__head"><h2>Where to get a key</h2></div>
            <ul class="dash-list key-help">
                <li><span><strong>OpenAI</strong><small>platform.openai.com → API keys</small></span></li>
                <li><span><strong>Anthropic (Claude)</strong><small>console.anthropic.com → API Keys</small></span></li>
                <li><span><strong>Google Gemini</strong><small>aistudio.google.com → Get API key</small></span></li>
                <li><span><strong>OpenRouter</strong><small>openrouter.ai → Keys (many models, one key)</small></span></li>
            </ul>
            <p class="muted small">AI usage is billed by your provider, not by DeveloperJet. n8n keeps its own credentials inside each workflow.</p>
        </section>
    </div>
@endsection
