@extends('layouts.dashboard')

@section('title', $lead->reference)
@section('heading', 'Automation request '.$lead->reference)
@section('subheading', 'Submitted '.$lead->created_at->format('d M Y'))

@section('dash')
    @php $quoteVisible = in_array($lead->status, ['quotation_sent', 'accepted', 'declined', 'converted'], true); @endphp
    <div class="panel"><div class="panel-head"><h2>Status</h2><x-status-badge :status="$lead->status" /></div>
        <p><strong>{{ $lead->statusLabel() }}</strong> — {{ \App\Support\LeadStatus::customerText($lead->status) }}</p>
        @if ($quoteVisible && $lead->quote_summary)
            <h3>Quotation</h3>
            @if ($lead->quote_minor !== null)<p>Amount: <strong>{{ \App\Support\Money::inr($lead->quote_minor) }}</strong></p>@endif
            <p class="muted">{!! nl2br(e($lead->quote_summary)) !!}</p>
            @if ($lead->status === 'quotation_sent')
                <p class="muted small">Accepting records your agreement only. Nothing is charged automatically — our team will send any invoice separately.</p>
                <div class="btn-row">
                    <form method="POST" action="{{ route('dashboard.automation.decide', $lead) }}">@csrf<input type="hidden" name="decision" value="accept"><button class="button-primary" type="submit">Accept quotation</button></form>
                    <form method="POST" action="{{ route('dashboard.automation.decide', $lead) }}" onsubmit="return confirm('Decline this quotation?')">@csrf<input type="hidden" name="decision" value="decline"><button class="link-danger" type="submit">Decline</button></form>
                </div>
            @endif
        @endif
        @if ($lead->project && $lead->project->user_id === auth()->id())<p><a class="button-primary" href="{{ route('dashboard.projects.show', $lead->project) }}">Open project {{ $lead->project->number }}</a></p>@endif
    </div>

    <div class="panel"><h2>Updates</h2>
        <ul class="timeline">
            @foreach ($lead->customerEvents as $e)
                <li><span class="muted small">{{ $e->created_at->format('d M Y H:i') }}</span> {!! nl2br(e($e->body)) !!}</li>
            @endforeach
        </ul>
    </div>

    <div class="panel"><h2>Your requirements</h2>
        <dl class="kv">
            <div><dt>Business type</dt><dd>{{ $lead->business_type }}</dd></div>
            @if ($lead->company)<div><dt>Company</dt><dd>{{ $lead->company }}</dd></div>@endif
            @if ($lead->plan)<div><dt>Plan of interest</dt><dd>{{ $lead->plan->name }}</dd></div>@endif
            <div><dt>Requirements</dt><dd>{!! nl2br(e($lead->requirements)) !!}</dd></div>
            @if ($lead->integrations)<div><dt>Services to integrate</dt><dd>{!! nl2br(e($lead->integrations)) !!}</dd></div>@endif
            @if ($lead->current_tools)<div><dt>Current tools</dt><dd>{!! nl2br(e($lead->current_tools)) !!}</dd></div>@endif
            @if ($lead->timeline)<div><dt>Timeline</dt><dd>{{ \App\Models\AutomationRequest::TIMELINES[$lead->timeline] ?? $lead->timeline }}</dd></div>@endif
            @if ($lead->budget)<div><dt>Budget</dt><dd>{{ \App\Models\AutomationRequest::BUDGETS[$lead->budget] ?? $lead->budget }}</dd></div>@endif
        </dl>
    </div>
@endsection
