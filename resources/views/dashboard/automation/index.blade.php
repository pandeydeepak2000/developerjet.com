@extends('layouts.dashboard')

@section('title', 'Automation requests')
@section('heading', 'Business Automation requests')
@section('subheading', 'Requirements you have sent us, their progress and any quotation.')

@section('dash')
    @if ($leads->isEmpty())
        <x-empty-state title="No automation requests" text="Tell us what you would like to automate and our team will prepare a scope and quotation." action="Request Business Automation" :action-url="route('business-automation').'#request'" />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Reference</th><th>Business type</th><th>Status</th><th>Quotation</th><th>Submitted</th></tr></thead>
            <tbody>@foreach ($leads as $lead)
                <tr>
                    <td data-label="Reference"><a href="{{ route('dashboard.automation.show', $lead) }}">{{ $lead->reference }}</a></td>
                    <td data-label="Business type">{{ $lead->business_type }}</td>
                    <td data-label="Status"><x-status-badge :status="$lead->status" />&nbsp;{{ $lead->statusLabel() }}</td>
                    <td data-label="Quotation">{{ in_array($lead->status, ['quotation_sent', 'accepted', 'declined', 'converted'], true) && $lead->quote_minor !== null ? \App\Support\Money::inr($lead->quote_minor) : '—' }}</td>
                    <td data-label="Submitted">{{ $lead->created_at->format('d M Y') }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>{{ $leads->links() }}
    @endif
@endsection
