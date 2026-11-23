<?php

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Support\Setting;
use Database\Seeders\AiAccessSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DocsSeeder;
use Database\Seeders\InfrastructureSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\ServicesCatalogSeeder;
use Illuminate\Support\Facades\Http;

trait BuildsFixtures
{
    protected array $razorpayPayments = [];

    protected int $serverCounter = 0;

    protected function bootFixtures(): void
    {
        Setting::flush();
        $this->seed(CatalogSeeder::class);
        $this->seed(ServicesCatalogSeeder::class);
        $this->seed(RbacSeeder::class);
        $this->seed(DocsSeeder::class);
        $this->seed(InfrastructureSeeder::class);
        $this->seed(AiAccessSeeder::class);
    }

    protected function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    protected function staff(string $roleSlug = 'super-admin', array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->forceFill(['role' => User::ROLE_STAFF, 'role_id' => Role::where('slug', $roleSlug)->value('id'), 'is_active' => true])->save();

        return $user;
    }

    /** An owner-approved plan so it can really be purchased in tests. */
    protected function approve(string $slug, int $monthlyRupees = 1000): Plan
    {
        $plan = Plan::where('slug', $slug)->firstOrFail();
        $plan->update(['price_status' => 'approved', 'is_purchasable' => true]);
        $plan->prices()->updateOrCreate(['billing_interval' => 'monthly'], ['amount_minor' => $monthlyRupees * 100, 'is_active' => true]);
        $plan->prices()->updateOrCreate(['billing_interval' => 'yearly'], ['amount_minor' => $monthlyRupees * 1000, 'is_active' => true]);

        return $plan->fresh();
    }

    protected function configureRazorpay(): void
    {
        Setting::set('gateway.razorpay.enabled', '1');
        Setting::set('gateway.razorpay.key_id', 'rzp_test_key');
        Setting::set('gateway.razorpay.key_secret', 'rzp_secret', true);
        Setting::set('gateway.razorpay.webhook_secret', 'whsec_test', true);
        Http::fake(fn ($request) => str_contains($request->url(), '/payments')
            ? Http::response(['items' => $this->razorpayPayments ?? []])
            : Http::response(['id' => 'order_TEST1']));
    }

    /** Add a plan to the user's cart and place an order through the real checkout endpoints. */
    protected function placeOrder(User $user, string $slug = 'hermes-cli-launch', string $token = 'tok-1', array $extra = []): Order
    {
        $this->actingAs($user);
        $this->post('/cart', ['plan' => $slug, 'billing_interval' => 'monthly']);
        $this->withSession(['checkout_token' => $token])->post('/checkout', ['accept_terms' => 1] + $extra);

        return Order::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    protected function server(array $attrs = []): Server
    {
        return Server::create($attrs + [
            'label' => 'vps-1', 'ip_address' => '10.0.'.intdiv(++$this->serverCounter, 250).'.'.($this->serverCounter % 250 + 1), 'cpu_capacity' => 8, 'ram_capacity_gb' => 16,
            'storage_capacity_gb' => 320, 'role' => 'customer_vps', 'status' => 'available',
        ]);
    }

    protected function signedCheckout(string $gatewayOrderId, string $paymentId = 'pay_TEST1'): array
    {
        return [
            'razorpay_order_id' => $gatewayOrderId, 'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => hash_hmac('sha256', $gatewayOrderId.'|'.$paymentId, 'rzp_secret'),
        ];
    }

    protected function webhookBody(string $event, string $gatewayOrderId, string $paymentId, int $amount, string $status = 'captured'): string
    {
        return json_encode(['event' => $event, 'payload' => ['payment' => ['entity' => ['id' => $paymentId, 'order_id' => $gatewayOrderId, 'amount' => $amount, 'status' => $status, 'error_description' => 'declined']]]]);
    }

    protected function postWebhook(string $body, ?string $eventId = 'evt_1', ?string $signature = null)
    {
        $signature ??= hash_hmac('sha256', $body, 'whsec_test');

        return $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature, 'HTTP_X-Razorpay-Event-Id' => $eventId, 'CONTENT_TYPE' => 'application/json',
        ], $body);
    }
}
