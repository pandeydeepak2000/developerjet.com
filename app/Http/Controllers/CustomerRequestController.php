<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ServiceRequest;
use App\Services\ProjectService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerRequestController extends Controller
{
    private function own(Request $request, ServiceRequest $sr): void
    {
        abort_unless($sr->user_id === $request->user()->id, 404);
    }

    public function index(Request $request): View
    {
        return view('dashboard.requests.index', ['requests' => ServiceRequest::where('user_id', $request->user()->id)->with('plan')->latest()->paginate(15)]);
    }

    public function show(Request $request, ServiceRequest $serviceRequest): View
    {
        $this->own($request, $serviceRequest);

        return view('dashboard.requests.show', ['sr' => $serviceRequest->load('plan', 'project')]);
    }

    /** Accepting records approval of a staff-set quote and opens a project. It does not charge anything. */
    public function accept(Request $request, ServiceRequest $serviceRequest, ProjectService $projects): RedirectResponse
    {
        $this->own($request, $serviceRequest);
        abort_unless($serviceRequest->status === 'quoted' && $serviceRequest->quote_minor !== null, 403, 'There is no quote to accept.');

        $projects->openFromRequest($serviceRequest);
        Audit::log('request.quote_accepted', $serviceRequest);

        return redirect()->route('dashboard.requests.show', $serviceRequest)->with('success', 'Quote accepted. A project has been opened; billing is arranged separately by our team.');
    }

    public function decline(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->own($request, $serviceRequest);
        abort_unless($serviceRequest->status === 'quoted', 403);

        $serviceRequest->update(['status' => 'declined', 'decided_at' => now()]);
        Audit::log('request.quote_declined', $serviceRequest);

        return back()->with('success', 'Quote declined.');
    }
}
