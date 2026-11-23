<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(private Notifier $notifier) {}

    /** Idempotent: one project per accepted request. */
    public function openFromRequest(ServiceRequest $sr): Project
    {
        return DB::transaction(function () use ($sr) {
            $locked = ServiceRequest::whereKey($sr->id)->lockForUpdate()->firstOrFail();

            if ($existing = Project::where('service_request_id', $locked->id)->first()) {
                return $existing;
            }

            $project = Project::create([
                'number' => 'TMP-'.bin2hex(random_bytes(5)), 'user_id' => $locked->user_id, 'service_request_id' => $locked->id,
                'kind' => $locked->kind, 'title' => ($locked->plan?->name ?? ucfirst($locked->kind)).' project', 'status' => 'planning',
                'due_date' => $locked->requirements['deadline'] ?? null,
            ]);
            $project->update(['number' => 'PRJ'.str_pad((string) $project->id, 5, '0', STR_PAD_LEFT)]);
            $locked->update(['status' => 'accepted', 'decided_at' => now()]);
            $this->history($project, 'Project opened from accepted quote '.$locked->number.'.');

            return $project;
        });
    }

    public function history(Project $project, string $text, ?User $actor = null): void
    {
        ProjectMessage::create(['project_id' => $project->id, 'user_id' => $actor?->id, 'is_staff' => (bool) $actor?->isStaff(), 'type' => 'history', 'body' => $text]);
    }

    public function progress(Project $project): int
    {
        $total = $project->milestones()->count();

        return $total === 0 ? 0 : (int) round($project->milestones()->where('status', 'approved')->count() / $total * 100);
    }

    /** Customer decision on a milestone that is awaiting approval. */
    public function decide(Milestone $milestone, User $customer, string $decision, ?string $feedback): Milestone
    {
        return DB::transaction(function () use ($milestone, $customer, $decision, $feedback) {
            $locked = Milestone::whereKey($milestone->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'awaiting_approval') {
                throw new OrderException('This milestone is not awaiting your approval.');
            }

            $status = $decision === 'approve' ? 'approved' : 'revision_requested';
            $locked->update(['status' => $status, 'decided_at' => now(), 'decided_by' => $customer->id, 'client_feedback' => $feedback]);
            ProjectMessage::create(['project_id' => $locked->project_id, 'user_id' => $customer->id, 'is_staff' => false, 'type' => $decision === 'approve' ? 'history' : 'revision',
                'body' => ($decision === 'approve' ? 'Approved' : 'Requested revision on')." milestone “{$locked->title}”.".($feedback ? " Feedback: {$feedback}" : '')]);

            return $locked;
        });
    }
}
