@extends('layouts.admin')

@section('title', $project->number)
@section('heading', $project->number.' · '.$project->title)
@section('actions')<x-status-badge :status="$project->status" />@if ($project->kind === 'seo') <a class="admin-btn admin-btn-quiet" href="{{ route('admin.seo.show', $project) }}">SEO workspace</a>@endif @endsection

@section('content')
    @php $u = auth()->user(); @endphp
    <div class="admin-grid-2">
        <section class="admin-card"><h2>Details</h2>
            <p>{{ $project->user->name }} <small>{{ $project->user->email }}</small></p>
            <div class="progress" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progress }}%"></span></div><p class="muted small">{{ $progress }}% milestones approved</p>
            @if ($u->hasPermission('projects.edit'))
            <form method="POST" action="{{ route('admin.projects.update', $project) }}" class="admin-form">@csrf @method('PUT')
                <label>Status<select name="status">@foreach (['planning', 'active', 'on_hold', 'review', 'completed', 'cancelled'] as $s)<option value="{{ $s }}" @selected($project->status === $s)>{{ \Illuminate\Support\Str::headline($s) }}</option>@endforeach</select></label>
                @if ($u->hasPermission('projects.assign'))<label>Assigned to<select name="assigned_to"><option value="">Unassigned</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($project->assigned_to === $s->id)>{{ $s->name }}</option>@endforeach</select></label>@else<input type="hidden" name="assigned_to" value="{{ $project->assigned_to }}">@endif
                <label>Due date<input type="date" name="due_date" value="{{ $project->due_date?->toDateString() }}"></label>
                <label>Summary<textarea name="summary" rows="3">{{ $project->summary }}</textarea></label>
                <button class="admin-btn" type="submit">Save</button>
            </form>
            @endif
            @if ($project->serviceRequest)<p><a href="{{ route('admin.requests.show', $project->serviceRequest) }}">View original requirements ({{ $project->serviceRequest->number }})</a></p>@endif
        </section>
        <section class="admin-card"><h2>Tasks</h2>
            @foreach ($project->tasks as $t)
                <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $t]) }}" class="admin-inline-form">@csrf @method('PUT')<span>{{ $t->title }}</span>
                    <select name="status" onchange="this.form.submit()" aria-label="Task status">@foreach (['todo', 'doing', 'done'] as $s)<option value="{{ $s }}" @selected($t->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select></form>
            @endforeach
            @if ($u->hasPermission('projects.edit'))
                <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" class="admin-inline-form">@csrf<input name="title" placeholder="New task" required maxlength="160"><button class="admin-btn admin-btn-quiet">Add</button></form>
            @endif
        </section>
    </div>

    <section class="admin-card"><h2>Milestones</h2>
        @foreach ($project->milestones->sortBy('sort_order') as $m)
            <div class="admin-service-row">
                <div><strong>{{ $m->title }}</strong> <x-status-badge :status="$m->status" />
                    @foreach ($m->attachments as $a)<a class="attachment" href="{{ route('admin.projects.attachment', [$project, $a]) }}">📎 {{ $a->original_name }}</a>@endforeach
                    @if ($m->client_feedback)<p class="muted small">Client feedback: {{ $m->client_feedback }}</p>@endif</div>
                @if ($u->hasPermission('projects.edit'))
                <form method="POST" action="{{ route('admin.projects.milestones.update', [$project, $m]) }}" enctype="multipart/form-data" class="admin-inline-form">@csrf @method('PUT')
                    @if ($m->status === 'pending')<button class="admin-btn admin-btn-quiet" name="action" value="start">Start</button>@endif
                    @if (in_array($m->status, ['in_progress', 'revision_requested']) && $u->hasPermission('projects.approve'))
                        <input type="file" name="files[]" multiple aria-label="Deliverables"><button class="admin-btn" name="action" value="request_approval">Upload & request client approval</button>@endif
                </form>
                @endif
            </div>
        @endforeach
        @if ($u->hasPermission('projects.edit'))
            <form method="POST" action="{{ route('admin.projects.milestones.store', $project) }}" class="admin-inline-form">@csrf<input name="title" placeholder="New milestone" required maxlength="160"><input type="date" name="due_date" aria-label="Due"><button class="admin-btn admin-btn-quiet">Add milestone</button></form>
        @endif
    </section>

    <section class="admin-card"><h2>History & communication</h2>
        <div class="thread">@foreach ($project->messages->sortBy('id') as $m)
            <article class="message {{ $m->is_staff ? 'message-staff' : '' }}"><header><strong>{{ $m->type === 'history' ? 'History' : ($m->user?->name ?? 'Unknown') }}</strong><time>{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d M Y H:i') }}</time></header><p>{!! nl2br(e($m->body)) !!}</p></article>
        @endforeach</div>
        @if ($u->hasPermission('projects.edit'))
            <form method="POST" action="{{ route('admin.projects.message', $project) }}" class="admin-form">@csrf<label>Message to customer<textarea name="body" rows="3" required maxlength="4000"></textarea></label><button class="admin-btn">Send</button></form>
        @endif
    </section>
@endsection
