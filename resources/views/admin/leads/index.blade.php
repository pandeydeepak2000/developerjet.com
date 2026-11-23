@extends('layouts.admin')

@section('title', 'Automation leads')
@section('heading', 'Business Automation leads')

@section('content')
    <div class="admin-kpis">
        <a class="admin-kpi" href="{{ route('admin.leads.index', ['status' => 'new']) }}"><span>New requests</span><strong>{{ $counts['new'] }}</strong></a>
        <a class="admin-kpi" href="{{ route('admin.leads.index', ['status' => 'open']) }}"><span>Open leads</span><strong>{{ $counts['open'] }}</strong></a>
        <a class="admin-kpi" href="{{ route('admin.leads.index', ['due' => 1]) }}"><span>Follow-ups due today</span><strong>{{ $counts['due'] }}</strong></a>
        <a class="admin-kpi" href="{{ route('admin.leads.index', ['status' => 'accepted']) }}"><span>Accepted, not converted</span><strong>{{ $counts['accepted'] }}</strong></a>
    </div>
    @unless (auth()->user()->hasPermission('leads.assign'))
        <p class="muted small">You see the leads assigned to you (as sales, technical or follow-up owner).</p>
    @endunless
    <form class="admin-filters" method="GET" action="{{ route('admin.leads.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Reference, name, email or company" aria-label="Search leads">
        <select name="status" aria-label="Status">
            <option value="">Status: all</option>
            <option value="open" @selected(request('status') === 'open')>Open (not decided/closed)</option>
            @foreach (\App\Support\LeadStatus::LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        </select>
        <label class="admin-check"><input type="checkbox" name="mine" value="1" @checked(request('mine'))> Mine</label>
        <label class="admin-check"><input type="checkbox" name="due" value="1" @checked(request('due'))> Follow-up due</label>
        <button class="admin-btn admin-btn-quiet" type="submit">Filter</button>
    </form>
    @if ($leads->isEmpty())
        <div class="admin-empty"><h2>No leads</h2><p>Business Automation requests that match these filters will appear here.</p></div>
    @else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Reference</th><th>Customer</th><th>Business type</th><th>Status</th><th>Sales owner</th><th>Follow-up</th><th>Received</th></tr></thead>
            <tbody>@foreach ($leads as $lead)
                <tr>
                    <td data-label="Reference"><a href="{{ route('admin.leads.show', $lead) }}">{{ $lead->reference }}</a></td>
                    <td data-label="Customer">{{ $lead->name }}@if ($lead->company)<br><span class="muted small">{{ $lead->company }}</span>@endif</td>
                    <td data-label="Business type">{{ \Illuminate\Support\Str::limit($lead->business_type, 40) }}</td>
                    <td data-label="Status">{{ $lead->statusLabel() }}</td>
                    <td data-label="Sales owner">{{ $lead->assignee?->name ?? 'Unassigned' }}</td>
                    <td data-label="Follow-up">@if ($lead->follow_up_at)<span @class(['badge badge-bad' => $lead->follow_up_at->isPast() && in_array($lead->status, \App\Support\LeadStatus::OPEN, true)])>{{ $lead->follow_up_at->format('d M H:i') }}</span>@else — @endif</td>
                    <td data-label="Received">{{ $lead->created_at->format('d M Y') }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
        {{ $leads->links() }}
    @endif
@endsection
