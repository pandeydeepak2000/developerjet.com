@extends('layouts.admin')

@section('title', 'Support tickets')
@section('heading', 'Support tickets')
@section('actions')
    @if (auth()->user()->hasPermission('tickets.export') && Route::has('admin.exports.download'))
        <a class="admin-btn admin-btn-quiet" href="{{ route('admin.exports.download', ['type' => 'tickets'] + request()->only('status', 'priority', 'department', 'q')) }}">Export CSV</a>
    @endif
@endsection

@section('content')
    <form class="admin-filters" method="GET" action="{{ route('admin.tickets.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Number, subject or customer" aria-label="Search tickets">
        <select name="status" aria-label="Status">
            <option value="" @selected(! request('status'))>Status: active (not closed)</option>
            <option value="all" @selected(request('status') === 'all')>Status: all</option>
            @foreach ($statuses as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ \Illuminate\Support\Str::headline($s) }}</option>@endforeach
        </select>
        <select name="priority" aria-label="Priority"><option value="">Priority: any</option>@foreach ($priorities as $p)<option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>@endforeach</select>
        <select name="department" aria-label="Department"><option value="">Department: all mine</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected((int) request('department') === $d->id)>{{ $d->name }}</option>@endforeach</select>
        <select name="assignee" aria-label="Assignee"><option value="">Assignee: anyone</option><option value="mine" @selected(request('assignee') === 'mine' || request('mine'))>Assigned to me</option><option value="unassigned" @selected(request('assignee') === 'unassigned')>Unassigned</option></select>
        <label class="admin-check"><input type="checkbox" name="escalated" value="1" @checked(request('escalated'))> Escalated</label>
        <label class="admin-check"><input type="checkbox" name="breached" value="1" @checked(request('breached'))> SLA missed</label>
        <button class="admin-btn admin-btn-quiet" type="submit">Filter</button>
    </form>
    @if ($tickets->isEmpty())
        <div class="admin-empty"><h2>No tickets</h2><p>Tickets from your departments that match these filters will appear here.</p></div>
    @else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Department</th><th>Priority</th><th>Status</th><th>Assigned</th><th>SLA</th></tr></thead>
            <tbody>@foreach ($tickets as $t)
                <tr>
                    <td data-label="Ticket"><a href="{{ route('admin.tickets.show', $t) }}">{{ $t->number }}</a>@if ($t->escalation_level) <span class="badge badge-bad" title="Escalation level">E{{ $t->escalation_level }}</span>@endif</td>
                    <td data-label="Subject">{{ \Illuminate\Support\Str::limit($t->subject, 50) }}</td>
                    <td data-label="Customer">{{ $t->user->email }}</td>
                    <td data-label="Department">{{ $t->department->name }}</td>
                    <td data-label="Priority">{{ ucfirst($t->priority) }}</td>
                    <td data-label="Status"><x-status-badge :status="$t->status" /></td>
                    <td data-label="Assigned">{{ $t->assignee?->name ?? '—' }}</td>
                    <td data-label="SLA">
                        @if ($t->sla_breached_at)<span class="badge badge-bad">Missed</span>
                        @elseif ($t->first_response_at === null && $t->first_response_due_at)<span class="small">Reply by {{ $t->first_response_due_at->format('d M H:i') }}</span>
                        @elseif ($t->resolution_due_at && $t->status !== 'closed')<span class="small">Resolve by {{ $t->resolution_due_at->format('d M H:i') }}</span>
                        @else<span class="muted small">—</span>@endif
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>{{ $tickets->links() }}
    @endif
@endsection
