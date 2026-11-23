@extends('layouts.dashboard')

@section('title', 'Projects')
@section('heading', 'Projects')
@section('subheading', 'Milestones, deliverables and approvals for your service projects.')

@section('dash')
    @if ($projects->isEmpty())
        <x-empty-state title="No projects yet" text="Projects open after you accept a quote or purchase a fixed-scope service." />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Project</th><th>Type</th><th>Status</th><th>Due</th></tr></thead>
            <tbody>@foreach ($projects as $p)<tr><td><a href="{{ route('dashboard.projects.show', $p) }}">{{ $p->number }} · {{ $p->title }}</a></td><td>{{ ucfirst($p->kind) }}</td><td><x-status-badge :status="$p->status" /></td><td>{{ $p->due_date?->format('d M Y') ?? '—' }}</td></tr>@endforeach</tbody>
        </table></div>{{ $projects->links() }}
    @endif
@endsection
