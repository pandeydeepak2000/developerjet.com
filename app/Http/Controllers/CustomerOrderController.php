<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\GatewayUnavailableException;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    public function show(Request $request, Order $order, GatewayManager $gateways, PaymentService $payments): View
    {
        $this->authorize('view', $order);
        $order->load('items', 'events', 'invoices');

        $checkout = null;
        $gatewayKey = $request->query('gateway', session('preferred_gateway'));

        if ($order->status === 'pending_payment' && $order->payableMinor() > 0 && $gatewayKey && isset($gateways->available()[$gatewayKey])) {
            try {
                $checkout = ['gateway' => $gatewayKey] + $payments->initiate($order, $gatewayKey);
            } catch (OrderException|GatewayUnavailableException $e) {
                session()->now('error', $e->getMessage());
            }
        }

        return view('dashboard.order-show', [
            'order' => $order,
            'gateways' => $gateways->available(),
            'checkout' => $checkout,
            // Straight after "Place order" with Razorpay chosen, open the payment window immediately (one-time flash),
            // so the customer goes checkout → pay without an extra click. Reloading the page does not re-open it.
            'autoOpen' => $checkout !== null && $request->boolean('pay'),
            // Customers never see internal notes.
            'events' => $order->events->where('is_internal', false),
        ]);
    }

    /** Return from hosted checkout. Verified server-side by signature; the redirect itself proves nothing. */
    public function verify(Request $request, Order $order, string $gateway, PaymentService $payments): RedirectResponse
    {
        $this->authorize('view', $order);

        try {
            $payments->confirmCheckout($order, $gateway, $request->only(['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature']));
        } catch (OrderException|GatewayUnavailableException $e) {
            return redirect()->route('dashboard.orders.show', $order)->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard.orders.show', $order)->with('success', 'Payment verified. Thank you!');
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorize('view', $order);

        try {
            $orders->cancel($order, $request->user(), 'Cancelled by customer');
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard.orders')->with('success', 'Order cancelled.');
    }
}
