<?php

namespace App\Services;

use App\Mail\TemplatedMail;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\Audit;
use App\Support\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class TicketService
{
    public const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'zip'];

    public const MAX_KB = 5120;

    public const MAX_ESCALATION = 3;

    public function __construct(private Notifier $notifier) {}

    public function open(User $user, Department $department, string $subject, string $body, string $priority, ?int $serviceId, array $files = []): Ticket
    {
        $ticket = DB::transaction(function () use ($user, $department, $subject, $body, $priority, $serviceId, $files) {
            [$firstDue, $resolveDue] = $this->slaTargets($department, $priority, now());
            $ticket = Ticket::create([
                'number' => 'TMP-'.Str::random(10), 'user_id' => $user->id, 'department_id' => $department->id,
                'subject' => $subject, 'priority' => $priority, 'service_id' => $serviceId,
                'last_activity_at' => now(), 'first_response_due_at' => $firstDue, 'resolution_due_at' => $resolveDue,
            ]);
            $ticket->update(['number' => 'TKT'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)]);
            $reply = $ticket->replies()->create(['user_id' => $user->id, 'is_staff' => false, 'body' => $body]);
            $this->attach($reply, $files, $user);
            $this->event($ticket, $user, 'opened', ['priority' => $priority, 'department' => $department->name]);

            return $ticket;
        });

        Audit::log('ticket.opened', $ticket, [], $user->id);
        $this->notifier->admin('ticket_opened', "ticket:{$ticket->id}", "Ticket {$ticket->number} opened ({$department->name}, {$priority}): {$subject}");
        $this->mailTeam($department, 'staff_ticket_new', $ticket);

        return $ticket;
    }

    public function reply(Ticket $ticket, User $author, string $body, bool $isStaff, bool $private = false, array $files = []): TicketReply
    {
        $private = $isStaff && $private; // customers can never create private notes

        $reply = DB::transaction(function () use ($ticket, $author, $body, $isStaff, $private, $files) {
            $reply = $ticket->replies()->create(['user_id' => $author->id, 'is_staff' => $isStaff, 'is_private' => $private, 'body' => $body]);
            $this->attach($reply, $files, $author);

            if (! $private) {
                $updates = ['status' => $isStaff ? 'answered' : 'customer_reply', 'last_activity_at' => now(), 'closed_at' => null];
                if ($isStaff && $ticket->first_response_at === null) {
                    $updates['first_response_at'] = now();
                    $this->event($ticket, $author, 'first_response');
                }
                $ticket->update($updates);
            }
            $this->event($ticket, $author, $private ? 'note' : ($isStaff ? 'reply' : 'customer_reply'));

            return $reply;
        });

        if ($isStaff && ! $private) {
            $this->notifier->customer('ticket_reply', "reply:{$reply->id}", $ticket->user, 'ticket_reply',
                ['ticket' => $ticket->number, 'url' => route('dashboard.tickets.show', $ticket)]);
        }

        return $reply;
    }

    /** Only active staff who may see the ticket's department can be assigned (no assigning work into a blind spot). */
    public function assign(Ticket $ticket, ?User $assignee, User $actor): void
    {
        if ($assignee && ! self::staffCanAccess($assignee, $ticket)) {
            throw new \InvalidArgumentException($assignee->name.' cannot see tickets in this department.');
        }

        $ticket->update(['assigned_to' => $assignee?->id]);
        $this->event($ticket, $actor, 'assigned', ['to' => $assignee?->id, 'to_name' => $assignee?->name]);
        Audit::log('ticket.assigned', $ticket, ['to' => $assignee?->id], $actor->id);

        if ($assignee) {
            $this->notifier->admin('ticket_assigned', "ticket:{$ticket->id}:to:{$assignee->id}", "Ticket {$ticket->number} assigned to {$assignee->name}.");
            if ($assignee->id !== $actor->id && Setting::bool('support.email_assignee', true)) {
                $this->notifier->customer('staff_ticket_assigned', "ticket:{$ticket->id}:assigned:{$assignee->id}:".now()->timestamp, $assignee, 'staff_ticket_assigned',
                    ['ticket' => $ticket->number, 'url' => route('admin.tickets.show', $ticket)]);
            }
        }
    }

    public function changeStatus(Ticket $ticket, string $status, User $actor): void
    {
        $from = $ticket->status;
        if ($from === $status) {
            return;
        }
        $ticket->update(['status' => $status, 'last_activity_at' => now(), 'closed_at' => $status === 'closed' ? now() : null]);
        $this->event($ticket, $actor, 'status', ['from' => $from, 'to' => $status]);
    }

    public function changePriority(Ticket $ticket, string $priority, User $actor): void
    {
        $from = $ticket->priority;
        if ($from === $priority) {
            return;
        }
        // Targets are recalculated from the original open time so a priority change cannot "reset the clock".
        [$firstDue, $resolveDue] = $this->slaTargets($ticket->department, $priority, $ticket->created_at);
        $ticket->update(['priority' => $priority, 'first_response_due_at' => $firstDue, 'resolution_due_at' => $resolveDue]);
        $this->event($ticket, $actor, 'priority', ['from' => $from, 'to' => $priority]);
    }

    /** Move to another department. An assignee who cannot see the new department is unassigned. */
    public function transfer(Ticket $ticket, Department $to, User $actor): void
    {
        $from = $ticket->department;
        if ($from->id === $to->id) {
            return;
        }
        DB::transaction(function () use ($ticket, $to, $from, $actor) {
            $ticket->update(['department_id' => $to->id]);
            $ticket->setRelation('department', $to);
            $this->event($ticket, $actor, 'department', ['from' => $from->name, 'to' => $to->name]);
            if ($ticket->assignee && ! self::staffCanAccess($ticket->assignee, $ticket)) {
                $ticket->update(['assigned_to' => null]);
                $this->event($ticket, $actor, 'assigned', ['to' => null]);
            }
        });
        $this->mailTeam($to, 'staff_ticket_new', $ticket);
    }

    /** Escalation: raises the level (max 3), optionally bumps priority / moves department, alerts staff. */
    public function escalate(Ticket $ticket, ?User $actor, string $reason, ?string $priority = null, ?Department $to = null): void
    {
        $level = min(self::MAX_ESCALATION, $ticket->escalation_level + 1);
        $ticket->update(['escalation_level' => $level, 'escalated_at' => now(), 'last_activity_at' => now()]);
        $this->event($ticket, $actor, 'escalated', ['level' => $level, 'reason' => Str::limit($reason, 200)]);
        Audit::log('ticket.escalated', $ticket, ['level' => $level], $actor?->id);

        if ($priority && $actor) {
            $this->changePriority($ticket, $priority, $actor);
        }
        if ($to && $actor) {
            $this->transfer($ticket, $to, $actor);
        }

        $this->notifier->admin('ticket_escalated', "ticket:{$ticket->id}:esc:{$level}", "Ticket {$ticket->number} escalated to level {$level}: ".Str::limit($reason, 120));
        if ($ticket->assignee) {
            $this->notifier->customer('staff_ticket_escalated', "ticket:{$ticket->id}:esc:{$level}:{$ticket->assigned_to}", $ticket->assignee, 'staff_ticket_escalated',
                ['ticket' => $ticket->number, 'url' => route('admin.tickets.show', $ticket)]);
        }
    }

    /** Marks missed SLA targets once per ticket (idempotent) and escalates when the setting is on. */
    public function slaCheck(?Carbon $now = null): int
    {
        $now ??= now();
        $count = 0;

        Ticket::with('department', 'assignee')->where('status', '!=', 'closed')->whereNull('sla_breached_at')
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('first_response_at')->whereNotNull('first_response_due_at')->where('first_response_due_at', '<', $now))
                ->orWhere(fn ($q) => $q->whereNotNull('resolution_due_at')->where('resolution_due_at', '<', $now)))
            ->orderBy('id')->chunkById(100, function ($tickets) use ($now, &$count) {
                foreach ($tickets as $ticket) {
                    $target = $ticket->first_response_at === null && $ticket->first_response_due_at?->lt($now) ? 'first response' : 'resolution';
                    $claimed = Ticket::whereKey($ticket->id)->whereNull('sla_breached_at')->update(['sla_breached_at' => $now]);
                    if (! $claimed) {
                        continue;
                    }
                    $count++;
                    $this->event($ticket, null, 'sla_breached', ['target' => $target]);
                    $this->notifier->admin('ticket_sla_breached', "ticket:{$ticket->id}:sla", "SLA {$target} target missed on ticket {$ticket->number} ({$ticket->priority}).");
                    if (Setting::bool('support.auto_escalate_on_breach')) {
                        $this->escalate($ticket->fresh(['assignee', 'department']), null, 'SLA '.$target.' target missed');
                    }
                }
            });

        return $count;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    public function slaTargets(Department $department, string $priority, $from): array
    {
        $first = $department->sla_first_response_hours ?: Setting::get("support.sla.{$priority}.first_response_hours");
        $resolve = $department->sla_resolution_hours ?: Setting::get("support.sla.{$priority}.resolution_hours");
        $from = Carbon::parse($from);

        return [
            $first ? $from->copy()->addHours((int) $first) : null,
            $resolve ? $from->copy()->addHours((int) $resolve) : null,
        ];
    }

    private function event(Ticket $ticket, ?User $user, string $type, array $meta = []): void
    {
        TicketEvent::create(['ticket_id' => $ticket->id, 'user_id' => $user?->id, 'type' => $type, 'meta' => $meta ?: null, 'created_at' => now()]);
    }

    /** Optional department inbox alert (staff address configured by an admin). Failures are logged, never thrown. */
    private function mailTeam(Department $department, string $template, Ticket $ticket): void
    {
        if (! $department->alert_email) {
            return;
        }
        try {
            Mail::to($department->alert_email)->queue(new TemplatedMail($template, [
                'name' => $department->name, 'ticket' => $ticket->number, 'service' => $department->name, 'url' => route('admin.tickets.show', $ticket),
            ]));
        } catch (Throwable $e) {
            Log::warning('Department alert email failed.', ['department' => $department->id, 'error' => $e::class]);
        }
    }

    /** @param  array<int, UploadedFile>  $files */
    private function attach($model, array $files, User $user): void
    {
        foreach ($files as $file) {
            // Random storage name on the private disk: the client filename is never used as a path.
            $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
            $path = $file->storeAs('attachments/'.now()->format('Y/m'), Str::random(40).'.'.$ext, 'local');

            $model->attachments()->create([
                'user_id' => $user->id, 'original_name' => Str::limit(basename($file->getClientOriginalName()), 120, ''),
                'path' => $path, 'mime' => $file->getMimeType() ?? 'application/octet-stream', 'size' => $file->getSize(),
            ]);
        }
    }

    public static function fileRules(): array
    {
        return ['nullable', 'array', 'max:3'];
    }

    public static function fileItemRules(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::ALLOWED_MIMES)];
    }

    /** Staff may see a ticket if they hold tickets.view and belong to its department (super staff: all). */
    public static function staffCanAccess(User $user, Ticket $ticket): bool
    {
        if (! $user->isStaff() || ! $user->hasPermission('tickets.view')) {
            return false;
        }

        return $user->isSuper() || $user->departments()->whereKey($ticket->department_id)->exists();
    }
}
