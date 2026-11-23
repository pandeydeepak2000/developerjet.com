<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerTicketController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.tickets.index', [
            'tickets' => Ticket::where('user_id', $request->user()->id)->with('department')->latest('last_activity_at')->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        // Optional deep link from a service page: preselect only the customer's OWN service.
        $preselect = (int) $request->query('service', 0);
        $preselect = $preselect && $request->user()->services()->whereKey($preselect)->exists() ? $preselect : null;

        return view('dashboard.tickets.create', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'services' => $request->user()->services()->get(),
            'preselect' => $preselect,
        ]);
    }

    public function store(Request $request, TicketService $tickets): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:8000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'service_id' => ['nullable', 'integer'],
            'files' => TicketService::fileRules(),
            'files.*' => TicketService::fileItemRules(),
        ]);

        // Only the customer's own service may be linked.
        $serviceId = ! empty($data['service_id']) && $request->user()->services()->whereKey($data['service_id'])->exists() ? (int) $data['service_id'] : null;

        $ticket = $tickets->open($request->user(), Department::findOrFail($data['department_id']), $data['subject'], $data['body'], $data['priority'], $serviceId, $request->file('files', []));

        return redirect()->route('dashboard.tickets.show', $ticket)->with('success', 'Ticket opened. We will reply by email and here.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        return view('dashboard.tickets.show', [
            'ticket' => $ticket->load('department', 'assignee'),
            // Private staff notes are excluded at the query level.
            'replies' => $ticket->replies()->where('is_private', false)->with('user', 'attachments')->orderBy('id')->get(),
        ]);
    }

    public function reply(Request $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $this->authorize('view', $ticket);

        abort_if($ticket->status === 'closed', 403, 'This ticket is closed.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:8000'],
            'files' => TicketService::fileRules(),
            'files.*' => TicketService::fileItemRules(),
        ]);

        $tickets->reply($ticket, $request->user(), $data['body'], false, false, $request->file('files', []));

        return back()->with('success', 'Reply sent.');
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);
        $ticket->update(['status' => 'closed', 'last_activity_at' => now()]);

        return back()->with('success', 'Ticket closed.');
    }

    /** Attachment download: allowed for the ticket owner (public replies only) — staff use the admin route. */
    public function attachment(Ticket $ticket, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $ticket);

        $reply = TicketReply::whereKey($attachment->attachable_id)->where('ticket_id', $ticket->id)->where('is_private', false)->first();
        abort_unless($attachment->attachable_type === (new TicketReply)->getMorphClass() && $reply, 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
