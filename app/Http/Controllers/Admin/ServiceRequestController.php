<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceRequestController extends Controller
{
    public function show(Request $request, ServiceRequest $serviceRequest): View
    {
        abort_unless($request->user()->hasPermission('projects.view'), 403);

        return view('admin.requests.show', ['sr' => $serviceRequest->load('user', 'plan', 'attachments', 'project')]);
    }

    /** Staff sets the quote. It is only a proposal: nothing is charged until the customer accepts and pays a separate invoice. */
    public function quote(Request $request, ServiceRequest $serviceRequest, Notifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('projects.edit'), 403);
        $data = $request->validate(['quote' => ['required', 'numeric', 'min:0', 'max:100000000'], 'quote_note' => ['nullable', 'string', 'max:3000']]);

        abort_unless(in_array($serviceRequest->status, ['submitted', 'quoted'], true), 403, 'This request is no longer open for quoting.');
        // Access requests (AI All-Access, coming soon) record interest only; quoting would open a billable project.
        abort_if(config('service_forms.'.$serviceRequest->kind.'.quotable') === false, 403, 'This type of request cannot be quoted.');

        $serviceRequest->update(['quote_minor' => (int) round($data['quote'] * 100), 'quote_note' => $data['quote_note'] ?? null, 'status' => 'quoted', 'quoted_at' => now()]);
        Audit::log('request.quoted', $serviceRequest, ['quote_minor' => $serviceRequest->quote_minor]);
        $notifier->customer('quote_ready', "request:{$serviceRequest->id}:{$serviceRequest->quoted_at->timestamp}", $serviceRequest->user, 'quote_ready',
            ['project' => $serviceRequest->number, 'url' => route('dashboard.requests.show', $serviceRequest)]);

        return back()->with('success', 'Quote saved and the customer has been notified.');
    }

    public function attachment(Request $request, ServiceRequest $serviceRequest, int $attachment): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('projects.view'), 403);
        $file = $serviceRequest->attachments()->findOrFail($attachment);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
