@extends('layouts.dashboard')

@section('title', $ticket->number)
@section('heading', $ticket->number.' · '.$ticket->subject)
@section('subheading', $ticket->department->name.' · Priority '.ucfirst($ticket->priority))

@section('dash')
    <div class="panel-head"><x-status-badge :status="$ticket->status" />
        @if ($ticket->status !== 'closed')
            <form method="POST" action="{{ route('dashboard.tickets.close', $ticket) }}" class="inline-form">@csrf<button class="link-danger" type="submit">Close ticket</button></form>
        @endif
    </div>
    <div class="thread">
        @foreach ($replies as $reply)
            <article class="message {{ $reply->is_staff ? 'message-staff' : '' }}">
                <header><strong>{{ $reply->is_staff ? 'DeveloperJet Support' : ($reply->user->name ?? 'You') }}</strong><time>{{ $reply->created_at->format('d M Y, H:i') }}</time></header>
                <p>{!! nl2br(e($reply->body)) !!}</p>
                @foreach ($reply->attachments as $a)
                    <a class="attachment" href="{{ route('dashboard.tickets.attachment', [$ticket, $a]) }}">📎 {{ $a->original_name }}</a>
                @endforeach
            </article>
        @endforeach
    </div>
    @if ($ticket->status !== 'closed')
        <form class="panel form-grid" method="POST" action="{{ route('dashboard.tickets.reply', $ticket) }}" enctype="multipart/form-data" data-loading-form>
            @csrf
            <label class="span-2">Reply<textarea name="body" rows="5" maxlength="8000" required></textarea></label>
            <label class="span-2">Attachments<input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.txt,.zip"></label>
            <div class="span-2"><button class="button-primary" type="submit">Send reply</button></div>
        </form>
    @endif
@endsection
