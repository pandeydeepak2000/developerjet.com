<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\AttachmentStore;
use App\Services\ProjectService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    private function can(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission('projects.'.$action), 403, 'You do not have permission to do that.');
    }

    /** Developers/SEO staff without projects.assign only see projects assigned to them. */
    private function visible(Request $request)
    {
        $q = Project::query();

        return $request->user()->hasPermission('projects.assign') || $request->user()->isSuper() ? $q : $q->where('assigned_to', $request->user()->id);
    }

    private function find(Request $request, Project $project): Project
    {
        abort_unless($this->visible($request)->whereKey($project->id)->exists(), 403);

        return $project;
    }

    public function index(Request $request): View
    {
        $this->can($request, 'view');
        $query = $this->visible($request)->with('user', 'assignee')->latest('id');

        if ($s = $request->query('status')) {
            $query->where('status', $s);
        }
        if ($k = $request->query('kind')) {
            $query->where('kind', $k);
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('number', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('title', 'like', '%'.addcslashes($term, '%_\\').'%'));
        }

        return view('admin.projects.index', ['projects' => $query->paginate(15)->withQueryString()]);
    }

    public function show(Request $request, Project $project): View
    {
        $this->can($request, 'view');
        $this->find($request, $project)->load('user', 'assignee', 'milestones.attachments', 'tasks.assignee', 'messages.user', 'serviceRequest', 'attachments');

        return view('admin.projects.show', ['project' => $project, 'staff' => User::where('role', 'staff')->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'progress' => $this->projects->progress($project)]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->can($request, 'edit');
        $this->find($request, $project);
        $data = $request->validate(['status' => ['required', 'in:planning,active,on_hold,review,completed,cancelled'], 'assigned_to' => ['nullable', 'exists:users,id'], 'summary' => ['nullable', 'string', 'max:3000'], 'due_date' => ['nullable', 'date']]);

        if (array_key_exists('assigned_to', $data) && (int) $data['assigned_to'] !== (int) $project->assigned_to) {
            $this->can($request, 'assign');
            $assignee = $data['assigned_to'] ? User::where('role', 'staff')->where('is_active', true)->findOrFail($data['assigned_to']) : null;
            $project->assigned_to = $assignee?->id;
            $this->projects->history($project, $assignee ? "Assigned to {$assignee->name}." : 'Unassigned.', $request->user());
        }

        if ($data['status'] !== $project->status) {
            $this->projects->history($project, 'Status changed to '.str_replace('_', ' ', $data['status']).'.', $request->user());
        }

        $project->forceFill(['status' => $data['status'], 'summary' => $data['summary'] ?? $project->summary, 'due_date' => $data['due_date'] ?? $project->due_date])->save();
        Audit::log('project.updated', $project);

        return back()->with('success', 'Project updated.');
    }

    public function milestoneStore(Request $request, Project $project): RedirectResponse
    {
        $this->can($request, 'edit');
        $this->find($request, $project);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'], 'due_date' => ['nullable', 'date']]);

        $project->milestones()->create($data + ['sort_order' => $project->milestones()->count() + 1]);
        $this->projects->history($project, "Milestone added: {$data['title']}.", $request->user());

        return back()->with('success', 'Milestone added.');
    }

    public function milestoneUpdate(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $this->can($request, 'edit');
        $this->find($request, $project);
        abort_unless($milestone->project_id === $project->id, 404);
        $data = $request->validate(['action' => ['required', 'in:start,request_approval'], 'files' => ['nullable', 'array', 'max:5'], 'files.*' => AttachmentStore::rules()]);

        if ($data['action'] === 'start' && $milestone->status === 'pending') {
            $milestone->update(['status' => 'in_progress']);
        }

        if ($data['action'] === 'request_approval') {
            $this->can($request, 'approve'); // requesting client approval is an approval-class action
            AttachmentStore::store($milestone, $request->file('files', []), $request->user());
            $milestone->update(['status' => 'awaiting_approval', 'approval_requested_at' => now()]);
            $this->projects->history($project, "Approval requested for milestone “{$milestone->title}”.", $request->user());
        }

        return back()->with('success', 'Milestone updated.');
    }

    public function taskStore(Request $request, Project $project): RedirectResponse
    {
        $this->can($request, 'edit');
        $this->find($request, $project);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'assigned_to' => ['nullable', 'exists:users,id']]);
        $project->tasks()->create($data);

        return back()->with('success', 'Task added.');
    }

    public function taskUpdate(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->can($request, 'edit');
        $this->find($request, $project);
        abort_unless($task->project_id === $project->id, 404);
        $task->update($request->validate(['status' => ['required', 'in:todo,doing,done']]));

        return back();
    }

    public function message(Request $request, Project $project): RedirectResponse
    {
        $this->can($request, 'edit');
        $this->find($request, $project);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        ProjectMessage::create(['project_id' => $project->id, 'user_id' => $request->user()->id, 'is_staff' => true, 'type' => 'message', 'body' => $data['body']]);

        return back()->with('success', 'Message sent to customer.');
    }

    public function attachment(Request $request, Project $project, Attachment $attachment): StreamedResponse
    {
        $this->can($request, 'view');
        $this->find($request, $project);
        abort_unless(in_array($attachment->attachable_type, [(new Milestone)->getMorphClass(), (new Project)->getMorphClass()], true), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
