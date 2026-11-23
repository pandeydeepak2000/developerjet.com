@extends('layouts.dashboard')

@section('title', $project->number)
@section('heading', $project->number.' · '.$project->title)
@section('subheading', ucfirst($project->kind).' project')

@section('dash')
    <div class="panel">
        <div class="panel-head"><h2>Overview</h2><x-status-badge :status="$project->status" /></div>
        <div class="progress" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progress }}%"></span></div>
        <p class="muted small">{{ $progress }}% of milestones approved{{ $project->due_date ? ' · due '.$project->due_date->format('d M Y') : '' }}</p>
        @if ($project->summary)<p>{{ $project->summary }}</p>@endif
    </div>

    <div class="panel"><h2>Milestones & deliverables</h2>
        @forelse ($project->milestones as $m)
            <article class="milestone">
                <header><strong>{{ $m->title }}</strong> <x-status-badge :status="$m->status" /></header>
                @if ($m->description)<p class="muted">{{ $m->description }}</p>@endif
                @foreach ($m->attachments as $a)<a class="attachment" href="{{ route('dashboard.projects.attachment', [$project, $a]) }}">📎 {{ $a->original_name }}</a>@endforeach
                @if ($m->client_feedback)<p class="muted small">Your feedback: {{ $m->client_feedback }}</p>@endif
                @if ($m->status === 'awaiting_approval')
                    <form method="POST" action="{{ route('dashboard.projects.milestone', [$project, $m]) }}" class="form-grid">@csrf
                        <label class="span-2">Feedback (required for revisions)<textarea name="feedback" rows="2" maxlength="2000"></textarea></label>
                        <div class="btn-row span-2"><button class="button-primary" name="decision" value="approve" type="submit">Approve</button><button class="auth-secondary-action" name="decision" value="revise" type="submit">Request revision</button></div>
                    </form>
                @endif
            </article>
        @empty<p class="muted">Milestones will appear once the team plans the project.</p>@endforelse
    </div>

    @if ($project->kind === 'seo')
        <div class="panel"><h2>SEO activity</h2>
            <div class="flash flash-info" role="note">Only real, recorded work is shown here. We do not display estimated rankings or traffic, and we do not guarantee search results. No search data source is connected yet.</div>
            <h3>Completed tasks</h3>
            @forelse ($project->seoTasks->where('kind', 'task')->where('status', 'completed') as $t)<p>✓ {{ $t->title }} <small>{{ $t->period }}</small></p>@empty<p class="muted">None yet.</p>@endforelse
            <h3>Recommendations awaiting your decision</h3>
            @forelse ($project->seoTasks->where('kind', 'recommendation')->where('status', 'pending') as $t)
                <form method="POST" action="{{ route('dashboard.projects.seo-task', [$project, $t]) }}" class="inline-form">@csrf {{ $t->title }}
                    <button class="button-primary" name="decision" value="approved">Approve</button> <button class="link-danger" name="decision" value="declined">Decline</button></form>
            @empty<p class="muted">None pending.</p>@endforelse
            <h3>Monthly reports</h3>
            @forelse ($reports as $r)<p><a href="{{ route('dashboard.projects.report', [$project, $r]) }}">{{ $r->title }} ({{ $r->period }})</a>@if ($r->summary) — {{ $r->summary }}@endif</p>@empty<p class="muted">No reports published yet.</p>@endforelse
        </div>
    @endif

    <div class="panel"><h2>Communication</h2>
        <div class="thread">@foreach ($project->messages->sortBy('id') as $msg)
            <article class="message {{ $msg->is_staff ? 'message-staff' : '' }}"><header><strong>{{ $msg->type === 'history' ? 'Project history' : ($msg->is_staff ? 'DeveloperJet team' : 'You') }}</strong><time>{{ \Illuminate\Support\Carbon::parse($msg->created_at)->format('d M Y, H:i') }}</time></header><p>{!! nl2br(e($msg->body)) !!}</p></article>
        @endforeach</div>
        <form method="POST" action="{{ route('dashboard.projects.message', $project) }}" class="form-grid">@csrf
            <label class="span-2">Message or feedback<textarea name="body" rows="3" required maxlength="4000"></textarea></label>
            <div class="span-2"><button class="button-primary" type="submit">Send</button></div>
        </form>
    </div>

    <div class="panel"><h2>Invoices & support</h2>
        @forelse ($invoices as $i)<p><a href="{{ route('dashboard.invoices.download', $i) }}">{{ $i->number }}</a> · {{ \App\Support\Money::inr($i->total_minor) }}</p>@empty<p class="muted">No invoices are linked to this project yet.</p>@endforelse
        @foreach ($tickets as $t)<p><a href="{{ route('dashboard.tickets.show', $t) }}">{{ $t->number }} — {{ $t->subject }}</a></p>@endforeach
        <a class="button-primary" href="{{ route('dashboard.tickets.create') }}">Open a support ticket</a>
    </div>
@endsection
