<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Services\Notifier;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Support\Audit;
use App\Support\Money;
use App\Support\OrderStatus as S;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private GatewayManager $gateways, private OrderService $orders, private Notifier $notifier) {}

    /** Create (or reuse) a server-side gateway order for the remaining payable amount. */
    public function initiate(Order $order, string $gatewayKey): array
    {
        if ($order->status !== S::PENDING_PAYMENT) {
            throw new OrderException('This order is not awaiting payment.');
        }

        $amount = $order->payableMinor();
        if ($amount <= 0) {
            throw new OrderException('Nothing left to pay on this order.');
        }

        $gateway = $this->gateways->get($gatewayKey);
        if (! $gateway->isAvailable()) {
            throw new OrderException('That payment method is not available right now.');
        }

        $attempt = $order->attempts()->where('gateway', $gatewayKey)->where('status', 'created')
            ->where('amount_minor', $amount)->whereNotNull('gateway_order_id')->latest('id')->first();

        if (! $attempt) {
            $gatewayOrderId = $gateway->createOrder($order, $amount);
            $attempt = $order->attempts()->create([
                'gateway' => $gatewayKey, 'mode' => $gateway->mode(), 'amount_minor' => $amount,
                'currency' => $order->currency, 'gateway_order_id' => $gatewayOrderId,
            ]);
        }

        return ['attempt' => $attempt, 'payload' => $gateway->checkoutPayload($attempt->gateway_order_id, $order, $amount)];
    }

    /** Customer returned from the hosted checkout: only a valid server-side signature counts. */
    public function confirmCheckout(Order $order, string $gatewayKey, array $payload): PaymentAttempt
    {
        $gateway = $this->gateways->get($gatewayKey);
        $attempt = $order->attempts()->where('gateway', $gatewayKey)
            ->where('gateway_order_id', $payload['razorpay_order_id'] ?? '')->first();

        if (! $attempt || ! $gateway->verifyCheckout($payload)) {
            Audit::log('payment.checkout_invalid', $order, ['gateway' => $gatewayKey], $order->user_id);
            if ($attempt && $attempt->status === 'created') {
                $attempt->update(['status' => 'failed', 'failure_reason' => 'Signature verification failed']);
            }
            throw new OrderException('We could not verify this payment. If money was debited it will be reconciled automatically; contact support if not.');
        }

        return $this->settle($attempt, (string) $payload['razorpay_payment_id'], $attempt->amount_minor);
    }

    /** Idempotent: repeated calls (callback + webhook + reconciliation) fulfil the order once. */
    public function settle(PaymentAttempt $attempt, string $paymentId, int $amountMinor): PaymentAttempt
    {
        try {
            $result = DB::transaction(function () use ($attempt, $paymentId, $amountMinor) {
                $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'verified') {
                    return [$locked, false];
                }

                if ($amountMinor !== $locked->amount_minor) {
                    $locked->update(['status' => 'amount_mismatch', 'gateway_payment_id' => $paymentId, 'failure_reason' => "Expected {$locked->amount_minor}, received {$amountMinor}"]);

                    return [$locked, 'mismatch'];
                }

                $locked->update(['status' => 'verified', 'gateway_payment_id' => $paymentId, 'failure_reason' => null]);

                return [$locked, true];
            });
        } catch (UniqueConstraintViolationException) {
            // The same gateway payment id was already recorded on another attempt: never double-fulfil.
            return $attempt->fresh();
        }

        [$locked, $outcome] = $result;

        if ($outcome === 'mismatch') {
            $this->orders->event($locked->order, 'payment', null, null, 'Payment amount mismatch — held for manual review.', null, true);
            $this->notifier->admin('payment_failure', "attempt:{$locked->id}:mismatch", "Amount mismatch on {$locked->order->number}: expected ".Money::inr($locked->amount_minor).', gateway reported '.Money::inr($amountMinor).'.');
        } elseif ($outcome === true) {
            $this->orders->markPaid($locked->order, $locked, $locked->gateway);
        }

        return $locked;
    }

    public function fail(PaymentAttempt $attempt, ?string $reason): void
    {
        if ($attempt->status !== 'created') {
            return; // never downgrade a verified/mismatch attempt
        }

        $attempt->update(['status' => 'failed', 'failure_reason' => $reason ? mb_substr($reason, 0, 250) : 'Payment failed']);
        $this->orders->event($attempt->order, 'payment', null, null, 'Payment attempt failed.', null);
        $this->notifier->admin('payment_failure', "attempt:{$attempt->id}", "Payment failed for {$attempt->order->number}.");
    }

    /** @return array{0: int, 1: string} HTTP status + message */
    public function handleWebhook(string $gatewayKey, string $rawBody, ?string $signature, ?string $eventId): array
    {
        $gateway = $this->gateways->get($gatewayKey);

        if (! $gateway->verifyWebhook($rawBody, $signature)) {
            Audit::log('webhook.rejected', null, ['gateway' => $gatewayKey]);

            return [400, 'invalid signature'];
        }

        $payload = json_decode($rawBody, true);
        $event = is_array($payload) ? $gateway->parseWebhook($payload) : null;
        $eventId = $eventId ?: ($event ? hash('sha256', $rawBody) : null);

        if (! $event || ! $eventId) {
            return [200, 'ignored'];
        }

        // The event record and its effect commit together: if settlement fails, the event row is rolled back, the
        // gateway gets a 5xx and its retry is processed instead of being dropped as a "duplicate".
        try {
            return DB::transaction(function () use ($gatewayKey, $eventId, $event) {
                $log = PaymentEvent::create(['gateway' => $gatewayKey, 'event_id' => $eventId, 'event_type' => $event['type'],
                    'payload' => ['order' => $event['gateway_order_id'], 'payment' => $event['payment_id'], 'amount' => $event['amount_minor']]]);

                $attempt = $event['gateway_order_id'] ? PaymentAttempt::where('gateway', $gatewayKey)->where('gateway_order_id', $event['gateway_order_id'])->first() : null;

                if (! $attempt) {
                    $log->update(['outcome' => 'ignored']);

                    return [200, 'unknown order'];
                }

                if ($event['success'] && $event['payment_id'] && $event['amount_minor'] !== null) {
                    $this->settle($attempt, $event['payment_id'], $event['amount_minor']);
                } elseif ($event['failed']) {
                    $this->fail($attempt, $event['reason']);
                }

                $log->update(['outcome' => 'processed']);

                return [200, 'ok'];
            });
        } catch (UniqueConstraintViolationException) {
            return [200, 'duplicate']; // idempotent: already processed
        }
    }

    /** Settle any captured payments the browser/webhook path missed. Returns the number of attempts settled. */
    public function reconcile(int $olderThanMinutes = 10): int
    {
        $settled = 0;

        PaymentAttempt::where('status', 'created')->whereNotNull('gateway_order_id')
            ->where('created_at', '<', now()->subMinutes($olderThanMinutes))->with('order')->each(function (PaymentAttempt $attempt) use (&$settled) {
                $gateway = $this->gateways->get($attempt->gateway);

                if (! $gateway->isAvailable()) {
                    return;
                }

                foreach ($gateway->capturedPayments($attempt->gateway_order_id) as $payment) {
                    $this->settle($attempt, $payment['id'], $payment['amount_minor']);
                    $settled++;
                    break;
                }
            });

        return $settled;
    }
}
