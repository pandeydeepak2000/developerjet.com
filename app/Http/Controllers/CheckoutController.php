<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Services\Payments\GatewayManager;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Checkout is gated by `auth` + `verified`. Placing an order only creates an unpaid order; "Paid" is reached solely via
 * verified payment (see PaymentService).
 */
class CheckoutController extends Controller
{
    public function show(CartService $carts, OrderService $orders, GatewayManager $gateways, WalletService $wallets, Request $request): View|RedirectResponse
    {
        $summary = $carts->summary($carts->current());

        if (! $summary['lines']) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // One token per checkout page load: a double-click or resubmit maps to the same order.
        $request->session()->put('checkout_token', Str::uuid()->toString());

        return view('pages.checkout', [
            'summary' => $summary,
            'pricing' => $summary['unavailable'] === 0 ? $orders->computeLines($summary) : null,
            'gateways' => $gateways->available(),
            'wallet' => $wallets->walletFor($request->user()),
            'approved' => collect($summary['lines'])->every(fn ($l) => $l['plan']->isApproved()),
        ]);
    }

    public function store(Request $request, CartService $carts, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'billing_name' => ['nullable', 'string', 'max:120'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9A-Z]{15}$/'],
            'use_wallet' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', 'string', 'in:razorpay,ptsure'],
            'accept_terms' => ['accepted'],
        ], ['accept_terms.accepted' => 'Please accept the Terms and Refund Policy to continue.']);

        $token = $request->session()->get('checkout_token');
        abort_unless($token, 419);

        $cart = $carts->current();
        $summary = $carts->summary($cart);

        try {
            $order = $orders->place($request->user(), $summary, [
                'billing_name' => $data['billing_name'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'gstin' => $data['gstin'] ?? null,
            ], $token, $request->boolean('use_wallet'));
        } catch (OrderException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $cart?->items()->delete();
        $request->session()->forget('checkout_token');

        return redirect()->route('dashboard.orders.show', $order)
            ->with('success', 'Order placed. Complete payment to start provisioning.')
            ->with('preferred_gateway', $data['payment_method'] ?? null);
    }
}
