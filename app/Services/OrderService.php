<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Support\Audit;
use App\Support\BillingCycle;
use App\Support\Money;
use App\Support\OrderStatus as S;
use App\Support\Setting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public const KIND_BY_CATEGORY = [
        'hermes-cli' => 'hermes_cli', 'hermes-workspace' => 'hermes_workspace', 'n8n' => 'n8n',
        'ai-complete-bundle' => 'bundle', 'openclaw' => 'openclaw',
        'claude-code' => 'claude_code', 'docker-vps' => 'docker_vps',
    ];

    public function __construct(
        private WalletService $wallets,
        private TaxService $taxes,
        private InvoiceService $invoices,
        private Notifier $notifier,
    ) {}

    /**
     * Pure pricing: per-line figures plus totals. Used by checkout preview and order placement so they cannot diverge.
     *
     * @return array{lines: array<int, array<string, mixed>>, subtotal: int, discount: int, tax: int, total: int, tax_configured: bool}
     */
    public function computeLines(array $summary): array
    {
        $lines = [];
        $sub = $disc = $tax = $total = 0;

        foreach ($summary['lines'] as $line) {
            $plan = $line['plan'];
            $unit = (int) $line['price_minor'];
            // Per-period setup fee / discount come from the cart line (PlanPrice); renewals pass setup 0 and the
            // subscription's agreed discount. Older callers without these keys fall back to the plan values.
            $setup = (int) (array_key_exists('setup_minor', $line) ? $line['setup_minor'] : $plan->setup_fee_minor);
            $discountPct = (int) (array_key_exists('discount_percent', $line) ? $line['discount_percent'] : $plan->discount_percent);
            $discount = intdiv(($unit + $setup) * $discountPct, 100);
            $net = $unit + $setup - $discount;
            $lineTax = $this->taxes->compute($net, $line['tax_class'] ?? $plan->tax_class);

            $addonRows = [];
            $sub += $unit + $setup;
            $disc += $discount;
            $tax += $lineTax;
            $total += $net + $lineTax;

            foreach ($line['addons'] as $addon) {
                $addonTax = $this->taxes->compute((int) $addon->price_minor, 'standard');
                $addonRows[] = [
                    'plan_id' => null, 'name' => $addon->name, 'billing_interval' => $line['interval'],
                    'unit_minor' => $addon->price_minor, 'tax_minor' => $addonTax,
                    'total_minor' => $addon->price_minor + $addonTax,
                    'config' => ['addon_slug' => $addon->slug, 'for_plan' => $plan->slug],
                ];
                $sub += $addon->price_minor;
                $tax += $addonTax;
                $total += $addon->price_minor + $addonTax;
            }

            $lines[] = [
                'name' => $plan->name,
                'interval' => $line['interval'],
                'item' => [
                    'plan_id' => $plan->id, 'name' => $plan->name, 'billing_interval' => $line['interval'],
                    'unit_minor' => $unit, 'setup_minor' => $setup, 'discount_minor' => $discount,
                    'tax_minor' => $lineTax, 'total_minor' => $net + $lineTax,
                ],
                'config' => $this->planSnapshot($plan, $line['addons']) + ['pricing' => ['discount_percent' => $discountPct, 'setup_minor' => $setup, 'tax_class' => $line['tax_class'] ?? $plan->tax_class]],
                'addons' => $addonRows,
                'tax_class' => $plan->tax_class,
            ];
        }

        return ['lines' => $lines, 'subtotal' => $sub, 'discount' => $disc, 'tax' => $tax, 'total' => $total, 'tax_configured' => $this->taxes->configured('standard')];
    }

    /**
     * Create an order from a cart summary. Idempotent per ($user, $token): a double submit or retry returns the same order.
     *
     * @param  array{lines: array<int, array<string, mixed>>, total_minor: int, unavailable: int}  $summary
     */
    public function place(User $user, array $summary, array $billing, string $token, bool $useWallet, ?int $renewalOf = null): Order
    {
        $key = hash('sha256', $user->id.'|'.$token);

        if ($existing = Order::where('idempotency_key', $key)->first()) {
            return $existing;
        }

        if ($renewalOf === null) {
            $this->assertPurchasable($summary);
        } elseif (! $summary['lines']) {
            throw new OrderException('Nothing to renew.');
        }

        try {
            $order = DB::transaction(function () use ($user, $summary, $billing, $key, $renewalOf) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'number' => 'TMP-'.Str::random(12),
                    'status' => S::PENDING_PAYMENT,
                    'idempotency_key' => $key,
                    'customer_snapshot' => array_filter(['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]) + $billing,
                ]);

                $priced = $this->computeLines($summary);

                foreach ($priced['lines'] as $row) {
                    $order->items()->create($row['item'] + ['config' => $row['config']]);
                    foreach ($row['addons'] as $addonRow) {
                        $order->items()->create($addonRow);
                    }
                }

                $order->update([
                    'number' => 'DJ'.now()->format('ym').str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
                    'subtotal_minor' => $priced['subtotal'], 'discount_minor' => $priced['discount'],
                    'tax_minor' => $priced['tax'], 'total_minor' => $priced['total'],
                    'snapshot' => ['tax_configured' => $priced['tax_configured'], 'placed_at' => now()->toIso8601String()] + ($renewalOf ? ['renewal_subscription_id' => $renewalOf] : []),
                ]);

                $this->event($order, 'status', null, S::PENDING_PAYMENT, 'Order placed.', $user->id);

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            return Order::where('idempotency_key', $key)->firstOrFail();
        }

        Audit::log('order.placed', $order, ['total_minor' => $order->total_minor], $user->id);
        $this->notifier->admin('new_order', "order:{$order->id}", "New order {$order->number} · ".Money::inr($order->total_minor));

        if ($useWallet) {
            $this->applyWallet($order);
        }

        return $order->fresh();
    }

    /** Renewal order for an existing subscription: priced from the agreed snapshot, never blocked by catalogue changes. */
    public function placeRenewal(User $user, array $summary, string $token, int $subscriptionId): Order
    {
        return $this->place($user, $summary, [], $token, false, $subscriptionId);
    }

    private function assertPurchasable(array $summary): void
    {
        if (! $summary['lines'] || $summary['unavailable'] > 0) {
            throw new OrderException('Your cart contains unavailable items.');
        }

        foreach ($summary['lines'] as $line) {
            $plan = $line['plan'];

            if (! $plan->isApproved()) {
                throw new OrderException("“{$plan->name}” pricing has not been approved yet, so it cannot be purchased online. Please contact Sales.");
            }
            if ($plan->stock !== null && $plan->stock <= 0) {
                throw new OrderException("“{$plan->name}” is currently out of stock.");
            }
            if ($plan->category->slug === 'n8n' && ! Setting::bool('n8n.resale_verified')) {
                throw new OrderException('n8n plans are not yet available for online purchase while commercial licensing is being verified.');
            }
        }
    }

    private function planSnapshot($plan, $addons): array
    {
        $plan->loadMissing('resourceProfile', 'category');
        $rp = $plan->resourceProfile;

        return [
            'category' => $plan->category->slug,
            'plan_slug' => $plan->slug,
            'price_status' => $plan->price_status,
            'features' => $plan->features,
            'resources' => $rp ? $rp->only(['vcpu', 'ram_gb', 'disk_gb', 'disk_type', 'bandwidth_tb', 'status']) : null,
            'addons' => collect($addons)->map(fn (Addon $a) => ['slug' => $a->slug, 'name' => $a->name, 'price_minor' => $a->price_minor])->all(),
        ];
    }

    /** Reserve wallet balance against an unpaid order (ledger debit). Settles the order if the wallet covers it all. */
    public function applyWallet(Order $order): Order
    {
        if ($order->status !== S::PENDING_PAYMENT || $order->wallet_applied_minor > 0) {
            return $order;
        }

        $wallet = $this->wallets->walletFor($order->user);
        $apply = min($wallet->balance_minor, $order->total_minor);

        if ($apply <= 0) {
            return $order;
        }

        $this->wallets->debit($order->user, $apply, 'order', "order:{$order->id}:wallet", "Payment toward order {$order->number}");
        $order->update(['wallet_applied_minor' => $apply]);
        $this->event($order, 'payment', null, null, 'Wallet balance applied: '.Money::inr($apply));

        if ($order->payableMinor() === 0) {
            $this->markPaid($order->fresh(), null, 'wallet');
        }

        return $order->fresh();
    }

    public function event(Order $order, string $type, ?string $from, ?string $to, ?string $message, ?int $userId = null, bool $internal = false): void
    {
        $order->events()->create([
            'type' => $type, 'from_status' => $from, 'to_status' => $to,
            'message' => $message, 'user_id' => $userId ?? auth()->id(), 'is_internal' => $internal,
        ]);
    }

    public function transition(Order $order, string $to, ?User $actor = null, ?string $message = null, bool $viaPayment = false): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $message, $viaPayment) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! S::canTransition($from, $to)) {
                throw new OrderException('Cannot move an order from '.S::label($from).' to '.S::label($to).'.');
            }

            if ($to === S::PAID && ! $viaPayment) {
                throw new OrderException('An order can only become Paid through a verified payment.');
            }

            $paidBefore = $locked->paid_at !== null;
            if ($from === S::FAILED && $to === S::AWAITING_PROVISIONING && ! $paidBefore) {
                throw new OrderException('Only paid orders can return to provisioning.');
            }
            if ($from === S::FAILED && $to === S::PENDING_PAYMENT && $paidBefore) {
                throw new OrderException('A paid order cannot return to pending payment.');
            }
            if ($from === S::REFUND_PENDING && in_array($to, [S::ACTIVE, S::AWAITING_PROVISIONING], true) && ! $paidBefore) {
                throw new OrderException('Invalid refund reversal.');
            }

            $locked->update(['status' => $to]);
            $this->event($locked, 'status', $from, $to, $message, $actor?->id);
            Audit::log('order.status', $locked, ['from' => $from, 'to' => $to], $actor?->id);

            return $locked;
        });
    }

    /**
     * The only path to "Paid": called after a gateway payment was verified (signature/webhook/API) or the wallet fully covered
     * the order. Safe to call repeatedly — fulfilment happens once.
     */
    public function markPaid(Order $order, ?PaymentAttempt $attempt, string $method): Order
    {
        $fulfil = false;

        $order = DB::transaction(function () use ($order, $method, &$fulfil) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === S::CANCELLED) {
                // Money arrived for an order that was cancelled: never fulfil, flag for a manual refund.
                $this->event($locked, 'payment', null, null, 'Payment verified after cancellation — manual refund required.', null, true);
                $this->notifier->admin('payment_failure', "order:{$locked->id}:late", "Verified payment on CANCELLED order {$locked->number} — refund required.");

                return $locked;
            }

            if (S::isPaidState($locked->status)) {
                return $locked; // duplicate callback / retry
            }

            if ($locked->status === S::FAILED) {
                $locked->update(['status' => S::PENDING_PAYMENT]);
            }

            $locked->update(['paid_at' => now(), 'payment_method' => $method]);
            $this->transition($locked, S::PAID, null, 'Payment verified ('.$method.').', true);
            $this->transition($locked->fresh(), S::AWAITING_PROVISIONING, null, 'Queued for provisioning.');
            $this->fulfil($locked->fresh());
            $fulfil = true;

            return $locked->fresh();
        });

        if ($fulfil) {
            Audit::log('order.paid', $order, ['method' => $method], $order->user_id);
            $this->notifier->admin('payment_success', "order:{$order->id}", "Payment verified for {$order->number} · ".Money::inr($order->total_minor));
            $this->notifier->customer('payment_success', "order:{$order->id}", $order->user, 'order_paid',
                ['order' => $order->number, 'url' => route('dashboard.orders.show', $order)]);
            $this->invoices->issueForOrder($order);

            if (! isset($order->snapshot['renewal_subscription_id']) && $order->services()->where('status', 'pending_provisioning')->exists()) {
                $this->notifier->admin('vps_assignment_required', "order:{$order->id}", "Order {$order->number} is paid and needs a VPS assigned (awaiting VPS assignment).");
            }
        }

        return $order;
    }

    /** Create the purchased service/project records (inside the markPaid transaction). */
    private function fulfil(Order $order): void
    {
        if ($subscriptionId = $order->snapshot['renewal_subscription_id'] ?? null) {
            $this->applyRenewal($order, (int) $subscriptionId);

            return;
        }

        foreach ($order->items()->whereNotNull('plan_id')->with('plan.category')->get() as $item) {
            $plan = $item->plan;
            if ($plan->kind === 'hosting') {
                $service = Service::create([
                    'user_id' => $order->user_id, 'order_id' => $order->id, 'plan_id' => $plan->id,
                    'label' => $plan->name.' · '.$plan->category->name,
                    'kind' => self::KIND_BY_CATEGORY[$plan->category->slug] ?? 'hermes_cli',
                    'status' => 'pending_provisioning',
                    'renews_at' => BillingCycle::advance(now(), $item->billing_interval),
                    'config' => ['addons' => $item->config['addons'] ?? []],
                ]);
                app(SubscriptionService::class)->createFromOrderItem($order, $item, $service);
            } else {
                $project = Project::create([
                    'number' => 'TMP-'.Str::random(10), 'user_id' => $order->user_id, 'order_id' => $order->id,
                    'kind' => $plan->kind, 'title' => $plan->name, 'status' => 'planning',
                ]);
                $project->update(['number' => 'PRJ'.str_pad((string) $project->id, 5, '0', STR_PAD_LEFT)]);
            }
        }
    }

    /** A paid renewal order extends the existing subscription/service instead of creating a new service. */
    private function applyRenewal(Order $order, int $subscriptionId): void
    {
        app(SubscriptionService::class)->applyRenewalPayment($order, $subscriptionId);

        // Nothing to provision for a renewal: walk the order to Active through valid transitions.
        foreach ([S::PROVISIONING, S::ACTIVE] as $to) {
            $this->transition($order->fresh(), $to, null, 'Renewal applied.');
        }
    }

    public function cancel(Order $order, ?User $actor, string $reason): Order
    {
        if ($order->paid_at) {
            throw new OrderException('A paid order cannot be cancelled directly. Request a refund instead.');
        }

        $order = $this->transition($order, S::CANCELLED, $actor, 'Cancelled: '.$reason);
        $order->update(['cancelled_at' => now(), 'cancel_reason' => $reason]);

        if ($order->wallet_applied_minor > 0) {
            $this->wallets->credit($order->user, $order->wallet_applied_minor, 'refund', "order:{$order->id}:wallet-release", "Wallet funds released from cancelled order {$order->number}");
            $this->event($order, 'payment', null, null, 'Reserved wallet balance returned.', $actor?->id);
        }

        return $order->fresh();
    }

    public function requestRefund(Order $order, ?User $actor, string $reason): Order
    {
        return $this->transition($order, S::REFUND_PENDING, $actor, 'Refund requested: '.$reason);
    }

    /** Admin confirms the refund. Gateway refunds are executed outside the app until a verified refund API integration exists. */
    public function completeRefund(Order $order, User $actor, bool $toWallet): Order
    {
        $order = $this->transition($order, S::REFUNDED, $actor, $toWallet ? 'Refunded to wallet.' : 'Refunded at payment gateway (recorded manually).');

        if ($toWallet) {
            $this->wallets->credit($order->user, $order->total_minor, 'refund', "order:{$order->id}:refund", "Refund for order {$order->number}", $actor);
        }

        if ($invoice = $order->invoices()->where('type', 'invoice')->first()) {
            $this->invoices->creditNoteFor($invoice);
        }

        $order->services()->update(['status' => 'cancelled']);

        return $order;
    }
}
