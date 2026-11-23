<?php

namespace App\Services;

use App\Jobs\SendRenewalReminder;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionReminder;
use App\Services\Billing\LifecycleService;
use App\Services\Billing\SubscriptionService;
use App\Support\BillingSettings;
use App\Support\SubscriptionStatus as S;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily billing run (`billing:run`, scheduled). For every open subscription:
 *   1. issue the renewal invoice N days before the due date (admin-configurable, default 7);
 *   2. send the configured reminders (before / on the due date / overdue) — each at most once per due date;
 *   3. move unpaid subscriptions through grace period → overdue → suspension pending (per service, never per account).
 * Customers pay renewal invoices themselves; stored payment credentials are never charged. Safe to re-run any
 * number of times a day: invoices, reminders and admin tasks are all idempotent.
 */
class RenewalService
{
    public function __construct(private SubscriptionService $subs, private Notifier $notifier, private LifecycleService $lifecycle) {}

    /** @return array{invoices: int, reminded: int, overdue: int, suspension_pending: int, errors: int, orders: int} */
    public function run(?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['invoices' => 0, 'reminded' => 0, 'overdue' => 0, 'suspension_pending' => 0, 'errors' => 0, 'orders' => 0];
        // Period-end cancellations and (optional) automatic expiry first, so those subscriptions are not invoiced.
        $stats += $this->lifecycle->process($now);

        Subscription::with('user', 'plan', 'service')
            ->whereIn('status', S::OPEN_STATES)->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', $now->copy()->addDays(max(BillingSettings::invoiceDaysBefore(), BillingSettings::beforeOffsets()[0] ?? 0))->endOfDay())
            ->chunkById(100, function ($chunk) use (&$stats, $now) {
                foreach ($chunk as $sub) {
                    try {
                        $this->process($sub, $now, $stats);
                        $this->lifecycle->clearFailures($sub);
                    } catch (Throwable $e) {
                        // One bad subscription must not stop the run; staff see it in the log, Admin → Billing → Failed renewals and Telegram.
                        $stats['errors']++;
                        $this->lifecycle->recordFailure($sub, $e);
                        Log::error('billing.run failed for subscription', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
                        $this->notifier->admin('renewal_due', "sub:{$sub->id}:error:".$now->toDateString(), "Billing run error for subscription #{$sub->id}. Check the application log.");
                    }
                }
            });

        $stats['orders'] = $stats['invoices']; // backwards-compatible key: one renewal order per invoice

        return $stats;
    }

    public function process(Subscription $sub, Carbon $now, array &$stats): void
    {
        if ($sub->cancel_at_period_end) {
            return; // ends at period end: no renewal invoice, no reminders
        }
        $due = $sub->next_due_at->copy();

        // 1. Renewal invoice (also generated late if the run was down for a while).
        $invoice = Invoice::where('subscription_id', $sub->id)->where('period_start', $due)->first();
        if (! $invoice && $now->gte($due->copy()->subDays(BillingSettings::invoiceDaysBefore())->startOfDay())) {
            $invoice = $this->subs->ensureRenewalInvoice($sub);
            if ($invoice) {
                $stats['invoices']++;
            }
        }
        if (! $invoice) {
            return; // nothing billable yet (or no agreed price: staff were alerted)
        }
        if ($invoice->status !== 'unpaid') {
            return; // paid: the payment path already extended the subscription
        }
        if ($sub->status === S::ACTIVE) {
            $this->subs->transition($sub, S::RENEWAL_DUE, 'Renewal invoice '.$invoice->number.' issued.');
        }

        // 2. Reminders.
        if (BillingSettings::remindersEnabled()) {
            $stats['reminded'] += $this->reminders($sub, $invoice, $due, $now);
        }

        // 3. Overdue → grace → overdue → suspension pending.
        if ($now->lte($due)) {
            return;
        }
        $graceEnd = $due->copy()->addDays(BillingSettings::graceDays());

        if (in_array($sub->status, [S::ACTIVE, S::RENEWAL_DUE, S::LEGACY_PAST_DUE], true)) {
            $to = $now->lt($graceEnd) ? S::GRACE : S::OVERDUE;
            $this->subs->transition($sub, $to, $to === S::GRACE
                ? 'Payment overdue — grace period until '.$graceEnd->format('d M Y').'.'
                : 'Payment overdue.', null, ['grace_ends_at' => $graceEnd, 'overdue_notified_at' => $now]);
            $this->notifier->admin('invoice_due', "sub:{$sub->id}:overdue:{$due->timestamp}", "Renewal overdue: {$this->subs->label($sub)} ({$sub->user->email}), invoice {$invoice->number}.");
            $stats['overdue']++;
        }

        if ($sub->status === S::GRACE && $now->gte($sub->grace_ends_at ?? $graceEnd)) {
            $this->subs->transition($sub, S::OVERDUE, 'Grace period ended without payment.');
        }

        if ($sub->status === S::OVERDUE) {
            if (BillingSettings::autoSuspend() && ! $sub->suspension_exempt) {
                $this->warn($sub, $invoice, $due);
                $this->subs->beginSuspension($sub, 'Renewal invoice '.$invoice->number.' unpaid after the grace period.');
                $stats['suspension_pending']++;
            } else {
                $this->notifier->admin('invoice_due', "sub:{$sub->id}:grace-ended:{$due->timestamp}",
                    "Grace period ended for {$this->subs->label($sub)} ({$sub->user->email}). Auto-suspension is ".($sub->suspension_exempt ? 'exempted' : 'off').' — review in Admin → Billing.');
            }
        }

        $sub->forceFill(['last_processed_at' => $now])->save();
    }

    /** Sends at most one reminder per run: the most relevant one that is due. Older missed ones are recorded as skipped. */
    private function reminders(Subscription $sub, Invoice $invoice, Carbon $due, Carbon $now): int
    {
        $daysUntil = (int) $now->copy()->startOfDay()->diffInDays($due->copy()->startOfDay(), false);
        $kinds = [];

        if ($daysUntil > 0) {
            $applicable = array_values(array_filter(BillingSettings::beforeOffsets(), fn ($d) => $d >= $daysUntil));
            if ($applicable) {
                $kinds = array_map(fn ($d) => "before:{$d}", $applicable); // largest first; last = most recent
            }
        } elseif ($daysUntil === 0) {
            if (BillingSettings::dueDayReminder()) {
                $kinds = ['due'];
            }
        } else {
            $applicable = array_values(array_filter(BillingSettings::overdueOffsets(), fn ($d) => $d <= -$daysUntil));
            $kinds = array_reverse(array_map(fn ($d) => "overdue:{$d}", $applicable)); // smallest first; last = most recent
        }

        if (! $kinds) {
            return 0;
        }

        $send = array_pop($kinds);
        foreach ($kinds as $older) {
            $this->record($sub, $invoice, $older, $due, 'skipped', 'Superseded by a later reminder.');
        }

        $reminder = $this->record($sub, $invoice, $send, $due, 'pending');
        if (! $reminder) {
            return 0; // already handled for this due date
        }
        SendRenewalReminder::dispatch($reminder->id);
        $sub->forceFill(['reminder_sent_at' => $now])->save();

        return 1;
    }

    private function warn(Subscription $sub, Invoice $invoice, Carbon $due): void
    {
        if ($reminder = $this->record($sub, $invoice, 'suspension_warning', $due, 'pending')) {
            SendRenewalReminder::dispatch($reminder->id);
        }
    }

    /** Insert-once (unique subscription+kind+due date). Returns null when this reminder already exists. */
    private function record(Subscription $sub, Invoice $invoice, string $kind, Carbon $due, string $status, ?string $error = null): ?SubscriptionReminder
    {
        try {
            return SubscriptionReminder::create([
                'subscription_id' => $sub->id, 'invoice_id' => $invoice->id, 'kind' => $kind,
                'due_date' => $due->toDateString(), 'status' => $status, 'error' => $error,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
