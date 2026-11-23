<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Support\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Razorpay adapter using the documented Orders API and HMAC-SHA256 signatures:
 *  - checkout:  hmac_sha256(order_id|payment_id, key_secret)
 *  - webhook:   hmac_sha256(raw_body, webhook_secret)   (header X-Razorpay-Signature, id in X-Razorpay-Event-Id)
 * Credentials come from encrypted settings. Not verified against a live Razorpay account in this repository.
 */
class RazorpayGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'razorpay';
    }

    public function label(): string
    {
        return 'Razorpay (UPI, cards, netbanking)';
    }

    public function mode(): string
    {
        return Setting::get('gateway.razorpay.mode', 'test') === 'live' ? 'live' : 'test';
    }

    private function keyId(): ?string
    {
        return Setting::get('gateway.razorpay.key_id');
    }

    private function secret(): ?string
    {
        return Setting::get('gateway.razorpay.key_secret');
    }

    public function isAvailable(): bool
    {
        return Setting::bool('gateway.razorpay.enabled') && $this->keyId() && $this->secret();
    }

    public function createOrder(Order $order, int $amountMinor): string
    {
        $this->assertAvailable();

        $response = Http::withBasicAuth($this->keyId(), $this->secret())->timeout(15)
            ->post(config('payments.razorpay_api').'/orders', [
                'amount' => $amountMinor,
                'currency' => $order->currency,
                'receipt' => $order->number,
                'notes' => ['order_number' => $order->number],
            ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new GatewayUnavailableException('The payment gateway could not create the payment. Please try again shortly.');
        }

        return $response->json('id');
    }

    public function checkoutPayload(string $gatewayOrderId, Order $order, int $amountMinor): array
    {
        return [
            'key' => $this->keyId(), // public key id only
            'order_id' => $gatewayOrderId,
            'amount' => $amountMinor,
            'currency' => $order->currency,
            'name' => 'DeveloperJet',
            'description' => 'Order '.$order->number,
            'prefill' => ['name' => $order->user->name, 'email' => $order->user->email],
        ];
    }

    public function verifyCheckout(array $payload): bool
    {
        $orderId = $payload['razorpay_order_id'] ?? null;
        $paymentId = $payload['razorpay_payment_id'] ?? null;
        $signature = $payload['razorpay_signature'] ?? null;
        $secret = $this->secret();

        if (! is_string($orderId) || ! is_string($paymentId) || ! is_string($signature) || ! $secret) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $secret), $signature);
    }

    public function verifyWebhook(string $rawBody, ?string $signature): bool
    {
        $secret = Setting::get('gateway.razorpay.webhook_secret');

        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    public function parseWebhook(array $payload): ?array
    {
        $type = $payload['event'] ?? null;
        $payment = $payload['payload']['payment']['entity'] ?? null;

        if (! is_string($type) || ! is_array($payment)) {
            return null;
        }

        return [
            'type' => $type,
            'gateway_order_id' => $payment['order_id'] ?? null,
            'payment_id' => $payment['id'] ?? null,
            'amount_minor' => isset($payment['amount']) ? (int) $payment['amount'] : null,
            'success' => in_array($type, ['payment.captured', 'order.paid'], true) && ($payment['status'] ?? null) === 'captured',
            'failed' => $type === 'payment.failed',
            'reason' => $payment['error_description'] ?? null,
        ];
    }

    public function capturedPayments(string $gatewayOrderId): array
    {
        $this->assertAvailable();

        $response = Http::withBasicAuth($this->keyId(), $this->secret())->timeout(15)
            ->get(config('payments.razorpay_api')."/orders/{$gatewayOrderId}/payments");

        if (! $response->successful()) {
            throw new GatewayUnavailableException('Reconciliation request failed (HTTP '.$response->status().').');
        }

        return collect($response->json('items', []))
            ->filter(fn ($p) => ($p['status'] ?? null) === 'captured')
            ->map(fn ($p) => ['id' => $p['id'], 'amount_minor' => (int) $p['amount']])
            ->values()->all();
    }

    private function assertAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw new GatewayUnavailableException('Razorpay is not enabled or configured.');
        }
    }
}
