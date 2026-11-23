<?php

namespace App\Services\Billing;

use App\Models\Addon;
use App\Models\CancellationRequest;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\User;
use App\Services\Notifier;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Support\Audit;
use App\Support\BillingSettings;
use App\Support\Money;
use App\Support\SubscriptionStatus as S;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Subscription lifecycle beyond renewals: recurring add-ons, customer cancellation requests (immediate or at the end of
 * the paid period), automatic expiry and failed-renewal bookkeeping. Nothing here deletes a service, server or data:
 * a cancelled/expired service creates a "retention_review" staff task instead. No refund or proration amount is
 * ever computed automatically.
 */
class LifecycleService
{
    public function __construct(private SubscriptionService $subs, private Notifier $notifier) {}

    // ------------------------------------------------------------------ add-ons

    /** Snapshot recurring add-ons bought with the plan (called when the subscription is created). */
    public function snapshotAddons(Subscription $sub, OrderItem $item): void
    {
        foreach ((array) ($item->config['addons'] ?? []) as $row) {
            $addon = Addon::where('slug', $row['slug'] ?? '')->first();
            if (($row['price_minor'] ?? null) === null || ($addon && $addon->billing_type !== 'recurring')) {
                continue; // one-time or unpriced add-ons are never re-billed
            }
            SubscriptionAddon::create([
                'subscription_id' => $sub->id, 'addon_id' => $addon?->id, 'slug' => (string) $row['slug'], 'name' => (string) ($row['name'] ?? $row['slug']),
                'price_minor' => (int) $row['price_minor'], 'status' => 'active', 'starts_at' => $sub->period_start,
            ]);
        }
    }

    /** Staff add a recurring add-on at an explicitly agreed price (audited). Billing starts per the mid-cycle policy. */
    public function addAddon(Subscription $sub, Addon $addon, int $priceMinor, User $actor): SubscriptionAddon
    {
        if (in_array($sub->status, [S::CANCELLED, S::EXPIRED], true)) {
            throw new InvalidArgumentException('Add-ons cannot be added to a cancelled or expired subscription.');
        }
        if ($sub->addons()->where('slug', $addon->slug)->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('That add-on is already active on this subscription.');
        }

        $policy = BillingSettings::addonMidcyclePolicy();
        $row = SubscriptionAddon::create([
            'subscription_id' => $sub->id, 'addon_id' => $addon->id, 'slug' => $addon->slug, 'name' => $addon->name,
            'price_minor' => $priceMinor, 'status' => 'active', 'created_by' => $actor->id,
            // Both policies bill the add-on from the next renewal invoice; "manual_invoice" means staff raise any
            // part-period charge themselves. Nothing is prorated automatically.
            'starts_at' => $sub->next_due_at ?? now(),
        ]);
        $this->subs->event($sub, 'note', null, null, "Add-on {$addon->name} added at ".Money::inr($priceMinor).' per period, billed from the next renewal'
            .($policy === 'manual_invoice' ? ' (any part-period charge is raised manually by staff).' : '.'), $actor, ['addon' => $addon->slug, 'policy' => $policy]);
        Audit::log('billing.subscription.addon_added', $sub, ['addon' => $addon->slug, 'price' => $priceMinor, 'policy' => $policy], $actor->id);

        return $row;
    }

    /** Stops future billing of the add-on from the next renewal. No refund is calculated. */
    public function cancelAddon(SubscriptionAddon $row, User $actor): void
    {
        if ($row->status !== 'active') {
            return;
        }
        $row->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->subs->event($row->subscription, 'note', null, null, "Add-on {$row->name} removed; it will not be billed on future renewals.", $actor, ['addon' => $row->slug]);
        Audit::log('billing.subscription.addon_removed', $row->subscription, ['addon' => $row->slug], $actor->id);
    }

    /** Add-on lines for the renewal period starting at $periodStart (shape matches OrderService::computeLines). */
    public function renewalAddonLines(Subscription $sub, Carbon $periodStart): Collection
    {
        return $sub->addons()->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $periodStart))
            ->orderBy('id')->get()
            ->map(fn (SubscriptionAddon $a) => (new Addon)->forceFill(['slug' => $a->slug, 'name' => $a->name, 'price_minor' => (int) $a->price_minor]));
    }

    // ------------------------------------------------------------------ cancellation

    public function requestCancellation(Subscription $sub, User $customer, string $type, ?string $reason): CancellationRequest
    {
        if ($sub->user_id !== $customer->id) {
            throw new InvalidArgumentException('Not your subscription.');
        }
        if (! BillingSettings::cancellationEnabled()) {
            throw new InvalidArgumentException('Online cancellation is not available. Please open a support ticket.');
        }
        if (in_array($sub->status, [S::CANCELLED, S::EXPIRED], true)) {
            throw new InvalidArgumentException('This subscription has already ended.');
        }
        if ($type === 'immediate' && ! BillingSettings::immediateCancellationAllowed()) {
            throw new InvalidArgumentException('Cancellation takes effect at the end of the paid period.');
        }

        try {
            $request = CancellationRequest::create([
                'subscription_id' => $sub->id, 'user_id' => $customer->id, 'type' => $type === 'immediate' ? 'immediate' : 'end_of_period',
                'reason' => $reason ? mb_substr($reason, 0, 1000) : null, 'status' => 'pending', 'open_key' => "sub:{$sub->id}",
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('A cancellation request for this subscription is already in progress.');
        }

        $this->subs->event($sub, 'note', null, null, 'Customer requested cancellation ('.str_replace('_', ' ', $request->type).').', $customer);
        Audit::log('billing.cancellation.requested', $sub, ['request' => $request->id, 'type' => $request->type], $customer->id);
        $this->notifier->admin('renewal_due', "cancel-request:{$request->id}", "Cancellation requested for {$this->subs->label($sub)} ({$customer->email}).");

        if (! BillingSettings::cancellationNeedsApproval()) {
            $this->approve($request, null, 'Approved automatically (approval not required).');
        }

        return $request->fresh();
    }

    public function approve(CancellationRequest $request, ?User $actor, ?string $note = null): void
    {
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException('Only pending requests can be approved.');
        }

        DB::transaction(function () use ($request, $actor, $note) {
            $sub = $request->subscription()->lockForUpdate()->firstOrFail();

            if ($request->type === 'immediate') {
                $this->voidFutureRenewalInvoices($sub, now(), $actor);
                $this->subs->cancel($sub, $actor, 'customer request #'.$request->id);
                $this->retentionTask($sub, 'Cancelled immediately at the customer\'s request.');
                $request->update(['status' => 'completed', 'open_key' => null, 'effective_at' => now()]);
            } else {
                $at = $sub->period_end ?? $sub->next_due_at ?? now();
                $sub->forceFill(['cancel_at_period_end' => true, 'cancel_at' => $at])->save();
                $this->voidFutureRenewalInvoices($sub, $at, $actor);
                $this->subs->event($sub, 'note', null, null, 'Cancellation approved: the service ends on '.$at->format('d M Y').' and will not renew.', $actor);
                $request->update(['status' => 'approved', 'effective_at' => $at]);
            }
            $request->update(['reviewed_by' => $actor?->id, 'reviewed_at' => now(), 'review_note' => $note ? mb_substr($note, 0, 500) : null]);
        });

        Audit::log('billing.cancellation.approved', $request->subscription, ['request' => $request->id], $actor?->id);
        $this->notifier->customer('cancellation_update', "cancel:{$request->id}:approved", $request->user, 'cancellation_update', [
            'service' => $this->subs->label($request->subscription), 'date' => $request->fresh()->effective_at?->format('d M Y') ?? '', 'url' => route('dashboard.subscriptions.show', $request->subscription_id),
        ]);
    }

    public function reject(CancellationRequest $request, User $actor, string $note): void
    {
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException('Only pending requests can be rejected.');
        }
        $request->update(['status' => 'rejected', 'open_key' => null, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => mb_substr($note, 0, 500)]);
        $this->subs->event($request->subscription, 'note', null, null, 'Cancellation request not approved: '.mb_substr($note, 0, 200), $actor);
        Audit::log('billing.cancellation.rejected', $request->subscription, ['request' => $request->id], $actor->id);
        $this->notifier->customer('cancellation_update', "cancel:{$request->id}:rejected", $request->user, 'cancellation_rejected', [
            'service' => $this->subs->label($request->subscription), 'url' => route('dashboard.subscriptions.show', $request->subscription_id),
        ]);
    }

    /** Customer withdraws a pending request, or reverses an approved end-of-period cancellation before it happens. */
    public function withdraw(CancellationRequest $request, User $customer): void
    {
        if ($request->user_id !== $customer->id || ! in_array($request->status, ['pending', 'approved'], true)) {
            throw new InvalidArgumentException('This request can no longer be withdrawn.');
        }
        DB::transaction(function () use ($request, $customer) {
            if ($request->status === 'approved') {
                $request->subscription->forceFill(['cancel_at_period_end' => false, 'cancel_at' => null])->save();
            }
            $request->update(['status' => 'withdrawn', 'open_key' => null]);
            $this->subs->event($request->subscription, 'note', null, null, 'Customer withdrew the cancellation request; the subscription will renew normally.', $customer);
        });
        Audit::log('billing.cancellation.withdrawn', $request->subscription, ['request' => $request->id], $customer->id);
    }

    /** Unpaid renewal invoices for periods at/after $from are voided (and their unpaid renewal orders cancelled). */
    private function voidFutureRenewalInvoices(Subscription $sub, Carbon $from, ?User $actor): void
    {
        $invoices = Invoice::where('subscription_id', $sub->id)->where('status', 'unpaid')->where('period_start', '>=', $from->copy()->subSecond())->get();
        foreach ($invoices as $invoice) {
            $invoice->update(['status' => 'cancelled']);
            if ($invoice->order_id && ($order = Order::find($invoice->order_id)) && ! $order->paid_at) {
                try {
                    app(OrderService::class)->cancel($order, $actor, 'Subscription cancellation');
                } catch (OrderException|InvalidArgumentException) {
                    // An order that cannot move to cancelled stays as is; the voided invoice is what customers see.
                }
            }
            $this->subs->event($sub, 'invoice', null, null, "Renewal invoice {$invoice->number} voided because the subscription is cancelled.", $actor, [], $invoice->id);
        }
    }

    // ------------------------------------------------------------------ daily lifecycle (called by billing:run)

    /** @return array{cancelled: int, expired: int} */
    public function process(?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['cancelled' => 0, 'expired' => 0];

        // 1. Cancellation at period end.
        Subscription::with('user')->where('cancel_at_period_end', true)->whereNotNull('cancel_at')->where('cancel_at', '<=', $now)
            ->whereNotIn('status', [S::CANCELLED, S::EXPIRED])->chunkById(100, function ($subs) use (&$stats) {
                foreach ($subs as $sub) {
                    $this->subs->cancel($sub, null, 'end of the paid period (customer request)');
                    CancellationRequest::where('subscription_id', $sub->id)->where('status', 'approved')->update(['status' => 'completed', 'open_key' => null]);
                    $this->retentionTask($sub, 'Paid period ended after an approved cancellation.');
                    $stats['cancelled']++;
                }
            });

        // 2. Automatic expiry (off unless the owner sets a number of days).
        if ($days = BillingSettings::expireAfterDays()) {
            Subscription::with('user')->whereIn('status', [S::OVERDUE, S::SUSPENDED])->whereNotNull('next_due_at')
                ->where('next_due_at', '<=', $now->copy()->subDays($days))->chunkById(100, function ($subs) use (&$stats, $days) {
                    foreach ($subs as $sub) {
                        if ($this->subs->transition($sub, S::EXPIRED, "Expired: unpaid {$days} days after the due date.", null, ['expired_at' => now()])) {
                            $this->retentionTask($sub, "Expired after {$days} unpaid days.");
                            $this->notifier->customer('service_expired', "sub:{$sub->id}:expired", $sub->user, 'service_expired', [
                                'service' => $this->subs->label($sub), 'url' => route('dashboard.subscriptions.show', $sub),
                            ]);
                            $stats['expired']++;
                        }
                    }
                });
        }

        return $stats;
    }

    /** Staff task: decide what happens to the server/data under the retention policy. Nothing is deleted automatically. */
    private function retentionTask(Subscription $sub, string $reason): void
    {
        $this->subs->openAction($sub, 'retention_review', $reason.' Review data retention and de-provision manually if appropriate — nothing was deleted.');
        $this->notifier->admin('renewal_due', "sub:{$sub->id}:retention", "Service ended: {$this->subs->label($sub)} ({$sub->user->email}). Retention review task created — nothing was deleted.");
    }

    // ------------------------------------------------------------------ failed renewals

    public function recordFailure(Subscription $sub, \Throwable $e): void
    {
        $failures = (int) $sub->renewal_failures + 1;
        // Class + truncated message only; exception messages from this code path never include secrets.
        Subscription::whereKey($sub->id)->update([
            'renewal_failures' => $failures, 'last_renewal_failed_at' => now(),
            'last_renewal_error' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 300),
        ]);
        if ($failures === BillingSettings::renewalFailureAlertAfter()) {
            $this->notifier->admin('renewal_due', "sub:{$sub->id}:failures:{$failures}:".now()->toDateString(),
                "Renewal processing failed {$failures} times for subscription #{$sub->id}. Review in Admin → Billing → Failed renewals.");
        }
    }

    public function clearFailures(Subscription $sub): void
    {
        if ((int) $sub->renewal_failures > 0) {
            Subscription::whereKey($sub->id)->update(['renewal_failures' => 0, 'last_renewal_error' => null]);
        }
    }
}
