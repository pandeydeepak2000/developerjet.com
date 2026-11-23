@extends('layouts.dashboard')

@section('title', 'Support')
@section('heading', 'Support tickets')
@section('subheading', 'Ask our team for help and follow the conversation.')

@section('dash')
    <p><a class="button-primary" href="{{ route('dashboard.tickets.create') }}">Open a ticket</a></p>
    @if ($tickets->isEmpty())
        <x-empty-state title="No tickets yet" text="When you open a ticket, it will appear here with its full history." />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Department</th><th>Status</th><th>Last activity</th></tr></thead>
            <tbody>
            @foreach ($tickets as $ticket)
                <tr><td><a href="{{ route('dashboard.tickets.show', $ticket) }}">{{ $ticket->number }}</a></td><td>{{ $ticket->subject }}</td><td>{{ $ticket->department->name }}</td><td><x-status-badge :status="$ticket->status" /></td><td>{{ $ticket->last_activity_at?->diffForHumans() }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $tickets->links() }}
    @endif
@endsection
