@extends('layouts.admin')

@section('title', $sr->number)
@section('heading', $sr->number.' · '.($sr->plan?->name ?? $sr->kind))
@section('actions')<x-status-badge :status="$sr->status" />@endsection

@section('content')
    <div class="admin-grid-2">
        <section class="admin-card"><h2>Requirements</h2>
            <p>{{ $sr->user->name }} <small>{{ $sr->user->email }}</small></p>
            <dl class="kv">@foreach ($sr->requirements as $k => $v)@if ($v)<div><dt>{{ \Illuminate\Support\Str::headline($k) }}</dt><dd>{!! nl2br(e(collect(config('service_forms.'.$sr->kind.'.fields'))->firstWhere(0, $k)[4][$v] ?? $v)) !!}</dd></div>@endif @endforeach</dl>
            @foreach ($sr->attachments as $a)<a class="attachment" href="{{ route('admin.requests.attachment', [$sr, $a->id]) }}">📎 {{ $a->original_name }}</a>@endforeach
        </section>
        @if (config('service_forms.'.$sr->kind.'.quotable') === false)
        <section class="admin-card"><h2>Access request</h2>
            <p class="muted small">This customer asked for early access to a product that is not available yet. It cannot be quoted or charged. Contact the customer when access opens.</p>
        </section>
        @else
        <section class="admin-card"><h2>Quote</h2>
            <p class="muted small">A quote is a proposal only. Nothing is charged automatically; the customer must accept it and billing is arranged separately.</p>
            @if (auth()->user()->hasPermission('projects.edit') && in_array($sr->status, ['submitted', 'quoted']))
                <form method="POST" action="{{ route('admin.requests.quote', $sr) }}" class="admin-form">@csrf
                    <label>Quote amount (₹)<input type="number" name="quote" step="0.01" min="0" required value="{{ $sr->quote_minor !== null ? $sr->quote_minor / 100 : '' }}"></label>
                    <label>Scope notes<textarea name="quote_note" rows="4">{{ $sr->quote_note }}</textarea></label>
                    <button class="admin-btn" type="submit">{{ $sr->status === 'quoted' ? 'Update quote' : 'Send quote' }}</button>
                </form>
            @else
                <p>Quote: {{ \App\Support\Money::inr($sr->quote_minor) }}</p>
            @endif
            @if ($sr->project)<p><a href="{{ route('admin.projects.show', $sr->project) }}">Open project {{ $sr->project->number }}</a></p>@endif
        </section>
        @endif
    </div>
@endsection
