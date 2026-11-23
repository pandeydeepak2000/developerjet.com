<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationRequest;
use App\Models\User;
use App\Services\AutomationLeadService;
use App\Services\OrderException;
use App\Services\ProjectService;
use App\Support\LeadStatus;
use App\Support\SafeText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Sales workflow for Business Automation leads. Permissions (server-side, every action):
 *  leads.view   — see leads you own (sales / technical / follow-up owner); with leads.assign you see all leads
 *  leads.edit   — notes, contact log, information requests, quotation, follow-ups, permitted status changes
 *  leads.assign — assign sales and technical owners, see every lead
 *  converting additionally needs projects.create. No action here takes or requests a payment.
 */
class AutomationLeadController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user();
        abort_unless($staff->hasPermission('leads.view'), 403);

        $query = AutomationRequest::query()->visibleTo($staff)->with('assignee', 'followUpOwner');
        if ($status = $request->query('status')) {
            if ($status === 'open') {
                $query->whereIn('status', LeadStatus::OPEN);
            } elseif (isset(LeadStatus::LABELS[$status])) {
                $query->where('status', $status);
            }
        }
        if ($request->boolean('mine')) {
            $query->where(fn ($q) => $q->where('assigned_to', $staff->id)->orWhere('technical_owner_id', $staff->id)->orWhere('follow_up_owner_id', $staff->id));
        }
        if ($request->boolean('due')) {
            $query->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay())->whereIn('status', LeadStatus::OPEN);
        }
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")->orWhere('company', 'like', "%{$search}%"));
        }

        $base = AutomationRequest::query()->visibleTo($staff);

        return view('admin.leads.index', [
            'leads' => $query->latest('id')->paginate(25)->withQueryString(),
            'counts' => [
                'new' => (clone $base)->where('status', 'new')->count(),
                'open' => (clone $base)->whereIn('status', LeadStatus::OPEN)->count(),
                'due' => (clone $base)->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay())->whereIn('status', LeadStatus::OPEN)->count(),
                'accepted' => (clone $base)->where('status', 'accepted')->count(),
            ],
        ]);
    }

    public function show(Request $request, AutomationRequest $lead): View
    {
        $this->authorizeView($request, $lead);
        $staffList = User::where('role', User::ROLE_STAFF)->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $u) => AutomationLeadService::assignable($u))->values();

        return view('admin.leads.show', [
            'lead' => $lead->load('events.user', 'assignee', 'technicalOwner', 'followUpOwner', 'user', 'plan', 'project'),
            'staffList' => $staffList,
            'transitions' => LeadStatus::TRANSITIONS[$lead->status] ?? [],
            'matchingAccount' => AutomationLeadService::matchingAccount($lead),
        ]);
    }

    public function status(Request $request, AutomationRequest $lead, AutomationLeadService $leads): RedirectResponse
    {
        $this->authorizeEdit($request, $lead);
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', array_keys(LeadStatus::LABELS))], 'note' => ['nullable', 'string', 'max:2000']]);

        return $this->attempt(fn () => $leads->transition($lead, $data['status'], $request->user(), SafeText::block($data['note'] ?? null, 2000)),
            'Status changed to “'.LeadStatus::label($data['status']).'”.');
    }

    public function note(Request $request, AutomationRequest $lead, AutomationLeadService $leads): RedirectResponse
    {
        $this->authorizeEdit($request, $lead);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000'], 'type' => ['required', 'in:note,contact'], 'channel' => ['nullable', 'in:phone,email,whatsapp,meeting,other']]);
        $body = SafeText::block($data['body'], 4000) ?? abort(422);
        if ($data['type'] === 'contact') {
            $body = 'Contacted customer'.(isset($data['channel']) ? ' by '.$data['channel'] : '').': '.$body;
        }
        $leads->note($lead, $request->user(), $body, $data['type']);

        return back()->with('success', $data['type'] === 'contact' ? 'Contact recorded (internal).' : 'Internal note added.');
    }

    public function requestInfo(Request $request, AutomationRequest $lead, AutomationLeadService $leads): RedirectResponse
    {
        $this->authorizeEdit($request, $lead);
        $message = SafeText::block($request->validate(['message' => ['required', 'string', 'max:3000']])['message'], 3000) ?? abort(422);
        abort_if(in_array($lead->status, ['converted', 'closed'], true), 422, 'This lead is closed.');
        $leads->requestInfo($lead, $request->user(), $message);

        return back()->with('success', 'Information request emailed to the customer (queued).');
    }

    public function quote(Request $request, AutomationRequest $lead, AutomationLeadService $leads): RedirectResponse
    {
        $this->authorizeEdit($request, $lead);
        $data = $request->validate(['amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'], 'summary' => ['required', 'string', 'max:5000']]);
        $amount = isset($data['amount']) && $data['amount'] !== '' ? (int) round(((float) $data['amount']) * 100) : null;

        return $this->attempt(fn () => $leads->prepareQuote($lead, $request->user(), $amount, SafeText::block($data['summary'], 5000) ?? ''),
            'Quotation saved. Use “Quotation sent” to share it with the customer.');
    }

    public function assign(Request $request, AutomationRequest $lead, AutomationLeadService $leads): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('leads.assign'), 403);
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'technical_owner_id' => ['nullable', 'integer', 'exists:users,id']]);

        return $this->attempt(fn () => $leads->assign($lead, $request->user(),
            isset($data['assigned_to']) ? User::find($data['assigned_to']) : null,
            isset($data['technical_owner_id']) ? User::find($data['technical_owner_id']) : null), 'Assignment saved.');
    }

    public function followUp(Request $request, AutomationRequest $lead, AutomationLeadService $leads): RedirectResponse
    {
        $this->authorizeEdit($request, $lead);
        $data = $request->validate(['owner_id' => ['nullable', 'integer', 'exists:users,id'], 'follow_up_at' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:1000']]);
        $owner = isset($data['owner_id']) ? User::find($data['owner_id']) : null;
        // Handing a follow-up to someone else is an assignment decision.
        abort_if($owner && $owner->id !== $request->user()->id && ! $request->user()->hasPermission('leads.assign') && $owner->id !== $lead->assigned_to, 403);

        return $this->attempt(fn () => $leads->followUp($lead, $request->user(), $owner,
            isset($data['follow_up_at']) ? Carbon::parse($data['follow_up_at']) : null, SafeText::line($data['note'] ?? null, 1000)), 'Follow-up saved.');
    }

    public function linkAccount(Request $request, AutomationRequest $lead, AutomationLeadService $leads): RedirectResponse
    {
        $this->authorizeEdit($request, $lead);

        return $this->attempt(fn () => $leads->linkAccount($lead, $request->user()), 'Lead linked to the customer account.');
    }

    public function convert(Request $request, AutomationRequest $lead, AutomationLeadService $leads, ProjectService $projects): RedirectResponse
    {
        $this->authorizeEdit($request, $lead);
        abort_unless($request->user()->hasPermission('projects.create'), 403);

        try {
            $project = $leads->convert($lead, $request->user(), $projects);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Converted to project '.$project->number.'. No order or invoice was created — raise billing separately.');
    }

    private function authorizeView(Request $request, AutomationRequest $lead): void
    {
        abort_unless($lead->isVisibleTo($request->user()), 403);
    }

    private function authorizeEdit(Request $request, AutomationRequest $lead): void
    {
        $this->authorizeView($request, $lead);
        abort_unless($request->user()->hasPermission('leads.edit'), 403);
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
