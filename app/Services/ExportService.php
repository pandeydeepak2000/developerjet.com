<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\AutomationRequest;
use App\Models\Deployment;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Authorised CSV exports. Each type declares its permission, an explicit column allow-list (no passwords, tokens,
 * secrets, credentials, raw gateway payloads or unnecessary PII such as phone numbers and addresses) and a query
 * that respects the actor's data scope. Rows are streamed in chunks, so large exports do not load into memory.
 * Spreadsheet formula injection is neutralised on every cell.
 */
class ExportService
{
    public const TYPES = [
        'customers' => ['label' => 'Customers', 'permission' => 'customers.export'],
        'orders' => ['label' => 'Orders', 'permission' => 'orders.export'],
        'invoices' => ['label' => 'Invoices', 'permission' => 'invoices.export'],
        'subscriptions' => ['label' => 'Subscriptions', 'permission' => 'subscriptions.export'],
        'renewals' => ['label' => 'Renewal invoices', 'permission' => 'subscriptions.export'],
        'wallet' => ['label' => 'Wallet transactions', 'permission' => 'wallet.export'],
        'tickets' => ['label' => 'Support tickets', 'permission' => 'tickets.export'],
        'staff_activity' => ['label' => 'Staff activity', 'permission' => 'security_logs.export'],
        'deployments' => ['label' => 'Deployment history', 'permission' => 'deployments.export'],
        'leads' => ['label' => 'Business Automation leads', 'permission' => 'leads.export'],
    ];

    public function allowedTypes(User $user): array
    {
        return array_filter(self::TYPES, fn ($t) => $user->hasPermission($t['permission']));
    }

    public function can(User $user, string $type): bool
    {
        return isset(self::TYPES[$type]) && $user->hasPermission(self::TYPES[$type]['permission']);
    }

    /** @return array{0: list<string>, 1: Builder, 2: callable} header, query, row mapper */
    public function definition(string $type, User $actor, array $filters): array
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : null;
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : null;
        $status = in_array($filters['status'] ?? null, [null, '', 'all', 'active'], true) ? null : $filters['status'];
        $activeOnly = ($filters['status'] ?? null) === 'active';
        $range = fn (Builder $q, string $col = 'created_at') => $q->when($from, fn ($q) => $q->where($col, '>=', $from))->when($to, fn ($q) => $q->where($col, '<=', $to));
        $d = fn ($v) => $v ? Carbon::parse($v)->format('Y-m-d H:i') : '';
        $rs = fn (?int $minor) => $minor === null ? '' : number_format($minor / 100, 2, '.', '');

        return match ($type) {
            'customers' => [
                ['ID', 'Name', 'Email', 'Email verified', 'Active', 'Orders', 'Registered'],
                $range(User::query()->where('role', User::ROLE_CUSTOMER)->withCount('orders')),
                fn (User $u) => [$u->id, $u->name, $u->email, $u->email_verified_at ? 'yes' : 'no', ($u->is_active ?? true) ? 'yes' : 'no', $u->orders_count, $d($u->created_at)],
            ],
            'orders' => [
                ['Number', 'Customer email', 'Status', 'Currency', 'Subtotal', 'Discount', 'Tax', 'Total', 'Wallet applied', 'Payment method', 'Paid at', 'Created'],
                $range(Order::query()->with('user:id,email'))->when($status, fn ($q) => $q->where('status', $status)),
                fn (Order $o) => [$o->number, $o->user?->email, $o->status, $o->currency, $rs($o->subtotal_minor), $rs($o->discount_minor), $rs($o->tax_minor), $rs($o->total_minor), $rs($o->wallet_applied_minor), $o->payment_method, $d($o->paid_at), $d($o->created_at)],
            ],
            'invoices', 'renewals' => [
                ['Number', 'Type', 'Customer email', 'Status', 'Subtotal', 'Tax', 'Total', 'Period start', 'Period end', 'Issued', 'Due', 'Paid'],
                $range(Invoice::query()->with('user:id,email'), 'issued_at')
                    ->when($type === 'renewals', fn ($q) => $q->whereNotNull('subscription_id'))
                    ->when($status, fn ($q) => $q->where('status', $status)),
                fn (Invoice $i) => [$i->number, $i->type, $i->user?->email, $i->status, $rs($i->subtotal_minor), $rs($i->tax_minor), $rs($i->total_minor), $d($i->period_start), $d($i->period_end), $d($i->issued_at), $d($i->due_at), $d($i->paid_at)],
            ],
            'subscriptions' => [
                ['ID', 'Customer email', 'Plan', 'Billing period', 'Status', 'Agreed price', 'Discount %', 'Paid through', 'Next due', 'Ends at period end', 'Renewal failures', 'Created'],
                $range(Subscription::query()->with('user:id,email', 'plan:id,name'))->when($status, fn ($q) => $q->where('status', $status)),
                fn (Subscription $s) => [$s->id, $s->user?->email, $s->plan?->name, $s->billing_interval, $s->status, $rs($s->price_minor), (int) $s->discount_percent, $d($s->period_end), $d($s->next_due_at), $s->cancel_at_period_end ? 'yes' : 'no', (int) $s->renewal_failures, $d($s->created_at)],
            ],
            'wallet' => [
                ['ID', 'Customer email', 'Type', 'Source', 'Amount', 'Balance after', 'Description', 'Created'],
                $range(WalletTransaction::query()->with('wallet.user:id,email')),
                fn (WalletTransaction $t) => [$t->id, $t->wallet?->user?->email, $t->type, $t->source, $rs($t->amount_minor), $rs($t->balance_after_minor), $t->description, $d($t->created_at)],
            ],
            'tickets' => [
                // Ticket bodies, private notes and attachments are never exported.
                ['Number', 'Customer email', 'Department', 'Subject', 'Priority', 'Status', 'Assigned to', 'Escalation', 'First response', 'SLA missed', 'Last activity', 'Created'],
                $range(Ticket::query()->with('user:id,email', 'department:id,name', 'assignee:id,name'))
                    ->when(! $actor->isSuper(), fn ($q) => $q->whereIn('department_id', $actor->departments()->pluck('departments.id')))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->when($activeOnly, fn ($q) => $q->where('status', '!=', 'closed')),
                fn (Ticket $t) => [$t->number, $t->user?->email, $t->department?->name, $t->subject, $t->priority, $t->status, $t->assignee?->name, (int) $t->escalation_level, $d($t->first_response_at), $t->sla_breached_at ? 'yes' : 'no', $d($t->last_activity_at), $d($t->created_at)],
            ],
            'staff_activity' => [
                // Audit meta is not exported (it may contain identifiers); only who/what/when/where.
                ['ID', 'When', 'Staff member', 'Action', 'Subject', 'IP'],
                $range(AuditLog::query()->with('user:id,name,role')->whereHas('user', fn ($q) => $q->whereIn('role', [User::ROLE_STAFF, User::ROLE_ADMIN]))),
                fn (AuditLog $l) => [$l->id, $d($l->created_at), $l->user?->name, $l->action, $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '', $l->ip],
            ],
            'deployments' => [
                ['ID', 'Service', 'Customer email', 'Server', 'Recipe', 'Version', 'Mode', 'Status', 'Attempt', 'Error summary', 'Started', 'Finished'],
                $range(Deployment::query()->with('service:id,label,user_id', 'service.user:id,email', 'server:id,label'))->when($status, fn ($q) => $q->where('status', $status)),
                fn (Deployment $x) => [$x->id, $x->service?->label, $x->service?->user?->email, $x->server?->label, $x->recipe, $x->recipe_version, $x->mode, $x->status, $x->attempt, $x->error_summary, $d($x->started_at), $d($x->finished_at)],
            ],
            // No phone numbers or requirement text (may contain business-sensitive detail); scoped to visible leads.
            'leads' => [
                ['Reference', 'Customer', 'Email', 'Company', 'Business type', 'Status', 'Sales owner', 'Quote', 'Follow-up', 'Received'],
                $range(AutomationRequest::query()->visibleTo($actor)->with('assignee:id,name'))->when($status, fn ($q) => $q->where('status', $status)),
                fn (AutomationRequest $l) => [$l->reference, $l->name, $l->email, $l->company, $l->business_type, $l->status, $l->assignee?->name, $rs($l->quote_minor), $d($l->follow_up_at), $d($l->created_at)],
            ],
        };
    }

    /** Write the CSV to an output stream. */
    public function stream(string $type, User $actor, array $filters, $handle): int
    {
        [$header, $query, $map] = $this->definition($type, $actor, $filters);
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so spreadsheet apps read ₹ and names correctly
        fputcsv($handle, $header);
        $rows = 0;

        $query->chunkById(500, function ($chunk) use ($handle, $map, &$rows) {
            foreach ($chunk as $model) {
                fputcsv($handle, array_map([self::class, 'safeCell'], $map($model)));
                $rows++;
            }
        });

        return $rows;
    }

    /** Neutralise spreadsheet formulas (CSV injection): cells starting with = + - @ tab or CR get a leading quote. */
    public static function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value) ? "'".$value : $value;
    }
}
