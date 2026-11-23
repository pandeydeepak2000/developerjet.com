<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\Service;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Support\OrderStatus;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        $this->configureRazorpay();
    }

    private function payableOrder(?string $token = 'tok-1'): array
    {
        $this->approve('hermes-cli-launch', 2000);
        $user = $this->customer();
        $order = $this->placeOrder($user, 'hermes-cli-launch', $token);
        $this->get(route('dashboard.orders.show', ['order' => $order, 'gateway' => 'razorpay']))->assertOk();

        return [$user, $order->fresh(), PaymentAttempt::where('order_id', $order->id)->firstOrFail()];
    }

    public function test_order_creation_snapshots_items_and_creates_unpaid_order(): void
    {
        $this->approve('hermes-cli-launch', 2000);
        $order = $this->placeOrder($this->customer());

        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertSame(200000, $order->total_minor);
        $this->assertNull($order->paid_at);
        $this->assertCount(1, $order->items);
        $this->assertSame('hermes-cli', $order->items->first()->config['category']);
        $this->assertStringStartsWith('DJ', $order->number);
        $this->assertDatabaseCount('services', 0);   // nothing provisioned before payment
        $this->assertDatabaseCount('invoices', 0);   // no invoice for an unpaid order
    }

    public function test_double_submit_with_same_token_creates_one_order(): void
    {
        $this->approve('hermes-cli-launch');
        $user = $this->customer();
        $this->actingAs($user);
        $this->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);

        $session = ['checkout_token' => 'same'];
        $this->withSession($session)->post('/checkout', ['accept_terms' => 1]);
        // Customer re-adds and replays the same token (retry / back button).
        $this->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);
        $this->withSession($session)->post('/checkout', ['accept_terms' => 1]);

        $this->assertSame(1, Order::count());
    }

    public function test_unapproved_illustrative_plan_cannot_be_ordered(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $this->post('/cart', ['plan' => 'hermes-cli-grow', 'billing_interval' => 'monthly']);
        $this->withSession(['checkout_token' => 't'])->post('/checkout', ['accept_terms' => 1])->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->get('/checkout')->assertSee('Pricing pending approval');
    }

    public function test_n8n_is_gated_until_licensing_verified(): void
    {
        $this->approve('n8n-launch');
        $user = $this->customer();
        $this->actingAs($user);
        $this->post('/cart', ['plan' => 'n8n-launch', 'billing_interval' => 'monthly']);
        $this->withSession(['checkout_token' => 't'])->post('/checkout', ['accept_terms' => 1])->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);

        Setting::set('n8n.resale_verified', '1');
        $this->post('/cart', ['plan' => 'n8n-launch', 'billing_interval' => 'monthly']);
        $this->withSession(['checkout_token' => 't2'])->post('/checkout', ['accept_terms' => 1]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_pending_openclaw_and_bundle_cannot_enter_cart(): void
    {
        $this->post('/cart', ['plan' => 'openclaw-starter', 'billing_interval' => 'monthly'])->assertSessionHas('error');
        $this->post('/cart', ['plan' => 'ai-complete-bundle', 'billing_interval' => 'monthly'])->assertSessionHas('error');
    }

    public function test_tax_is_config_driven_and_not_silently_zero(): void
    {
        $this->approve('hermes-cli-launch', 1000);
        $this->actingAs($this->customer());
        $this->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);
        $this->get('/checkout')->assertSee('Not configured');

        Setting::set('tax.rate.standard', '18');
        $this->get('/checkout')->assertSee('₹180')->assertSee('₹1,180');
        $order = $this->placeOrder(auth()->user(), 'hermes-cli-launch', 'tax-tok');
        $this->assertSame(18000, $order->tax_minor);
        $this->assertSame(118000, $order->total_minor);
    }

    public function test_state_machine_rejects_invalid_transitions_and_unverified_paid(): void
    {
        [, $order] = $this->payableOrder();
        $svc = app(OrderService::class);

        foreach ([OrderStatus::ACTIVE, OrderStatus::PROVISIONING, OrderStatus::REFUNDED] as $bad) {
            try {
                $svc->transition($order, $bad);
                $this->fail("transition to {$bad} should be rejected");
            } catch (OrderException) {
                $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);
            }
        }

        $this->expectException(OrderException::class);
        $svc->transition($order, OrderStatus::PAID); // not via verified payment
    }

    public function test_valid_signature_marks_paid_issues_invoice_and_creates_service_once(): void
    {
        [$user, $order, $attempt] = $this->payableOrder();
        $payload = $this->signedCheckout($attempt->gateway_order_id);

        $this->post(route('dashboard.orders.verify', [$order, 'razorpay']), $payload)->assertRedirect()->assertSessionHas('success');
        // Replayed callback (refresh / double click): no second fulfilment.
        $this->post(route('dashboard.orders.verify', [$order, 'razorpay']), $payload);

        $order->refresh();
        $this->assertSame(OrderStatus::AWAITING_PROVISIONING, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(1, Service::count());
        $this->assertSame(1, Invoice::count());
        $this->assertSame('verified', $attempt->fresh()->status);
        $this->assertSame('razorpay', $order->payment_method);
    }

    public function test_invalid_signature_never_marks_paid(): void
    {
        [, $order, $attempt] = $this->payableOrder();

        $this->post(route('dashboard.orders.verify', [$order, 'razorpay']), [
            'razorpay_order_id' => $attempt->gateway_order_id, 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => 'forged',
        ])->assertSessionHas('error');

        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_browser_redirect_alone_is_not_proof_of_payment(): void
    {
        [, $order] = $this->payableOrder();

        $this->post(route('dashboard.orders.verify', [$order, 'razorpay']), [])->assertSessionHas('error');
        $this->get(route('dashboard.orders.show', $order))->assertOk();
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        [, $order, $attempt] = $this->payableOrder();
        $body = $this->webhookBody('payment.captured', $attempt->gateway_order_id, 'pay_W1', $attempt->amount_minor);

        $this->postWebhook($body, 'evt_bad', 'not-a-signature')->assertStatus(400);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_duplicate_webhooks_are_idempotent(): void
    {
        [, $order, $attempt] = $this->payableOrder();
        $body = $this->webhookBody('payment.captured', $attempt->gateway_order_id, 'pay_W1', $attempt->amount_minor);

        $this->postWebhook($body, 'evt_1')->assertOk();
        $this->postWebhook($body, 'evt_1')->assertOk()->assertSee('duplicate');
        // Same payment re-sent under a different event id (e.g. order.paid after payment.captured).
        $this->postWebhook($this->webhookBody('order.paid', $attempt->gateway_order_id, 'pay_W1', $attempt->amount_minor), 'evt_2')->assertOk();

        $this->assertSame(OrderStatus::AWAITING_PROVISIONING, $order->fresh()->status);
        $this->assertSame(1, Service::count());
        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, PaymentEvent::count());
    }

    public function test_webhook_that_fails_mid_settlement_is_not_recorded_so_the_gateway_retry_is_processed(): void
    {
        [, $order, $attempt] = $this->payableOrder();
        $body = $this->webhookBody('payment.captured', $attempt->gateway_order_id, 'pay_W9', $attempt->amount_minor);

        // Simulate a crash during settlement (e.g. database outage) on the first delivery only.
        $calls = 0;
        PaymentAttempt::updating(function () use (&$calls) {
            if ($calls++ === 0) {
                throw new \RuntimeException('simulated failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->postWebhook($body, 'evt_retry');
            $this->fail('Expected the simulated failure.');
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('payment_events', 0);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);

        // Gateway retries the same event: it is processed, exactly once.
        $this->postWebhook($body, 'evt_retry')->assertOk()->assertSee('ok');
        $this->postWebhook($body, 'evt_retry')->assertOk()->assertSee('duplicate');
        $this->assertSame(OrderStatus::AWAITING_PROVISIONING, $order->fresh()->status);
        $this->assertSame(1, Invoice::count());
    }

    public function test_webhook_amount_mismatch_does_not_pay_order(): void
    {
        [, $order, $attempt] = $this->payableOrder();
        $body = $this->webhookBody('payment.captured', $attempt->gateway_order_id, 'pay_W9', $attempt->amount_minor - 100);

        $this->postWebhook($body, 'evt_m')->assertOk();

        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);
        $this->assertSame('amount_mismatch', $attempt->fresh()->status);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_failed_payment_event_is_recorded_and_order_stays_retryable(): void
    {
        [, $order, $attempt] = $this->payableOrder();
        $body = $this->webhookBody('payment.failed', $attempt->gateway_order_id, 'pay_F1', $attempt->amount_minor, 'failed');

        $this->postWebhook($body, 'evt_f')->assertOk();

        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_late_payment_on_cancelled_order_is_flagged_not_fulfilled(): void
    {
        [$user, $order, $attempt] = $this->payableOrder();
        $this->delete(route('dashboard.orders.cancel', $order));
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);

        $this->postWebhook($this->webhookBody('payment.captured', $attempt->gateway_order_id, 'pay_L1', $attempt->amount_minor), 'evt_l')->assertOk();

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, Service::count());
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'is_internal' => true]);
    }

    public function test_reconciliation_settles_captured_payment_missed_by_other_paths(): void
    {
        [, $order, $attempt] = $this->payableOrder();
        $attempt->forceFill(['created_at' => now()->subHour()])->save();
        $this->razorpayPayments = [['id' => 'pay_R1', 'status' => 'captured', 'amount' => $attempt->amount_minor]];

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(OrderStatus::AWAITING_PROVISIONING, $order->fresh()->status);
    }

    public function test_ptsure_cannot_be_used_even_if_enabled(): void
    {
        Setting::set('gateway.ptsure.enabled', '1');
        $this->approve('hermes-cli-launch');
        $user = $this->customer();
        $order = $this->placeOrder($user);

        $this->get(route('dashboard.orders.show', ['order' => $order, 'gateway' => 'ptsure']))->assertOk()->assertDontSee('PTSure');
        $this->call('POST', '/webhooks/ptsure', [], [], [], [], '{}')->assertStatus(400);
    }

    public function test_customer_data_isolation_for_orders_and_invoices(): void
    {
        [$owner, $order, $attempt] = $this->payableOrder();
        $this->post(route('dashboard.orders.verify', [$order, 'razorpay']), $this->signedCheckout($attempt->gateway_order_id));
        $invoice = Invoice::firstOrFail();
        $other = $this->customer();

        $this->actingAs($other)->get(route('dashboard.orders.show', $order))->assertForbidden();
        $this->get(route('dashboard.invoices.download', $invoice))->assertForbidden();
        $this->delete(route('dashboard.orders.cancel', $order))->assertForbidden();
        $this->post(route('dashboard.orders.verify', [$order, 'razorpay']), [])->assertForbidden();
        $this->actingAs($owner)->get(route('dashboard.invoices.download', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_customers_cannot_see_internal_order_notes(): void
    {
        [$owner, $order] = $this->payableOrder();
        $staff = $this->staff('admin');
        $this->actingAs($staff)->post(route('admin.orders.note', $order), ['message' => 'SECRET-INTERNAL-NOTE', 'internal' => 1])->assertRedirect();
        $this->post(route('admin.orders.note', $order), ['message' => 'Hello customer visible', 'internal' => 0]);

        $this->actingAs($owner)->get(route('dashboard.orders.show', $order))->assertSee('Hello customer visible')->assertDontSee('SECRET-INTERNAL-NOTE');
    }

    public function test_cart_survives_login_and_is_preserved_after_failed_order(): void
    {
        $this->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);
        $user = $this->customer(['password' => 'Secret-pass-1234']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret-pass-1234']);
        $this->post('/checkout', ['accept_terms' => 1]); // no checkout token → rejected; nothing lost

        $this->assertSame(1, $user->cart->items()->count());
    }
}
