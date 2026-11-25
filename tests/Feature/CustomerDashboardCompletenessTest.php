<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestone 10: every customer dashboard section renders, uses only the customer's own data, and states limits truthfully. */
class CustomerDashboardCompletenessTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
    }

    public function test_every_dashboard_section_renders_for_a_customer_with_data(): void
    {
        $plan = $this->approve('hermes-cli-launch', 1000);
        $customer = $this->customer();
        app(WalletService::class)->credit($customer, 5000000, 'topup', 'seed-'.$customer->id);
        $this->actingAs($customer, 'web');
        $this->post('/cart', ['plan' => $plan->slug, 'billing_interval' => 'monthly']);
        $this->withSession(['checkout_token' => 'tok-dash'])->post('/checkout', ['accept_terms' => 1, 'use_wallet' => 1]);
        $sub = Subscription::where('user_id', $customer->id)->firstOrFail();

        foreach (['/dashboard', '/dashboard/services', '/dashboard/orders', '/dashboard/projects', '/dashboard/requests', '/dashboard/subscriptions',
            '/dashboard/subscriptions/'.$sub->id, '/dashboard/invoices', '/dashboard/payments', '/dashboard/wallet', '/dashboard/tickets',
            '/dashboard/tickets/new', '/dashboard/profile', '/dashboard/services/'.$sub->service_id, '/cart', '/docs'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/dashboard')->assertSee('Payments')->assertSee('Subscriptions &amp; renewals', false)->assertDontSee('Staff portal');
        $this->get('/dashboard/payments')->assertSee('Paid from wallet');
    }

    public function test_payments_and_records_are_isolated_between_customers(): void
    {
        $this->approve('hermes-cli-launch');
        $owner = $this->customer();
        $order = $this->placeOrder($owner, 'hermes-cli-launch', 'tok-own');
        PaymentAttempt::create(['order_id' => $order->id, 'gateway' => 'razorpay', 'mode' => 'test', 'gateway_order_id' => 'order_OWN', 'amount_minor' => 118000, 'status' => 'failed']);
        $invoice = Invoice::create(['number' => 'ISO1', 'type' => 'invoice', 'order_id' => $order->id, 'user_id' => $owner->id, 'status' => 'unpaid', 'subtotal_minor' => 1, 'tax_minor' => 0, 'total_minor' => 1, 'issued_at' => now()]);

        $this->actingAs($owner, 'web')->get('/dashboard/payments')->assertOk()->assertSee($order->number);

        $other = $this->customer();
        $this->actingAs($other, 'web')->get('/dashboard/payments')->assertOk()->assertDontSee($order->number);
        $this->get('/dashboard/orders/'.$order->id)->assertForbidden();
        $this->get('/dashboard/invoices/'.$invoice->id.'/pdf')->assertForbidden();
    }

    public function test_staff_accounts_see_a_link_to_the_staff_portal_not_the_admin_area(): void
    {
        $staff = $this->staff('billing-staff');
        $this->actingAs($staff, 'web')->get('/dashboard')->assertOk()->assertSee('/staff/developerjet/login')->assertSee('Staff portal');
    }

    public function test_service_panel_does_not_present_usage_metrics_as_real(): void
    {
        $plan = $this->approve('hermes-cli-launch', 1000);
        $customer = $this->customer();
        app(WalletService::class)->credit($customer, 5000000, 'topup', 'seed-'.$customer->id);
        $this->actingAs($customer, 'web');
        $this->post('/cart', ['plan' => $plan->slug, 'billing_interval' => 'monthly']);
        $this->withSession(['checkout_token' => 'tok-m'])->post('/checkout', ['accept_terms' => 1, 'use_wallet' => 1]);
        $service = $customer->services()->firstOrFail();

        $this->get('/dashboard/services/'.$service->id)->assertOk()->assertDontSee('CPU usage: ', false)->assertDontSee('Usage metrics'); // no invented numbers, no empty placeholder rows
    }
}
