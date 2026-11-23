<?php

namespace App\Services\Payments;

use App\Models\Order;

interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    /** Enabled by admin AND fully usable (configured + integration verified). */
    public function isAvailable(): bool;

    public function mode(): string;

    /** Create a server-side gateway order for $amountMinor; returns the gateway's order id. */
    public function createOrder(Order $order, int $amountMinor): string;

    /** Public data the browser needs to open the hosted checkout (never secrets). */
    public function checkoutPayload(string $gatewayOrderId, Order $order, int $amountMinor): array;

    /** Verify the signed checkout response. A browser redirect alone is never proof of payment. */
    public function verifyCheckout(array $payload): bool;

    public function verifyWebhook(string $rawBody, ?string $signature): bool;

    /**
     * Normalise a webhook body.
     *
     * @return array{type: string, gateway_order_id: ?string, payment_id: ?string, amount_minor: ?int, success: bool, failed: bool, reason: ?string}|null
     */
    public function parseWebhook(array $payload): ?array;

    /** Captured payments for a gateway order, for reconciliation: [['id' => string, 'amount_minor' => int]]. */
    public function capturedPayments(string $gatewayOrderId): array;
}
