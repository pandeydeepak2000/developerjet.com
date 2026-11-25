@extends('layouts.admin')

@section('title', 'Deployment #'.$deployment->id)
@section('heading', 'Install log · '.$deployment->service->label)
@section('actions')<x-status-badge :status="$deployment->status" />@endsection

@section('content')
    @php
        $registry = app(\App\Services\Provisioning\RecipeRegistry::class);
        $done = $deployment->completed_steps ?? [];
        $running = in_array($deployment->status, ['queued', 'preflight', 'running'], true);
        $server = $deployment->server;
        $prompt = ($server->ssh_user ?? 'root').'@'.($server->ip_address ?? $server->label);
    @endphp

    @if ($deployment->mode === 'dry_run')
        <div class="admin-setup-note" role="note" style="margin-bottom:14px"><strong>Test run.</strong> Nothing was installed or changed on the server, and the service was not activated.</div>
    @endif

    <div class="dj-terminal" data-terminal data-feed="{{ route('admin.deployments.log', $deployment) }}" data-running="{{ $running ? '1' : '0' }}" data-last-id="{{ $deployment->logs->max('id') ?? 0 }}">
        <div class="dj-terminal-bar"><i></i><i></i><i></i><span>{{ $prompt }} · {{ $registry->recipe($deployment->recipe)['label'] ?? $deployment->recipe }}{{ $deployment->mode === 'dry_run' ? ' · test run' : '' }}</span>
            <span class="dj-term-status" data-term-status>{{ $running ? 'Running…' : \Illuminate\Support\Str::headline($deployment->status) }}</span></div>
        <pre class="dj-terminal-body" data-term-body><span class="ln ln-muted">$ deploy {{ $deployment->recipe }} --attempt={{ $deployment->attempt }}{{ $deployment->mode === 'dry_run' ? ' --dry-run' : '' }}</span>
@foreach ($deployment->logs as $l)<span class="ln ln-{{ $l->level === 'info' ? 'ok' : ($l->level === 'warn' ? 'warn' : ($l->level === 'error' ? 'error' : 'muted')) }}" data-ln><time>{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('H:i:s') }}</time><span class="tag">[{{ $l->step }}]</span>{{ $l->message }}</span>
@endforeach
@if ($running)<span class="ln ln-muted" data-term-wait>{{ $deployment->status === 'queued' ? 'Waiting for the background worker to pick this up…' : 'Working…' }} <span class="cursor"></span></span>@else<span class="ln {{ $deployment->status === 'succeeded' ? 'ln-ok' : ($deployment->status === 'failed' ? 'ln-error' : 'ln-muted') }}">— {{ $deployment->status === 'failed' ? 'Stopped: '.($deployment->error_summary ?? 'see the lines above') : 'Finished ('.\Illuminate\Support\Str::headline($deployment->status).')' }}</span>@endif</pre>
    </div>

    <ul class="dj-steps-mini" aria-label="Steps">
        @foreach ($registry->steps($deployment->recipe) as $step)
            <li class="{{ $deployment->failed_step === $step && $deployment->status === 'failed' ? 'is-failed' : (in_array($step, $done, true) || $deployment->status === 'dry_run_ok' ? 'is-done' : '') }}">{{ $registry->label($step) }}</li>
        @endforeach
    </ul>

    <div class="admin-grid-2" style="margin-top:18px">
        <section class="admin-card">
            <h2>Details</h2>
            <dl class="kv">
                <div><dt>Order</dt><dd>@if ($deployment->service->order)<a class="admin-strong-link" href="{{ route('admin.orders.show', $deployment->service->order) }}#setup">{{ $deployment->service->order->number }}</a>@else — @endif</dd></div>
                <div><dt>Server</dt><dd>{{ $server->ip_address }} <small class="muted">{{ $server->label }}</small></dd></div>
                <div><dt>Mode</dt><dd>{{ $deployment->mode === 'dry_run' ? 'Test run' : 'Live install' }} · attempt {{ $deployment->attempt }}</dd></div>
                <div><dt>Recipe</dt><dd>{{ $deployment->recipe }} v{{ $deployment->recipe_version }}</dd></div>
                @if ($deployment->error_summary)<div><dt>Error</dt><dd>{{ $deployment->error_summary }}</dd></div>@endif
            </dl>
        </section>
        <section class="admin-card">
            <h2>Next</h2>
            @if ($deployment->service->order)
                <p><a class="admin-btn" href="{{ route('admin.orders.show', $deployment->service->order) }}#setup">Back to the order</a></p>
            @endif
            <p class="muted small">Passwords and keys are never written to this log.</p>
        </section>
    </div>
@endsection
