<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\WalletService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Owner rule: 18% GST on online (Razorpay) payments, no GST when the order is paid fully from the wallet. */
class CheckoutGstTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        config(['payments.gst_percent' => 18]);
        $this->approve('hermes-cli-launch', 1000);
    }

    private function fund($user, int $rupees): void
    {
        app(WalletService::class)->credit($user, $rupees * 100, 'admin_adjustment', 'adjust:'.uniqid(), 'Test funds');
    }

    public function test_checkout_shows_gst_for_razorpay_and_no_gst_for_wallet(): void
    {
        Setting::set('gateway.razorpay.enabled', '1');
        Setting::set('gateway.razorpay.key_id', 'rzp_test_x');
        Setting::set('gateway.razorpay.key_secret', 'secret', true);
        $user = $this->customer();
        $this->fund($user, 5000);
        $this->actingAs($user)->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);

        $this->get('/checkout')->assertOk()
            ->assertSee('Powered by JixPay')
            ->assertSee('+ 18% GST')
            ->assertSee('No GST')
            ->assertSee('GST (18%)')
            ->assertSee('₹1,180')   // online total
            ->assertSee('₹1,000');  // wallet total
    }

    public function test_razorpay_order_carries_18_percent_gst(): void
    {
        $user = $this->customer();
        $order = $this->placeOrder($user, 'hermes-cli-launch', 'tok-online', ['payment_method' => 'razorpay']);

        $this->assertSame(100000, $order->subtotal_minor);
        $this->assertSame(18000, $order->tax_minor);
        $this->assertSame(118000, $order->total_minor);
        $this->assertSame('online', $order->snapshot['payment_route']);
    }

    public function test_wallet_order_has_no_gst_and_is_paid_from_the_wallet(): void
    {
        $user = $this->customer();
        $this->fund($user, 2000);
        $order = $this->placeOrder($user, 'hermes-cli-launch', 'tok-wallet', ['payment_method' => 'wallet']);

        $this->assertSame(0, $order->tax_minor);
        $this->assertSame(100000, $order->total_minor);
        $this->assertSame('wallet', $order->snapshot['payment_route']);
        $this->assertSame(100000, $order->wallet_applied_minor);
        $this->assertNotSame('pending_payment', $order->status);
        $this->assertSame(100000, app(WalletService::class)->walletFor($user)->balance_minor); // 2000 - 1000
    }

    public function test_wallet_that_cannot_cover_the_order_is_treated_as_an_online_payment(): void
    {
        $user = $this->customer();
        $this->fund($user, 300);
        $order = $this->placeOrder($user, 'hermes-cli-launch', 'tok-partial', ['use_wallet' => 1]);

        $this->assertSame(18000, $order->tax_minor);
        $this->assertSame(118000, $order->total_minor);
        $this->assertSame('online', $order->snapshot['payment_route']);
        $this->assertSame(Order::find($order->id)->wallet_applied_minor, 30000);
    }

    public function test_admin_tax_setting_overrides_the_default(): void
    {
        Setting::set('tax.rate.standard', '12');
        $order = $this->placeOrder($this->customer(), 'hermes-cli-launch', 'tok-12', ['payment_method' => 'razorpay']);
        $this->assertSame(12000, $order->tax_minor);
    }
}
