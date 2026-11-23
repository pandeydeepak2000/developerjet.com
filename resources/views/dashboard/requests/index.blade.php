@extends('layouts.dashboard')

@section('title', 'Quotes')
@section('heading', 'Quote requests')
@section('subheading', 'Requirements you have submitted and the quotes we have prepared.')

@section('dash')
    @if ($requests->isEmpty())
        <x-empty-state title="No quote requests" text="Request a quote from any WordPress, Laravel, web development or SEO service page." action="Browse services" :action-url="route('services.show', 'wordpress')" />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Request</th><th>Service</th><th>Status</th><th>Quote</th><th>Submitted</th></tr></thead>
            <tbody>@foreach ($requests as $r)
                <tr><td><a href="{{ route('dashboard.requests.show', $r) }}">{{ $r->number }}</a></td><td>{{ $r->plan?->name }}</td><td><x-status-badge :status="$r->status" /></td><td>{{ \App\Support\Money::inr($r->quote_minor) }}</td><td>{{ $r->created_at->format('d M Y') }}</td></tr>
            @endforeach</tbody>
        </table></div>{{ $requests->links() }}
    @endif
@endsection
