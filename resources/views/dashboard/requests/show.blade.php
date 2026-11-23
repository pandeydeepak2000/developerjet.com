@extends('layouts.dashboard')

@section('title', $sr->number)
@section('heading', $sr->number.' · '.($sr->plan?->name ?? ucfirst($sr->kind)))
@section('subheading', 'Submitted '.$sr->created_at->format('d M Y'))

@section('dash')
    <div class="panel"><div class="panel-head"><h2>Status</h2><x-status-badge :status="$sr->status" /></div>
        @if ($sr->quote_minor !== null)
            <p>Quote: <strong>{{ \App\Support\Money::inr($sr->quote_minor) }}</strong></p>
            @if ($sr->quote_note)<p class="muted">{!! nl2br(e($sr->quote_note)) !!}</p>@endif
            @if ($sr->status === 'quoted')
                <p class="muted small">Accepting records your approval and opens a project. You are not charged automatically; our team will arrange billing separately.</p>
                <div class="btn-row">
                    <form method="POST" action="{{ route('dashboard.requests.accept', $sr) }}">@csrf<button class="button-primary" type="submit">Accept quote</button></form>
                    <form method="POST" action="{{ route('dashboard.requests.decline', $sr) }}" onsubmit="return confirm('Decline this quote?')">@csrf<button class="link-danger" type="submit">Decline</button></form>
                </div>
            @endif
        @else
            <p class="muted">{{ config('service_forms.'.$sr->kind.'.pending_note') ?? 'We are reviewing your requirements. You will receive a quote by email.' }}</p>
        @endif
        @if ($sr->project)<p><a class="button-primary" href="{{ route('dashboard.projects.show', $sr->project) }}">Open project {{ $sr->project->number }}</a></p>@endif
    </div>
    <div class="panel"><h2>Your requirements</h2>
        <dl class="kv">@foreach ($sr->requirements as $k => $v)@if ($v)<div><dt>{{ \Illuminate\Support\Str::headline($k) }}</dt><dd>{!! nl2br(e(collect(config('service_forms.'.$sr->kind.'.fields'))->firstWhere(0, $k)[4][$v] ?? $v)) !!}</dd></div>@endif @endforeach</dl>
    </div>
@endsection
