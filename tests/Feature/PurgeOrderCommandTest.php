<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Server;
use App\Models\Service;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class PurgeOrderCommandTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function deliveredOrder(): Service
    {
        $user = $this->customer();
        app(WalletService::class)->credit($user, 5_000_000, 'admin_adjustment', 'adjust:'.uniqid(), 'Test funds');
        $this->approve('hermes-cli-launch', 1000);
        $this->placeOrder($user, 'hermes-cli-launch', 'tok-'.uniqid(), ['use_wallet' => 1]);
        $service = Service::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->actingAs($this->staff('admin'))->post(route('admin.services.setup', $service), ['ip_address' => '203.0.113.150', 'ssh_password' => 'Pw-PURGE-1'])->assertSessionHas('success');
        $this->post(route('admin.services.delivered', $service))->assertSessionHas('success');

        return $service->fresh();
    }

    public function test_dry_run_changes_nothing_and_a_wrong_confirmation_deletes_nothing(): void
    {
        $service = $this->deliveredOrder();
        $number = $service->order->number;

        $this->artisan('orders:purge', ['number' => $number, '--dry-run' => true])->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->artisan('orders:purge', ['number' => $number])->expectsQuestion('Type the order number to permanently delete it', 'nope')->assertFailed();

        $this->assertDatabaseHas('orders', ['number' => $number]);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_purge_deletes_the_order_and_everything_linked_and_frees_the_server(): void
    {
        $service = $this->deliveredOrder();
        $order = $service->order;
        $serverId = $service->server_id;
        $this->assertGreaterThan(0, Invoice::where('order_id', $order->id)->count());

        $this->artisan('orders:purge', ['number' => $order->number])
            ->expectsQuestion('Type the order number to permanently delete it', $order->number)
            ->expectsOutputToContain('was deleted')->assertSuccessful();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertDatabaseMissing('subscriptions', ['service_id' => $service->id]);
        $this->assertSame(0, Invoice::where('order_id', $order->id)->count());
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);

        $server = Server::findOrFail($serverId);
        $this->assertSame('available', $server->status);
        $this->assertNull($server->assigned_order_id);
        $this->assertNull($server->assigned_user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.purged']);

        // The customer account itself is untouched.
        $this->assertDatabaseHas('users', ['id' => $order->user_id]);
    }

    public function test_orders_paid_with_a_live_payment_are_refused(): void
    {
        $service = $this->deliveredOrder();
        $order = $service->order;
        PaymentAttempt::create(['order_id' => $order->id, 'gateway' => 'razorpay', 'mode' => 'live', 'amount_minor' => 1000, 'status' => 'verified', 'gateway_order_id' => 'order_LIVE1']);

        $this->artisan('orders:purge', ['number' => $order->number])->expectsOutputToContain('LIVE')->assertFailed();
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertSame(1, Order::whereKey($order->id)->count());
    }
}
