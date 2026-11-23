<?php

namespace App\Services;

use App\Jobs\SendLeadEmail;
use App\Models\AutomationRequest;
use App\Models\AutomationRequestEvent;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\LeadStatus;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Setting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Business Automation leads: submission, notifications and the sales quotation lifecycle.
 *
 * Guarantees: one record per rendered form (submission token) and per repeated identical request within 24 h
 * (fingerprint); notifications are deduplicated by NotificationLog keys and queued with retries; internal notes are
 * never sent to customers; no step creates an order, invoice or payment — conversion opens a project only after
 * the customer has accepted, and any billing is raised separately by staff.
 */
class AutomationLeadService
{
    public const DUPLICATE_WINDOW_HOURS = 24;

    public function __construct(private Notifier $notifier) {}

    /**
     * @param  array<string, mixed>  $data  validated + sanitised fields
     * @return array{0: AutomationRequest, 1: bool} [lead, true when an existing lead was returned]
     */
    public function submit(array $data, ?User $user, string $token, ?string $ip): array
    {
        if ($existing = AutomationRequest::where('submission_token', $token)->first()) {
            return [$existing, true];
        }

        $fingerprint = self::fingerprint($data['email'], $data['requirements']);
        $repeat = AutomationRequest::where('fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subHours(self::DUPLICATE_WINDOW_HOURS))->latest('id')->first();
        if ($repeat) {
            return [$repeat, true];
        }

        try {
            $lead = DB::transaction(function () use ($data, $user, $token, $ip, $fingerprint) {
                $lead = AutomationRequest::create($data + [
                    'reference' => $this->newReference(),
                    'user_id' => $user?->id,
                    'status' => 'new',
                    'submission_token' => $token,
                    'fingerprint' => $fingerprint,
                    'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
                ]);
                $this->event($lead, null, 'submitted', 'customer', 'Request received.', null, 'new');

                return $lead;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent double-submit of the same form won the race: return that record.
            return [AutomationRequest::where('submission_token', $token)->firstOrFail(), true];
        }

        Audit::log('lead.submitted', $lead, ['reference' => $lead->reference, 'guest' => $user === null], $user?->id);
        $this->notifyNew($lead);

        return [$lead, false];
    }

    public static function fingerprint(string $email, string $requirements): string
    {
        $normal = mb_strtolower(preg_replace('/\s+/u', ' ', trim($requirements)) ?? '');

        return hash('sha256', mb_strtolower(trim($email)).'|'.$normal);
    }

    /** Customer acknowledgement, sales mailbox and Telegram — each queued once per lead. */
    public function notifyNew(AutomationRequest $lead): void
    {
        $url = route('admin.leads.show', $lead);
        $summary = $lead->summary(400);

        if (Setting::bool('automation.customer_ack', true)) {
            $this->queueMail('automation_ack', 'ack:'.$lead->reference, $lead->email, 'automation_ack', [
                'name' => $lead->name, 'reference' => $lead->reference, 'business_type' => $lead->business_type,
                'summary' => $summary, 'email' => $lead->email, 'phone' => $lead->phoneDisplay(),
                'url' => $lead->user_id ? route('dashboard.automation.show', $lead) : route('business-automation'),
                'support' => route('contact'),
            ]);
        }

        $salesEmail = Setting::get('sales.notify_email');
        $vars = [
            'name' => 'Sales team', 'reference' => $lead->reference, 'customer' => $lead->name, 'email' => $lead->email,
            'phone' => $lead->phoneDisplay(), 'business_type' => $lead->business_type, 'company' => $lead->company ?: '—',
            'summary' => $summary, 'url' => $url,
        ];
        if ($salesEmail) {
            $this->queueMail('automation_staff', 'staff:'.$lead->reference, $salesEmail, 'staff_automation_new', $vars);
        } else {
            $this->skip('automation_staff', 'mail:automation_staff:staff:'.$lead->reference, 'mail', 'Sales notification email is not configured (Admin → Settings → Sales).');
        }

        // Telegram: contact details only — never requirement text that could contain secrets beyond the redacted summary.
        $this->notifier->admin('automation_request', $lead->reference, implode("\n", [
            'New Business Automation Lead',
            'Reference: '.$lead->reference,
            'Customer: '.$lead->name,
            'Phone: '.$lead->phoneDisplay(),
            'Email: '.$lead->email,
            'Business type: '.$lead->business_type,
            'Summary: '.$lead->summary(200),
            'Open: '.$url,
        ]));
    }

    // ------------------------------------------------------------------------------------------- sales workflow

    public function transition(AutomationRequest $lead, string $to, User $staff, ?string $note = null): void
    {
        $from = $lead->status;
        if (! LeadStatus::canMove($from, $to)) {
            throw new OrderException('A lead cannot move from “'.LeadStatus::label($from).'” to “'.LeadStatus::label($to).'”.');
        }
        if (in_array($to, ['quotation_sent', 'quotation_prepared'], true) && $lead->quote_summary === null) {
            throw new OrderException('Prepare the quotation (scope summary) before moving to “'.LeadStatus::label($to).'”.');
        }
        if ($to === 'assigned' && ! $lead->assigned_to) {
            throw new OrderException('Assign a sales owner first.');
        }

        $changes = ['status' => $to];
        if ($to === 'quotation_sent') {
            $changes['quote_sent_at'] = now();
        }
        if (in_array($to, ['accepted', 'declined'], true)) {
            $changes += ['decided_at' => now(), 'decision_source' => 'recorded_by_staff'];
        }
        $lead->update($changes);

        $this->event($lead, $staff, 'status', 'internal', $note, $from, $to);
        Audit::log('lead.status_changed', $lead, ['from' => $from, 'to' => $to], $staff->id);
        $this->customerMilestone($lead, $to);
    }

    public function note(AutomationRequest $lead, User $staff, string $body, string $type = 'note'): void
    {
        $this->event($lead, $staff, $type, 'internal', $body);
        Audit::log('lead.'.$type, $lead, [], $staff->id);
    }

    /** Ask the customer for more information: a customer-visible message plus an email. */
    public function requestInfo(AutomationRequest $lead, User $staff, string $message): void
    {
        $from = $lead->status;
        if ($from !== 'requirements_discussion' && LeadStatus::canMove($from, 'requirements_discussion')) {
            $lead->update(['status' => 'requirements_discussion']);
        }
        $this->event($lead, $staff, 'info_request', 'customer', $message, $from, $lead->status);
        Audit::log('lead.info_requested', $lead, [], $staff->id);
        $this->queueMail('automation_update', 'info:'.$lead->reference.':'.Str::random(8), $lead->email, 'automation_info_request', [
            'name' => $lead->name, 'reference' => $lead->reference, 'message' => $message, 'support' => route('contact'),
            'url' => $lead->user_id ? route('dashboard.automation.show', $lead) : route('contact'),
        ]);
    }

    /** Prepare (or revise) the quotation. Amount is optional: a scope-only quotation can be discussed first. */
    public function prepareQuote(AutomationRequest $lead, User $staff, ?int $amountMinor, string $summary): void
    {
        if (! in_array($lead->status, ['assigned', 'requirements_discussion', 'quotation_prepared', 'quotation_sent', 'reviewing'], true)) {
            throw new OrderException('A quotation can be prepared only while the lead is open and before a decision.');
        }
        $from = $lead->status;
        $lead->update(['quote_minor' => $amountMinor, 'quote_summary' => $summary, 'quoted_at' => now(), 'status' => 'quotation_prepared']);
        $this->event($lead, $staff, 'quote', 'internal', 'Quotation prepared'.($amountMinor !== null ? ': '.Money::inr($amountMinor) : ' (no amount yet)').'.', $from, 'quotation_prepared');
        Audit::log('lead.quote_prepared', $lead, ['quote_minor' => $amountMinor], $staff->id);
    }

    public function assign(AutomationRequest $lead, User $staff, ?User $sales, ?User $technical): void
    {
        foreach ([$sales, $technical] as $candidate) {
            if ($candidate && ! self::assignable($candidate)) {
                throw new OrderException('That staff member cannot be assigned to leads.');
            }
        }
        $lead->update(['assigned_to' => $sales?->id, 'technical_owner_id' => $technical?->id]);
        $text = 'Sales owner: '.($sales?->name ?? 'none').'; technical owner: '.($technical?->name ?? 'none').'.';
        $this->event($lead, $staff, 'assignment', 'internal', $text);
        Audit::log('lead.assigned', $lead, ['sales' => $sales?->id, 'technical' => $technical?->id], $staff->id);

        if ($sales && in_array($lead->status, ['new', 'reviewing'], true)) {
            $this->transition($lead->refresh(), 'assigned', $staff);
        }
    }

    public function followUp(AutomationRequest $lead, User $staff, ?User $owner, ?\DateTimeInterface $at, ?string $note): void
    {
        if ($owner && ! self::assignable($owner)) {
            throw new OrderException('That staff member cannot own follow-ups.');
        }
        $lead->update(['follow_up_owner_id' => $owner?->id, 'follow_up_at' => $at]);
        $this->event($lead, $staff, 'follow_up', 'internal', trim('Follow-up: '.($owner?->name ?? 'no owner').($at ? ' on '.$at->format('d M Y H:i') : '').'. '.($note ?? '')));
        Audit::log('lead.follow_up', $lead, ['owner' => $owner?->id, 'at' => $at?->format(DATE_ATOM)], $staff->id);
    }

    /** Customer decision from their dashboard (only on a sent quotation). Nothing is charged. */
    public function customerDecision(AutomationRequest $lead, User $customer, string $decision): void
    {
        DB::transaction(function () use ($lead, $customer, $decision) {
            $locked = AutomationRequest::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($locked->user_id !== $customer->id || $locked->status !== 'quotation_sent') {
                throw new OrderException('There is no quotation awaiting your decision.');
            }
            $to = $decision === 'accept' ? 'accepted' : 'declined';
            $locked->update(['status' => $to, 'decided_at' => now(), 'decision_source' => 'customer_dashboard']);
            $this->event($locked, $customer, 'decision', 'customer', $to === 'accepted' ? 'You accepted the quotation.' : 'You declined the quotation.', 'quotation_sent', $to);
            Audit::log('lead.customer_'.$to, $locked, [], $customer->id);
        });
        $lead->refresh();
        $this->notifier->admin('automation_request', $lead->reference.':'.$lead->status, 'Business Automation lead '.$lead->reference.': customer '.$lead->status.' the quotation. '.route('admin.leads.show', $lead));
    }

    /** A verified customer account with exactly the lead's email (guest leads only). */
    public static function matchingAccount(AutomationRequest $lead): ?User
    {
        if ($lead->user_id) {
            return null;
        }

        return User::where('email', mb_strtolower($lead->email))->where('role', User::ROLE_CUSTOMER)->whereNotNull('email_verified_at')->first();
    }

    public function linkAccount(AutomationRequest $lead, User $staff): User
    {
        $account = self::matchingAccount($lead) ?? throw new OrderException('No verified customer account uses this email address.');
        $lead->update(['user_id' => $account->id]);
        $this->event($lead, $staff, 'note', 'internal', 'Linked to the verified customer account with the same email address.');
        Audit::log('lead.account_linked', $lead, ['user_id' => $account->id], $staff->id);

        return $account;
    }

    /** Accepted lead → existing digital-services project workflow (service request + project). Idempotent. */
    public function convert(AutomationRequest $lead, User $staff, ProjectService $projects): Project
    {
        $project = DB::transaction(function () use ($lead, $staff, $projects) {
            $locked = AutomationRequest::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($locked->project_id && $locked->project) {
                return $locked->project;
            }
            if ($locked->status !== 'accepted') {
                throw new OrderException('Only a lead whose quotation the customer accepted can be converted.');
            }
            if (! $locked->user_id) {
                throw new OrderException('Link the lead to a verified customer account first (the customer must register with the same email).');
            }

            $sr = ServiceRequest::create([
                'number' => 'TMP-'.bin2hex(random_bytes(5)), 'user_id' => $locked->user_id, 'plan_id' => $locked->plan_id, 'kind' => 'automation',
                'requirements' => ['business_type' => $locked->business_type, 'summary' => $locked->requirements, 'lead_reference' => $locked->reference],
                'status' => 'quoted', 'quote_minor' => $locked->quote_minor, 'quote_note' => $locked->quote_summary,
                'quoted_at' => $locked->quoted_at ?? now(),
            ]);
            $sr->update(['number' => 'QR'.str_pad((string) $sr->id, 5, '0', STR_PAD_LEFT)]);
            $project = $projects->openFromRequest($sr);
            $project->update(['title' => 'Business Automation — '.($locked->company ?: $locked->name), 'summary' => $locked->quote_summary, 'assigned_to' => $locked->technical_owner_id ?? $locked->assigned_to]);

            $locked->update(['status' => 'converted', 'service_request_id' => $sr->id, 'project_id' => $project->id]);
            $this->event($locked, $staff, 'conversion', 'customer', 'Your request is now project '.$project->number.'.', 'accepted', 'converted');
            Audit::log('lead.converted', $locked, ['project' => $project->number], $staff->id);

            return $project;
        });

        $lead->refresh();
        $this->customerMilestone($lead, 'converted');

        return $project;
    }

    public static function assignable(User $user): bool
    {
        return $user->isStaff() && ($user->is_active ?? true) && $user->hasPermission('leads.view');
    }

    // ------------------------------------------------------------------------------------------------- helpers

    private function customerMilestone(AutomationRequest $lead, string $status): void
    {
        if (! in_array($status, LeadStatus::CUSTOMER_MILESTONES, true)) {
            return;
        }
        $message = LeadStatus::customerText($status);
        if ($status === 'quotation_sent') {
            $message .= ' '.($lead->quote_minor !== null ? 'Quoted amount: '.Money::inr($lead->quote_minor).'. ' : '')
                .'Scope: '.Str::limit((string) $lead->quote_summary, 600)
                .' Nothing is charged until you accept and pay a separate invoice.';
        }
        if ($status !== 'converted') { // conversion already wrote its own customer-visible entry
            $this->event($lead, null, 'status', 'customer', $message, null, $status);
        }
        $this->queueMail('automation_update', $status.':'.$lead->reference.':'.now()->timestamp, $lead->email, 'automation_update', [
            'name' => $lead->name, 'reference' => $lead->reference, 'status' => LeadStatus::label($status), 'message' => $message,
            'url' => $lead->user_id ? route('dashboard.automation.show', $lead) : route('contact'), 'support' => route('contact'),
        ]);
    }

    private function queueMail(string $event, string $key, string $to, string $template, array $vars): void
    {
        $dedupe = "mail:{$event}:{$key}";
        try {
            $log = NotificationLog::create(['dedupe_key' => Str::limit($dedupe, 160, ''), 'event' => $event, 'channel' => 'mail', 'status' => 'pending']);
        } catch (UniqueConstraintViolationException) {
            return; // already queued for this occurrence
        }
        // Stored encrypted so a failed email can be retried from Admin → Notifications.
        $log->payload = json_encode(['type' => 'lead_email', 'to' => $to, 'template' => $template, 'vars' => $vars], JSON_UNESCAPED_UNICODE);
        $log->save();

        SendLeadEmail::dispatch($log->id, $to, $template, $vars);
    }

    private function skip(string $event, string $dedupe, string $channel, string $reason): void
    {
        try {
            NotificationLog::create(['dedupe_key' => $dedupe, 'event' => $event, 'channel' => $channel, 'status' => 'skipped', 'error' => $reason]);
        } catch (UniqueConstraintViolationException) {
        }
    }

    private function event(AutomationRequest $lead, ?User $actor, string $type, string $visibility, ?string $body, ?string $from = null, ?string $to = null): void
    {
        AutomationRequestEvent::create([
            'automation_request_id' => $lead->id, 'user_id' => $actor?->id, 'type' => $type, 'visibility' => $visibility,
            'from_status' => $from, 'to_status' => $to, 'body' => $body,
        ]);
    }

    private function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = 'BA-'.now()->format('ymd').'-'.$suffix;
        } while (AutomationRequest::where('reference', $reference)->exists());

        return $reference;
    }

    /** E.164 from the two phone fields (already validated by ValidPhone). */
    public static function phone(string $country, string $national): ?string
    {
        return PhoneNumber::normalise($country, $national);
    }
}
