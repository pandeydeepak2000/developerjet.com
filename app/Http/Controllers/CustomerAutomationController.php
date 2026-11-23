<?php

namespace App\Http\Controllers;

use App\Models\AutomationRequest;
use App\Services\AutomationLeadService;
use App\Services\OrderException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A customer's own Business Automation requests. Only customer-visible history is shown; internal notes never are. */
class CustomerAutomationController extends Controller
{
    private function own(Request $request, AutomationRequest $lead): void
    {
        abort_unless($lead->user_id !== null && $lead->user_id === $request->user()->id, 404);
    }

    public function index(Request $request): View
    {
        return view('dashboard.automation.index', [
            'leads' => AutomationRequest::where('user_id', $request->user()->id)->latest('id')->paginate(15),
        ]);
    }

    public function show(Request $request, AutomationRequest $lead): View
    {
        $this->own($request, $lead);

        return view('dashboard.automation.show', ['lead' => $lead->load('customerEvents', 'project', 'plan')]);
    }

    public function decide(Request $request, AutomationRequest $lead, AutomationLeadService $leads): RedirectResponse
    {
        $this->own($request, $lead);
        $decision = $request->validate(['decision' => ['required', 'in:accept,decline']])['decision'];

        try {
            $leads->customerDecision($lead, $request->user(), $decision);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $decision === 'accept'
            ? 'Quotation accepted. Nothing has been charged — our team will contact you about next steps and send any invoice separately.'
            : 'Quotation declined. Thank you for letting us know.');
    }
}
