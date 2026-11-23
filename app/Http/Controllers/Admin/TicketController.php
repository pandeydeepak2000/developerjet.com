<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff ticket queue. Every action checks the permission AND that the staff member may see the ticket's
 * department (Super Admins see all). Support roles never get payment secrets, credentials or wallet rights from here.
 */
class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    /** Record-level authorisation: permission AND department membership. */
    private function guard(Request $request, Ticket $ticket, string ...$anyOf): void
    {
        $anyOf = $anyOf ?: ['tickets.view'];
        $allowed = collect($anyOf)->contains(fn ($p) => $request->user()->hasPermission($p));
        abort_unless($allowed && TicketService::staffCanAccess($request->user(), $ticket), 403);
    }

    /** Departments the current staff member may work in. */
    private function visibleDepartmentIds(User $user): ?array
    {
        return $user->isSuper() ? null : $user->departments()->pluck('departments.id')->all();
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tickets.view'), 403);
        $user = $request->user();
        $visible = $this->visibleDepartmentIds($user);

        $query = Ticket::with('user', 'department', 'assignee')->orderByRaw("case when status = 'closed' then 1 else 0 end")->latest('last_activity_at');

        if ($visible !== null) {
            $query->whereIn('department_id', $visible);
        }
        if (($status = $request->query('status')) && in_array($status, Ticket::STATUSES, true)) {
            $query->where('status', $status);
        } elseif ($status === 'active' || $status === null) {
            $query->where('status', '!=', 'closed');
        }
        if (($priority = $request->query('priority')) && in_array($priority, Ticket::PRIORITIES, true)) {
            $query->where('priority', $priority);
        }
        if ($dept = $request->integer('department')) {
            $query->where('department_id', $dept);
        }
        match ($request->query('assignee')) {
            'mine' => $query->where('assigned_to', $user->id),
            'unassigned' => $query->whereNull('assigned_to'),
            default => null,
        };
        if ($request->query('mine')) { // legacy link
            $query->where('assigned_to', $user->id);
        }
        if ($request->boolean('escalated')) {
            $query->where('escalation_level', '>', 0);
        }
        if ($request->boolean('breached')) {
            $query->whereNotNull('sla_breached_at');
        }
        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('subject', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $like)->orWhere('name', 'like', $like)));
        }

        return view('admin.tickets.index', [
            'tickets' => $query->paginate(20)->withQueryString(),
            'departments' => Department::when($visible !== null, fn ($q) => $q->whereIn('id', $visible))->orderBy('name')->get(['id', 'name']),
            'statuses' => Ticket::STATUSES, 'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->guard($request, $ticket);
        $ticket->load('user', 'department', 'assignee', 'service');

        // Only staff who can see this department are offered as assignees.
        $assignable = User::where('role', User::ROLE_STAFF)->where('is_active', true)->with('staffRole')->orderBy('name')->get()
            ->filter(fn (User $u) => TicketService::staffCanAccess($u, $ticket))->values();

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'replies' => $ticket->replies()->with('user', 'attachments')->orderBy('id')->get(),
            'events' => $ticket->events()->with('user')->latest('id')->limit(50)->get(),
            'staff' => $assignable,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'history' => Ticket::where('user_id', $ticket->user_id)->whereKeyNot($ticket->id)
                ->when($this->visibleDepartmentIds($request->user()) !== null, fn ($q) => $q->whereIn('department_id', $this->visibleDepartmentIds($request->user())))
                ->latest('id')->limit(10)->get(['id', 'number', 'subject', 'status', 'created_at']),
            'statuses' => Ticket::STATUSES, 'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        // tickets.reply is the dedicated permission; tickets.edit keeps working for roles created before it existed.
        $this->guard($request, $ticket, 'tickets.reply', 'tickets.edit');
        $data = $request->validate([
            'body' => ['required', 'string', 'max:8000'], 'private' => ['nullable', 'boolean'],
            'files' => TicketService::fileRules(), 'files.*' => TicketService::fileItemRules(),
        ]);

        $this->tickets->reply($ticket, $request->user(), $data['body'], true, $request->boolean('private'), $request->file('files', []));

        return back()->with('success', $request->boolean('private') ? 'Private note saved.' : 'Reply sent.');
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->guard($request, $ticket, 'tickets.assign');
        $data = $request->validate(['assigned_to' => ['nullable', 'integer']]);

        $assignee = ! empty($data['assigned_to']) ? User::where('role', User::ROLE_STAFF)->where('is_active', true)->findOrFail($data['assigned_to']) : null;

        try {
            $this->tickets->assign($ticket, $assignee, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ticket assignment updated.');
    }

    public function status(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->guard($request, $ticket, 'tickets.edit');
        $data = $request->validate(['status' => ['required', Rule::in(Ticket::STATUSES)]]);
        $this->tickets->changeStatus($ticket, $data['status'], $request->user());

        return back()->with('success', 'Status updated.');
    }

    public function priority(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->guard($request, $ticket, 'tickets.edit');
        $data = $request->validate(['priority' => ['required', Rule::in(Ticket::PRIORITIES)]]);
        $this->tickets->changePriority($ticket, $data['priority'], $request->user());

        return back()->with('success', 'Priority updated.');
    }

    public function transfer(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->guard($request, $ticket, 'tickets.assign');
        $data = $request->validate(['department_id' => ['required', 'integer']]);
        $to = Department::where('is_active', true)->findOrFail($data['department_id']);
        $this->tickets->transfer($ticket, $to, $request->user());

        // The mover may no longer see the ticket in its new department.
        return TicketService::staffCanAccess($request->user(), $ticket->fresh())
            ? back()->with('success', 'Ticket moved to '.$to->name.'.')
            : redirect()->route('admin.tickets.index')->with('success', 'Ticket moved to '.$to->name.'.');
    }

    public function escalate(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->guard($request, $ticket, 'tickets.edit', 'tickets.assign');
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
        ]);
        abort_if($ticket->escalation_level >= TicketService::MAX_ESCALATION, 422, 'This ticket is already at the highest escalation level.');

        $this->tickets->escalate($ticket, $request->user(), $data['reason'], $data['priority'] ?? null);

        return back()->with('success', 'Ticket escalated.');
    }

    public function attachment(Request $request, Ticket $ticket, Attachment $attachment): StreamedResponse
    {
        $this->guard($request, $ticket);
        abort_unless($attachment->attachable_type === (new TicketReply)->getMorphClass()
            && TicketReply::whereKey($attachment->attachable_id)->where('ticket_id', $ticket->id)->exists(), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
