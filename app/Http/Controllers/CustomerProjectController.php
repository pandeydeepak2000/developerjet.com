<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\SeoReport;
use App\Models\SeoTask;
use App\Models\Ticket;
use App\Services\OrderException;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    private function own(Request $request, Project $project): void
    {
        abort_unless($project->user_id === $request->user()->id, 404);
    }

    public function index(Request $request): View
    {
        return view('dashboard.projects.index', ['projects' => Project::where('user_id', $request->user()->id)->latest()->paginate(15)]);
    }

    public function show(Request $request, Project $project): View
    {
        $this->own($request, $project);
        $project->load(['milestones' => fn ($q) => $q->orderBy('sort_order')->with('attachments'), 'messages.user', 'seoTasks', 'seoAudits']);

        return view('dashboard.projects.show', [
            'project' => $project,
            'progress' => $this->projects->progress($project),
            'invoices' => $project->order_id ? $request->user()->invoices()->where('order_id', $project->order_id)->get() : collect(),
            'tickets' => Ticket::where('user_id', $request->user()->id)->where('project_id', $project->id)->get(),
            'reports' => SeoReport::where('project_id', $project->id)->whereNotNull('published_at')->orderByDesc('period')->get(),
        ]);
    }

    public function milestone(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $this->own($request, $project);
        abort_unless($milestone->project_id === $project->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,revise'], 'feedback' => ['nullable', 'required_if:decision,revise', 'string', 'max:2000']]);

        try {
            $this->projects->decide($milestone, $request->user(), $data['decision'], $data['feedback'] ?? null);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['decision'] === 'approve' ? 'Milestone approved.' : 'Revision requested.');
    }

    public function message(Request $request, Project $project): RedirectResponse
    {
        $this->own($request, $project);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000'], 'type' => ['nullable', 'in:message,feedback']]);

        ProjectMessage::create(['project_id' => $project->id, 'user_id' => $request->user()->id, 'is_staff' => false, 'type' => $data['type'] ?? 'message', 'body' => $data['body']]);

        return back()->with('success', 'Message sent.');
    }

    /** SEO recommendations: customer approves or declines (client approval). */
    public function seoTask(Request $request, Project $project, SeoTask $task): RedirectResponse
    {
        $this->own($request, $project);
        abort_unless($task->project_id === $project->id && $task->kind === 'recommendation' && $task->status === 'pending', 403);
        $data = $request->validate(['decision' => ['required', 'in:approved,declined']]);
        $task->update(['status' => $data['decision']]);

        return back()->with('success', 'Recorded.');
    }

    public function attachment(Request $request, Project $project, Attachment $attachment): StreamedResponse
    {
        $this->own($request, $project);
        $milestoneIds = $project->milestones()->pluck('id');
        $allowed = ($attachment->attachable_type === (new Milestone)->getMorphClass() && $milestoneIds->contains($attachment->attachable_id))
            || ($attachment->attachable_type === (new Project)->getMorphClass() && $attachment->attachable_id === $project->id);
        abort_unless($allowed, 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function report(Request $request, Project $project, SeoReport $report): StreamedResponse
    {
        $this->own($request, $project);
        abort_unless($report->project_id === $project->id && $report->published_at && $report->file_path, 404);

        return Storage::disk('local')->download($report->file_path, "seo-report-{$report->period}.pdf");
    }
}
