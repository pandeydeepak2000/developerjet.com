<?php

namespace App\Http\Controllers;

use App\Services\Payments\GatewayUnavailableException;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function handle(Request $request, string $gateway, PaymentService $payments): Response
    {
        try {
            [$status, $message] = $payments->handleWebhook(
                $gateway,
                $request->getContent(),
                $request->header('X-Razorpay-Signature'),
                $request->header('X-Razorpay-Event-Id'),
            );
        } catch (GatewayUnavailableException) {
            return response('unknown gateway', 404);
        }

        return response($message, $status);
    }
}
