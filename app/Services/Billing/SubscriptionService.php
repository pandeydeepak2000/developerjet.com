<?php

namespace App\Services\Billing;

use App\Models\BillingAction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Notifier;
use App\Services\OrderService;
use App\Support\Audit;
use App\Support\BillingCycle;
use App\Support\BillingSettings;
use App\Support\Money;
use App\Support\SubscriptionStatus as S;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Subscription lifecycle: creation from a paid order, renewal invoices, state transitions, suspension and
 * reactivation. Suspension/reactivation of real infrastructure happens ONLY through a verified ServiceSuspender
 * with live suspension enabled; otherwise an admin task (billing_actions) is opened and staff are alerted.
 * Nothing is ever deleted, and stored payment details are never charged automatically.
 */
class SubscriptionService
{
    public function __construct(
        private InvoiceService $invoices,
        private Notifier $notifier,
        private ServiceSuspender $suspender,
    ) {}

    /** Called inside the order-paid transaction for each recurring hosting item. */
    public function createFromOrderItem(Order $order, OrderItem $item, Service $service): Subscription
    {
        $start = Carbon::instance($order->paid_at ?? now());
        $end = BillingCycle::advance($start, $item->billing_interval, (int) $start->day);

        $sub = Subscription::create([
            'user_id' => $order->user_id, 'service_id' => $service->id, 'plan_id' => $item->plan_id, 'order_id' => $order->id,
            'billing_interval' => $item->billing_interval, 'anchor_day' => (int) $start->day,
            // Agreed price snapshot: what the customer accepted at checkout.
            'price_minor' => $item->unit_minor, 'discount_percent' => (int) ($item->config['pricing']['discount_percent'] ?? 0),
            'currency' => $order->currency ?? 'INR', 'tax_class' => $item->config['pricing']['tax_class'] ?? null,
            'status' => S::ACTIVE, 'period_start' => $start, 'period_end' => $end, 'next_due_at' => $end,
        ]);
        $this->event($sub, 'status', null, S::ACTIVE, 'Subscription started from order '.$order->number.'.', null, ['order_id' => $order->id]);
        app(LifecycleService::class)->snapshotAddons($sub, $item);

        return $sub;
    }

    /** Validated status change + history row. Returns false (and changes nothing) when already in that state. */
    public function transition(Subscription $sub, string $to, string $note, ?User $actor = null, array $extra = []): bool
    {
        $from = $sub->status;
        if ($from === $to && ! $extra) {
            return false;
        }
        S::assert($from, $to);
        $sub->forceFill(['status' => $to] + $extra)->save();
        if ($from !== $to) {
            $this->event($sub, 'status', $from, $to, $note, $actor);
        }

        return true;
    }

    /**
     * Renewal invoice for the period starting at next_due_at. Idempotent three ways: an existing unpaid/paid invoice
     * for the period is returned; the renewal order is keyed by subscription+period; and invoices has a UNIQUE
     * (subscription_id, period_start) constraint for concurrent runs.
     */
    public function ensureRenewalInvoice(Subscription $sub): ?Invoice
    {
        $periodStart = $sub->next_due_at->copy();

        if ($existing = Invoice::where('subscription_id', $sub->id)->where('period_start', $periodStart)->first()) {
            return $existing;
        }

        $plan = $sub->plan;
        if ($sub->price_minor === null && ($agreed = $this->agreedPriceFromOrder($sub)) !== null) {
            // Subscriptions created before price snapshots existed: the price on the original order is the agreed one.
            $sub->forceFill(['price_minor' => $agreed])->save();
            $this->event($sub, 'price', null, null, 'Agreed price captured from the original order: '.Money::inr($agreed).'.');
        }
        $price = $sub->price_minor ?? $plan?->chargeablePriceFor($sub->billing_interval);
        if (! $plan || $price === null) {
            $this->notifier->admin('renewal_due', "sub:{$sub->id}:noprice:{$periodStart->timestamp}", "Renewal for subscription #{$sub->id} cannot be invoiced: no agreed or approved price.");
            $this->event($sub, 'invoice', null, null, 'Renewal invoice not generated: no agreed or approved price.');

            return null;
        }

        $periodEnd = BillingCycle::advance($periodStart, $sub->billing_interval, $sub->anchor_day);
        // Agreed recurring add-ons (price snapshots) active for this period.
        $addons = app(LifecycleService::class)->renewalAddonLines($sub, $periodStart);
        $summary = ['lines' => [[
            'plan' => $plan->loadMissing('category'), 'interval' => $sub->billing_interval, 'price_minor' => (int) $price,
            'setup_minor' => 0, // never re-charge the setup fee on renewal
            'discount_percent' => (int) $sub->discount_percent,
            'tax_class' => $sub->tax_class ?: $plan->tax_class,
            'addons' => $addons, 'available' => true, 'line_total_minor' => (int) $price + (int) $addons->sum('price_minor'),
        ]], 'total_minor' => (int) $price + (int) $addons->sum('price_minor'), 'unavailable' => 0];

        try {
            return DB::transaction(function () use ($sub, $summary, $periodStart, $periodEnd) {
                $order = app(OrderService::class)->placeRenewal($sub->user, $summary, "renewal:{$sub->id}:{$periodStart->timestamp}", $sub->id);
                $invoice = $this->invoices->issueRenewal($order, $sub, $periodStart, $periodEnd);
                $sub->forceFill(['renewal_order_id' => $order->id])->save();
                $this->event($sub, 'invoice', null, null, "Renewal invoice {$invoice->number} issued for ".$periodStart->format('d M Y').' – '.$periodEnd->format('d M Y').'.', null, [], $invoice->id, $order->id);

                return $invoice;
            });
        } catch (UniqueConstraintViolationException) {
            return Invoice::where('subscription_id', $sub->id)->where('period_start', $periodStart)->first(); // a concurrent run won
        }
    }

    /**
     * After a verified renewal payment (inside OrderService::markPaid's transaction): extend from the paid-through
     * date, then decide whether the service can simply stay active or needs reactivation.
     */
    public function applyRenewalPayment(Order $order, int $subscriptionId): void
    {
        $sub = Subscription::whereKey($subscriptionId)->where('user_id', $order->user_id)->lockForUpdate()->first();
        if (! $sub) {
            return;
        }

        $invoice = Invoice::where('order_id', $order->id)->where('type', 'invoice')->first();
        // Duplicate protection: this order already extended the subscription.
        if (SubscriptionEvent::where('subscription_id', $sub->id)->where('type', 'renewal')->where('order_id', $order->id)->exists()) {
            return;
        }

        $from = $invoice?->period_start ?? $sub->next_due_at ?? now();
        $newEnd = $invoice?->period_end ?? BillingCycle::advance($from, $sub->billing_interval, $sub->anchor_day);
        if ($sub->period_end && $newEnd->lte($sub->period_end)) {
            // Legacy renewal orders without a period: extend from the current paid-through date.
            $from = $sub->period_end->copy();
            $newEnd = BillingCycle::advance($from, $sub->billing_interval, $sub->anchor_day);
        }

        $wasStatus = $sub->status;
        $sub->forceFill([
            'period_start' => $from, 'period_end' => $newEnd, 'next_due_at' => $newEnd, 'renewal_order_id' => null,
            'reminder_sent_at' => null, 'overdue_notified_at' => null, 'grace_ends_at' => null,
        ])->save();
        $sub->service?->update(['renews_at' => $newEnd]);
        $this->event($sub, 'renewal', null, null, 'Renewal paid ('.$order->number.'). Paid through '.$newEnd->format('d M Y').'.', null, [], $invoice?->id, $order->id);

        if ($wasStatus === S::SUSPENDED) {
            $this->transition($sub, S::REACTIVATION_PENDING, 'Renewal paid while suspended — reactivation required.');
            DB::afterCommit(fn () => $this->attemptReactivation($sub->fresh()));
        } else {
            if ($wasStatus === S::SUSPENSION_PENDING) {
                $this->closeActions($sub, 'suspend', 'cancelled', 'Customer paid before the service was suspended.');
            }
            if ($wasStatus === S::CANCELLED || $wasStatus === S::EXPIRED) {
                $this->event($sub, 'note', null, null, 'Payment received for a '.$wasStatus.' subscription — staff review required.');
                $this->notifier->admin('renewal_due', "sub:{$sub->id}:paid-closed:{$order->id}", "Payment received for {$wasStatus} subscription #{$sub->id} ({$order->number}). Review manually.");

                return;
            }
            $this->transition($sub, S::ACTIVE, 'Renewal paid.');
        }
    }

    /** Overdue past grace: queue suspension (manual task) or, only with a verified adapter + live mode + no approval step, suspend. */
    public function beginSuspension(Subscription $sub, string $reason): void
    {
        if ($sub->suspension_exempt) {
            return;
        }
        $this->transition($sub, S::SUSPENSION_PENDING, $reason);
        $service = $sub->service;

        if ($service && ! BillingSettings::suspensionNeedsApproval() && BillingSettings::liveSuspension() && $this->suspender->supports($service)) {
            $this->suspendNow($sub, null, 'Automatic suspension (verified adapter).');

            return;
        }

        $this->openAction($sub, 'suspend', $reason);
        $this->notifier->admin('suspension_pending', "sub:{$sub->id}:suspend:{$sub->next_due_at?->timestamp}",
            "Suspension pending for {$this->label($sub)} ({$sub->user->email}) — renewal unpaid since {$sub->next_due_at?->format('d M Y')}. Action required in Admin → Billing.");
    }

    /**
     * Staff approved a pending suspension. With a verified adapter (and live mode) the adapter suspends the service;
     * otherwise staff confirm they suspended it by hand (`$manualConfirmed`). Adapter failures leave the
     * subscription in suspension_pending and alert staff.
     */
    public function suspendNow(Subscription $sub, ?User $actor, string $note, bool $manualConfirmed = false): bool
    {
        $service = $sub->service;

        if (! $manualConfirmed) {
            if (! $service || ! BillingSettings::liveSuspension() || ! $this->suspender->supports($service)) {
                $this->openAction($sub, 'suspend', 'No verified adapter: suspend the service manually, then confirm.');

                return false;
            }
            try {
                $this->suspender->suspend($service);
            } catch (Throwable $e) {
                $this->openAction($sub, 'suspend', 'Adapter suspension failed: '.mb_substr($e->getMessage(), 0, 150));
                $this->event($sub, 'suspension', null, null, 'Adapter suspension failed — still pending.', $actor);
                $this->notifier->admin('suspension_pending', "sub:{$sub->id}:suspend-failed:".now()->timestamp, "Suspension FAILED for {$this->label($sub)}: adapter error. Manual action required.");

                return false;
            }
        }

        if ($sub->status !== S::SUSPENSION_PENDING && $sub->status !== S::OVERDUE) {
            $this->transition($sub, S::SUSPENSION_PENDING, 'Suspension requested by staff.', $actor);
        }
        $this->transition($sub, S::SUSPENDED, $note, $actor, ['suspended_at' => now()]);
        $service?->update(['status' => 'suspended']);
        $this->closeActions($sub, 'suspend', 'done', $note, $actor);
        Audit::log('billing.subscription.suspended', $sub, ['manual' => $manualConfirmed], $actor?->id);

        return true;
    }

    /** After payment of a suspended service: adapter reactivation + health check, else a staff task. */
    public function attemptReactivation(Subscription $sub, ?User $actor = null): bool
    {
        $service = $sub->service;
        $canAuto = $service && BillingSettings::reactivationPolicy() === 'auto' && BillingSettings::liveSuspension() && $this->suspender->supports($service);

        if ($canAuto) {
            try {
                $this->suspender->reactivate($service);
                if (! $this->suspender->healthy($service)) {
                    throw new \RuntimeException('Health check failed after reactivation.');
                }

                return $this->confirmReactivated($sub, $actor, 'Reactivated by verified adapter; health check passed.');
            } catch (Throwable $e) {
                $this->event($sub, 'reactivation', null, null, 'Adapter reactivation failed: '.mb_substr($e->getMessage(), 0, 150), $actor);
            }
        }

        $this->openAction($sub, 'reactivate', 'Renewal paid. Reactivate the service, check it works, then confirm.');
        $this->notifier->admin('reactivation_pending', "sub:{$sub->id}:reactivate:{$sub->period_end?->timestamp}",
            "Reactivation pending for {$this->label($sub)} ({$sub->user->email}) — renewal paid. Action required in Admin → Billing.");

        return false;
    }

    /** Staff confirm the service really works again (or the adapter did it). Only now is it marked active. */
    public function confirmReactivated(Subscription $sub, ?User $actor, string $note): bool
    {
        $this->transition($sub, S::ACTIVE, $note, $actor, ['suspended_at' => null]);
        if ($sub->service && $sub->service->status === 'suspended') {
            $sub->service->update(['status' => 'active']);
        }
        $this->closeActions($sub, 'reactivate', 'done', $note, $actor);
        Audit::log('billing.subscription.reactivated', $sub, [], $actor?->id);
        $this->notifier->customer('service_reactivated', "sub:{$sub->id}:reactivated:{$sub->period_end?->timestamp}", $sub->user, 'service_reactivated',
            ['service' => $this->label($sub), 'url' => route('dashboard.subscriptions')]);

        return true;
    }

    public function cancel(Subscription $sub, ?User $actor, string $reason): void
    {
        $this->transition($sub, S::CANCELLED, 'Cancelled: '.$reason, $actor, ['cancelled_at' => now()]);
        $this->closeActions($sub, 'suspend', 'cancelled', 'Subscription cancelled.', $actor);
        $this->closeActions($sub, 'reactivate', 'cancelled', 'Subscription cancelled.', $actor);
        Audit::log('billing.subscription.cancelled', $sub, ['reason' => mb_substr($reason, 0, 200)], $actor?->id);
    }

    public function setExempt(Subscription $sub, bool $exempt, User $actor): void
    {
        $sub->forceFill(['suspension_exempt' => $exempt])->save();
        $this->event($sub, 'note', null, null, $exempt ? 'Exempted from automatic suspension.' : 'Suspension exemption removed.', $actor);
        Audit::log('billing.subscription.exempt', $sub, ['exempt' => $exempt], $actor->id);
    }

    /** Admin-only, audited change of the agreed price (applies from the NEXT renewal invoice). */
    public function changeAgreedPrice(Subscription $sub, int $priceMinor, User $actor, string $reason): void
    {
        $old = $sub->price_minor;
        $sub->forceFill(['price_minor' => $priceMinor])->save();
        $this->event($sub, 'price', null, null, 'Agreed price changed from '.Money::inr($old).' to '.Money::inr($priceMinor).': '.mb_substr($reason, 0, 200), $actor, ['old' => $old, 'new' => $priceMinor]);
        Audit::log('billing.subscription.price_changed', $sub, ['old' => $old, 'new' => $priceMinor], $actor->id);
    }

    public function event(Subscription $sub, string $type, ?string $from, ?string $to, string $note, ?User $actor = null, array $meta = [], ?int $invoiceId = null, ?int $orderId = null): void
    {
        SubscriptionEvent::create([
            'subscription_id' => $sub->id, 'type' => $type, 'from_status' => $from, 'to_status' => $to,
            'note' => mb_substr($note, 0, 500), 'actor_id' => $actor?->id, 'meta' => $meta ?: null,
            'invoice_id' => $invoiceId, 'order_id' => $orderId,
        ]);
    }

    public function label(Subscription $sub): string
    {
        return $sub->service?->label ?? $sub->plan?->name ?? 'subscription #'.$sub->id;
    }

    private function agreedPriceFromOrder(Subscription $sub): ?int
    {
        if (! $sub->order_id) {
            return null;
        }
        $unit = OrderItem::where('order_id', $sub->order_id)->where('plan_id', $sub->plan_id)
            ->where('billing_interval', $sub->billing_interval)->value('unit_minor');

        return $unit === null ? null : (int) $unit;
    }

    public function openAction(Subscription $sub, string $type, string $reason): BillingAction
    {
        $key = "sub:{$sub->id}:{$type}";
        $action = BillingAction::where('open_key', $key)->first();
        if ($action) {
            $action->update(['reason' => mb_substr($reason, 0, 300)]);

            return $action;
        }

        try {
            return BillingAction::create(['subscription_id' => $sub->id, 'service_id' => $sub->service_id, 'type' => $type, 'status' => 'open', 'open_key' => $key, 'reason' => mb_substr($reason, 0, 300)]);
        } catch (UniqueConstraintViolationException) {
            return BillingAction::where('open_key', $key)->firstOrFail();
        }
    }

    private function closeActions(Subscription $sub, string $type, string $status, string $note, ?User $actor = null): void
    {
        BillingAction::where('open_key', "sub:{$sub->id}:{$type}")->update([
            'status' => $status, 'open_key' => null, 'resolution_note' => mb_substr($note, 0, 500),
            'resolved_by' => $actor?->id, 'resolved_at' => now(),
        ]);
    }
}
