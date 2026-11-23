@extends('layouts.admin')

@section('title', 'Projects')
@section('heading', 'Projects')

@section('content')
    <form class="admin-filters" method="GET" action="{{ route('admin.projects.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Project number or title" aria-label="Search projects">
        <select name="status" aria-label="Status"><option value="">Status: all</option>@foreach (['planning', 'active', 'on_hold', 'review', 'completed', 'cancelled'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ \Illuminate\Support\Str::headline($s) }}</option>@endforeach</select>
        <select name="kind" aria-label="Type"><option value="">Type: all</option>@foreach (['wordpress', 'laravel', 'web', 'seo'] as $k)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ ucfirst($k) }}</option>@endforeach</select>
        <button class="admin-btn admin-btn-quiet" type="submit">Filter</button>
    </form>
    @if ($projects->isEmpty())
        <div class="admin-empty"><h2>No projects</h2><p>Projects open when a customer accepts a quote or buys a fixed-scope service.</p></div>
    @else
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Project</th><th>Customer</th><th>Type</th><th>Status</th><th>Assigned</th><th>Due</th></tr></thead>
            <tbody>@foreach ($projects as $p)
                <tr><td data-label="Project"><a href="{{ route('admin.projects.show', $p) }}">{{ $p->number }}</a> {{ $p->title }}</td><td data-label="Customer">{{ $p->user->email }}</td><td data-label="Type">{{ ucfirst($p->kind) }}</td><td data-label="Status"><x-status-badge :status="$p->status" /></td><td data-label="Assigned">{{ $p->assignee?->name ?? '—' }}</td><td data-label="Due">{{ $p->due_date?->format('d M Y') ?? '—' }}</td></tr>
            @endforeach</tbody>
        </table></div>{{ $projects->links() }}
    @endif
@endsection
