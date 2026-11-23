@extends('layouts.admin')

@section('title', 'SEO · '.$project->number)
@section('heading', 'SEO workspace — '.$project->number)
@section('actions')<a class="admin-btn admin-btn-quiet" href="{{ route('admin.projects.show', $project) }}">Project</a>@endsection

@section('content')
    @php $u = auth()->user(); @endphp
    <div class="flash flash-info" role="note">Search Console integration: <strong>not connected</strong> (framework only). Audits below are manual entries.</div>
    <div class="admin-grid-2">
        <section class="admin-card"><h2>Audit records</h2>
            @forelse ($project->seoAudits as $a)<details><summary>{{ \Illuminate\Support\Str::headline($a->type) }} · {{ $a->website_url }} <small>({{ $a->source }}, {{ $a->created_at->format('d M Y') }})</small></summary><ul>@foreach ($a->findings ?? [] as $f)<li>{{ $f }}</li>@endforeach</ul></details>@empty<p class="muted">No audits recorded.</p>@endforelse
            @if ($u->hasPermission('seo.create'))
            <form method="POST" action="{{ route('admin.seo.audit', $project) }}" class="admin-form">@csrf
                <label>Website URL<input type="url" name="website_url" required></label>
                <label>Type<select name="type"><option value="technical">Technical</option><option value="meta_tags">Meta tags</option><option value="sitemap">Sitemap</option></select></label>
                <label>Findings (one per line)<textarea name="findings" rows="4" required></textarea></label>
                <button class="admin-btn admin-btn-quiet">Record audit</button></form>
            @endif
        </section>
        <section class="admin-card"><h2>Task checklist & recommendations</h2>
            @foreach ($project->seoTasks as $t)
                <div class="admin-list-row"><span>{{ $t->title }} <small>{{ $t->kind }} {{ $t->period }}</small></span>
                    @if ($t->kind === 'task' && $u->hasPermission('seo.edit'))
                        <form method="POST" action="{{ route('admin.seo.task-status', [$project, $t]) }}">@csrf @method('PUT')<select name="status" onchange="this.form.submit()" aria-label="Status"><option value="pending" @selected($t->status === 'pending')>Pending</option><option value="completed" @selected($t->status === 'completed')>Completed</option></select></form>
                    @else<x-status-badge :status="$t->status" />@endif</div>
            @endforeach
            @if ($u->hasPermission('seo.create'))
            <form method="POST" action="{{ route('admin.seo.task', $project) }}" class="admin-inline-form">@csrf<input name="title" placeholder="New item" required maxlength="200"><select name="kind"><option value="task">Task</option><option value="recommendation">Recommendation (client approves)</option></select><input name="period" placeholder="YYYY-MM" pattern="\d{4}-\d{2}" aria-label="Period"><button class="admin-btn admin-btn-quiet">Add</button></form>
            @endif
        </section>
    </div>
    <section class="admin-card"><h2>Monthly reports</h2>
        @forelse ($project->seoReports as $r)<div class="admin-list-row"><span>{{ $r->title }} <small>{{ $r->period }}</small></span>@if ($r->published_at)<x-status-badge status="sent" />@elseif ($u->hasPermission('seo.edit'))<form method="POST" action="{{ route('admin.seo.publish', [$project, $r]) }}">@csrf<button class="admin-btn admin-btn-quiet">Publish</button></form>@endif</div>@empty<p class="muted">No reports.</p>@endforelse
        @if ($u->hasPermission('seo.create'))
        <form method="POST" action="{{ route('admin.seo.report', $project) }}" enctype="multipart/form-data" class="admin-form admin-form-grid">@csrf
            <label>Period (YYYY-MM)<input name="period" required pattern="\d{4}-\d{2}"></label><label>Title<input name="title" required maxlength="160"></label>
            <label class="span-2">Summary<textarea name="summary" rows="2"></textarea></label>
            <label>PDF report<input type="file" name="file" accept=".pdf" required></label><label class="admin-check"><input type="checkbox" name="publish" value="1"> Publish to customer now</label>
            <div class="span-2"><button class="admin-btn">Upload report</button></div></form>
        @endif
    </section>
@endsection
