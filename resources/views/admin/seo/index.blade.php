@extends('layouts.admin')

@section('title', 'SEO services')
@section('heading', 'SEO services')

@section('content')
    <div class="flash flash-info" role="note">No search data source (Search Console, rank tracker) is connected. Only staff-entered audits and tasks are recorded here; nothing is estimated or promised.</div>
    @if ($projects->isEmpty())
        <div class="admin-empty"><h2>No SEO projects</h2><p>SEO projects appear after a customer accepts an SEO quote.</p></div>
    @else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Project</th><th>Customer</th><th>Assigned</th><th>Audits</th><th>Tasks</th><th>Reports</th></tr></thead>
            <tbody>@foreach ($projects as $p)<tr><td><a href="{{ route('admin.seo.show', $p) }}">{{ $p->number }}</a> {{ $p->title }}</td><td>{{ $p->user->email }}</td><td>{{ $p->assignee?->name ?? '—' }}</td><td>{{ $p->seo_audits_count }}</td><td>{{ $p->seo_tasks_count }}</td><td>{{ $p->seo_reports_count }}</td></tr>@endforeach</tbody>
        </table></div>{{ $projects->links() }}
    @endif
@endsection
