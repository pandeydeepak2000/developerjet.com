@extends('layouts.admin')

@section('title', 'Deployment #'.$deployment->id)
@section('heading', 'Deployment #'.$deployment->id)
@section('actions')<x-status-badge :status="$deployment->status" />@endsection

@section('content')
    @if ($deployment->mode === 'dry_run')
        <div class="flash flash-info" role="note"><strong>Dry run.</strong> This is a simulation. Nothing was installed or changed on the server, and the service was not activated.</div>
    @endif
    <section class="admin-card">
        <dl class="kv">
            <div><dt>Service</dt><dd>{{ $deployment->service->label }}</dd></div>
            <div><dt>Server</dt><dd>{{ $deployment->server->label }}</dd></div>
            <div><dt>Failed step</dt><dd>{{ $deployment->failed_step ?? '—' }}</dd></div>
            <div><dt>Recipe</dt><dd>{{ $deployment->recipe }} v{{ $deployment->recipe_version }}</dd></div>
            <div><dt>Attempt</dt><dd>{{ $deployment->attempt }}</dd></div>
            <div><dt>Mode</dt><dd>{{ $deployment->mode }}</dd></div>
            <div><dt>Error</dt><dd>{{ $deployment->error_summary ?? '—' }}</dd></div>
        </dl>
    </section>
    @php $registry = app(\App\Services\Provisioning\RecipeRegistry::class); $done = $deployment->completed_steps ?? []; @endphp
    <section class="admin-card"><h2>Installation progress</h2>
        <ol class="timeline">@foreach ($registry->steps($deployment->recipe) as $step)
            <li><strong>{{ $registry->label($step) }}</strong><span>@if ($deployment->failed_step === $step && $deployment->status === 'failed')<span class="badge badge-bad">failed here</span>@elseif (in_array($step, $done, true))<span class="badge badge-good">done</span>@elseif ($deployment->mode === 'dry_run')<span class="badge badge-neutral">simulated</span>@elseif (! $registry->approved($step))<span class="badge badge-warn">command not yet approved</span>@else<span class="badge badge-neutral">pending</span>@endif</span></li>
        @endforeach</ol>
    </section>
    <section class="admin-card"><h2>Log</h2>
        @forelse ($deployment->logs as $l)
            <div class="admin-log admin-log-{{ $l->level }}"><time>{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('H:i:s') }}</time><code>{{ $l->step }}</code> {{ $l->message }}</div>
        @empty<p class="muted">No log entries yet.</p>@endforelse
    </section>
@endsection
