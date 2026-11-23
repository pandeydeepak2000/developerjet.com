<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Support\Setting;

/**
 * PTSure adapter — SKELETON ONLY.
 *
 * No verified PTSure merchant API documentation, endpoints, signature scheme or credentials are available, and none
 * are invented here. Admin can store configuration (encrypted), but `isAvailable()` stays false until
 * `payments.ptsure_integration_verified` is set after a verified sandbox integration. See docs/02_PROJECT_STATUS.md ("PTSure").
 */
class PtSureGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'ptsure';
    }

    public function label(): string
    {
        return 'PTSure';
    }

    public function mode(): string
    {
        return Setting::get('gateway.ptsure.mode', 'test') === 'live' ? 'live' : 'test';
    }

    public function isAvailable(): bool
    {
        return config('payments.ptsure_integration_verified') === true && Setting::bool('gateway.ptsure.enabled');
    }

    private function unavailable(): never
    {
        throw new GatewayUnavailableException('PTSure integration is pending verified merchant API documentation.');
    }

    public function createOrder(Order $order, int $amountMinor): string
    {
        $this->unavailable();
    }

    public function checkoutPayload(string $gatewayOrderId, Order $order, int $amountMinor): array
    {
        $this->unavailable();
    }

    public function verifyCheckout(array $payload): bool
    {
        return false; // never trust anything until a real verification scheme exists
    }

    public function verifyWebhook(string $rawBody, ?string $signature): bool
    {
        return false;
    }

    public function parseWebhook(array $payload): ?array
    {
        return null;
    }

    public function capturedPayments(string $gatewayOrderId): array
    {
        $this->unavailable();
    }
}
