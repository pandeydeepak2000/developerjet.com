@extends('layouts.dashboard')

@section('title', 'Order '.$order->number)
@section('heading', 'Order '.$order->number)
@section('subheading', 'Placed '.$order->created_at->format('d M Y, H:i'))

@section('dash')
    <div class="panel">
        <div class="panel-head"><h2>Status</h2><x-status-badge :status="$order->status" /></div>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Item</th><th>Billing</th><th>Total</th></tr></thead>
            <tbody>
            @foreach ($order->items as $item)
                <tr><td>{{ $item->name }}</td><td>{{ \App\Support\BillingCycle::label($item->billing_interval) }}</td><td>{{ \App\Support\Money::inr($item->total_minor) }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
        <dl class="kv">
            <div><dt>Subtotal</dt><dd>{{ \App\Support\Money::inr($order->subtotal_minor) }}</dd></div>
            <div><dt>Discount</dt><dd>− {{ \App\Support\Money::inr($order->discount_minor) }}</dd></div>
            <div><dt>Tax</dt><dd>{{ \App\Support\Money::inr($order->tax_minor) }}</dd></div>
            @if ($order->wallet_applied_minor)<div><dt>Wallet applied</dt><dd>− {{ \App\Support\Money::inr($order->wallet_applied_minor) }}</dd></div>@endif
            <div><dt><strong>Total</strong></dt><dd><strong>{{ \App\Support\Money::inr($order->total_minor) }}</strong></dd></div>
        </dl>
    </div>

    @if ($order->status === 'pending_payment')
        <div class="panel">
            <h2>Payment</h2>
            @if ($checkout)
                <p>Pay <strong>{{ \App\Support\Money::inr($order->payableMinor()) }}</strong> securely. Your order is confirmed only after we verify the payment on our servers.</p>
                <form id="verify-form" method="POST" action="{{ route('dashboard.orders.verify', [$order, $checkout['gateway']]) }}">
                    @csrf
                    <input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_signature">
                </form>
                <button class="auth-submit" type="button" id="pay-button"@if ($autoOpen) data-auto-open @endif>Pay {{ \App\Support\Money::inr($order->payableMinor()) }}</button>
                <p class="muted small" id="pay-status" aria-live="polite">@if ($autoOpen)Opening the secure payment window…@endif</p>
                <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                <script>
                    (function () {
                        var button = document.getElementById('pay-button');
                        var status = document.getElementById('pay-status');
                        function openCheckout() {
                            if (typeof Razorpay === 'undefined') { status.textContent = 'The payment window could not load. Check your connection and press Pay.'; return; }
                            var options = @json($checkout['payload']);
                            options.handler = function (r) {
                                var f = document.getElementById('verify-form');
                                f.razorpay_order_id.value = r.razorpay_order_id;
                                f.razorpay_payment_id.value = r.razorpay_payment_id;
                                f.razorpay_signature.value = r.razorpay_signature;
                                status.textContent = 'Payment received. Confirming it with our server…';
                                f.submit();
                            };
                            options.modal = Object.assign({}, options.modal || {}, {
                                ondismiss: function () { status.textContent = 'Payment window closed. Your order is saved — press Pay whenever you are ready.'; }
                            });
                            new Razorpay(options).open();
                        }
                        button.addEventListener('click', openCheckout);
                        // Coming straight from checkout: open the payment window without an extra click.
                        if (button.hasAttribute('data-auto-open')) {
                            // Drop ?pay=1 so a reload or bookmark does not open the window again.
                            try { var u = new URL(window.location.href); u.searchParams.delete('pay'); window.history.replaceState(null, '', u.toString()); } catch (e) {}
                            window.addEventListener('load', openCheckout);
                        }
                    })();
                </script>
            @elseif ($gateways)
                <p class="muted">Choose how to pay:</p>
                <div class="btn-row">
                    @foreach ($gateways as $key => $gateway)
                        <a class="button-primary" href="{{ route('dashboard.orders.show', ['order' => $order, 'gateway' => $key]) }}">{{ $gateway->label() }}</a>
                    @endforeach
                </div>
            @else
                <x-empty-state title="Online payment unavailable" text="No payment method is enabled yet. Contact Sales or top up your wallet to complete this order." action="Contact Sales" :action-url="route('contact')" />
            @endif
            <form method="POST" action="{{ route('dashboard.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?')" class="inline-form">
                @csrf @method('DELETE')
                <button class="link-danger" type="submit">Cancel order</button>
            </form>
        </div>
    @endif

    <div class="panel">
        <h2>Timeline</h2>
        <ol class="timeline">
            @foreach ($events as $event)
                <li><strong>{{ $event->to_status ? \App\Support\OrderStatus::label($event->to_status) : ucfirst($event->type) }}</strong><span>{{ $event->message }}</span><time>{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d M Y, H:i') }}</time></li>
            @endforeach
        </ol>
    </div>

    @if ($order->invoices->isNotEmpty())
        <div class="panel"><h2>Invoices</h2>
            @foreach ($order->invoices as $invoice)
                <a class="button-primary" href="{{ route('dashboard.invoices.download', $invoice) }}">Download {{ $invoice->number }} (PDF)</a>
            @endforeach
        </div>
    @endif
@endsection
