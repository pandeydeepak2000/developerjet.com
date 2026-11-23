@extends('layouts.admin')

@section('title', $ticket->number)
@section('heading', $ticket->number.' · '.$ticket->subject)
@section('actions')<x-status-badge :status="$ticket->status" />@if ($ticket->escalation_level) <span class="badge badge-bad">Escalation level {{ $ticket->escalation_level }}</span>@endif @endsection

@section('content')
    @php $u = auth()->user(); $canReply = $u->hasPermission('tickets.reply') || $u->hasPermission('tickets.edit'); @endphp
    <div class="admin-grid-2">
        <section class="admin-card">
            <dl class="kv">
                <div><dt>Customer</dt><dd>{{ $ticket->user->name }} ({{ $ticket->user->email }})</dd></div>
                <div><dt>Department</dt><dd>{{ $ticket->department->name }}</dd></div>
                <div><dt>Priority</dt><dd>{{ ucfirst($ticket->priority) }}</dd></div>
                <div><dt>Service</dt><dd>{{ $ticket->service?->label ?? '—' }}</dd></div>
                <div><dt>Assigned</dt><dd>{{ $ticket->assignee?->name ?? 'Unassigned' }}</dd></div>
                <div><dt>First response</dt><dd>{{ $ticket->first_response_at?->format('d M Y H:i') ?? 'Not yet' }}@if ($ticket->first_response_due_at) <span class="muted small">(target {{ $ticket->first_response_due_at->format('d M H:i') }})</span>@endif</dd></div>
                <div><dt>Resolution target</dt><dd>{{ $ticket->resolution_due_at?->format('d M Y H:i') ?? 'No SLA configured' }}@if ($ticket->sla_breached_at) <span class="badge badge-bad">SLA missed</span>@endif</dd></div>
            </dl>
        </section>
        <section class="admin-card admin-ticket-controls">
            @if ($u->hasPermission('tickets.assign'))
                <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="admin-inline-form">@csrf
                    <label class="sr-only" for="assign-to">Assign to</label>
                    <select id="assign-to" name="assigned_to"><option value="">Unassigned</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($ticket->assigned_to === $s->id)>{{ $s->name }} · {{ $s->staffRole?->name }}</option>@endforeach</select><button class="admin-btn admin-btn-quiet">Assign</button></form>
                <form method="POST" action="{{ route('admin.tickets.transfer', $ticket) }}" class="admin-inline-form">@csrf
                    <label class="sr-only" for="move-to">Move to department</label>
                    <select id="move-to" name="department_id">@foreach ($departments as $d)<option value="{{ $d->id }}" @selected($ticket->department_id === $d->id)>{{ $d->name }}</option>@endforeach</select><button class="admin-btn admin-btn-quiet">Move</button></form>
            @endif
            @if ($u->hasPermission('tickets.edit'))
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="admin-inline-form">@csrf
                    <label class="sr-only" for="t-status">Status</label>
                    <select id="t-status" name="status">@foreach ($statuses as $s)<option value="{{ $s }}" @selected($ticket->status === $s)>{{ \Illuminate\Support\Str::headline($s) }}</option>@endforeach</select><button class="admin-btn admin-btn-quiet">Update status</button></form>
                <form method="POST" action="{{ route('admin.tickets.priority', $ticket) }}" class="admin-inline-form">@csrf
                    <label class="sr-only" for="t-priority">Priority</label>
                    <select id="t-priority" name="priority">@foreach ($priorities as $p)<option value="{{ $p }}" @selected($ticket->priority === $p)>{{ ucfirst($p) }}</option>@endforeach</select><button class="admin-btn admin-btn-quiet">Set priority</button></form>
            @endif
            @if (($u->hasPermission('tickets.edit') || $u->hasPermission('tickets.assign')) && $ticket->escalation_level < \App\Services\TicketService::MAX_ESCALATION)
                <details><summary>Escalate</summary>
                    <form method="POST" action="{{ route('admin.tickets.escalate', $ticket) }}" class="admin-form">@csrf
                        <label>Reason<input name="reason" required maxlength="255"></label>
                        <label>Raise priority to<select name="priority"><option value="">Keep {{ $ticket->priority }}</option>@foreach ($priorities as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach</select></label>
                        <button class="admin-btn admin-btn-danger" type="submit">Escalate</button>
                    </form>
                </details>
            @endif
        </section>
    </div>
    <section class="admin-card"><h2>Conversation</h2>
        <div class="thread">
            @foreach ($replies as $r)
                <article class="message {{ $r->is_staff ? 'message-staff' : '' }} {{ $r->is_private ? 'message-private' : '' }}">
                    <header><strong>{{ $r->user?->name ?? 'Unknown' }}</strong>@if ($r->is_private)<span class="badge badge-neutral">Private staff note</span>@endif<time>{{ $r->created_at->format('d M Y, H:i') }}</time></header>
                    <p>{!! nl2br(e($r->body)) !!}</p>
                    @foreach ($r->attachments as $a)<a class="attachment" href="{{ route('admin.tickets.attachment', [$ticket, $a]) }}">📎 {{ $a->original_name }}</a>@endforeach
                </article>
            @endforeach
        </div>
    </section>
    @if ($canReply)
        <form class="admin-card admin-form" method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" data-loading-form>@csrf
            <label>Reply<textarea name="body" rows="5" required maxlength="8000"></textarea></label>
            <label>Attachments (up to 3: jpg, png, pdf, txt, zip; 5 MB each)<input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.txt,.zip"></label>
            <label class="admin-check"><input type="checkbox" name="private" value="1"> Private staff note (customer cannot see it)</label>
            <button class="admin-btn" type="submit">Send</button>
        </form>
    @endif
    <div class="admin-grid-2">
        <section class="admin-card"><h2>Activity log</h2>
            @if ($events->isEmpty())<p class="muted">No activity recorded yet.</p>@else
            <ul class="admin-timeline">@foreach ($events as $e)<li><time>{{ $e->created_at?->format('d M H:i') }}</time> {{ $e->describe() }} <span class="muted small">· {{ $e->user?->name ?? 'System' }}</span></li>@endforeach</ul>@endif
        </section>
        <section class="admin-card"><h2>Customer's other tickets</h2>
            @if ($history->isEmpty())<p class="muted">No other tickets you can see.</p>@else
            <ul class="admin-timeline">@foreach ($history as $h)<li><a href="{{ route('admin.tickets.show', $h->id) }}">{{ $h->number }}</a> {{ \Illuminate\Support\Str::limit($h->subject, 40) }} <span class="muted small">· {{ \Illuminate\Support\Str::headline($h->status) }} · {{ $h->created_at->format('d M Y') }}</span></li>@endforeach</ul>@endif
        </section>
    </div>
@endsection
