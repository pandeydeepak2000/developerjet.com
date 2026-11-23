<?php

namespace App\Services\Payments;

class GatewayManager
{
    /** @return array<string, PaymentGateway> */
    public function all(): array
    {
        return [
            'razorpay' => app(RazorpayGateway::class),
            'ptsure' => app(PtSureGateway::class),
        ];
    }

    public function get(string $key): PaymentGateway
    {
        return $this->all()[$key] ?? throw new GatewayUnavailableException('Unknown payment gateway.');
    }

    /** @return array<string, PaymentGateway> */
    public function available(): array
    {
        return array_filter($this->all(), fn (PaymentGateway $g) => $g->isAvailable());
    }
}
