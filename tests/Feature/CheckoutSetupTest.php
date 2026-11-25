<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\WalletService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Checkout setup: app address (own domain or DeveloperJet address set by staff) and the optional AI key. */
class CheckoutSetupTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const KEY = 'sk-test-THIS-IS-A-FAKE-KEY-123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function paidOrder(string $slug, array $setup): Service
    {
        $user = $this->customer();
        app(WalletService::class)->credit($user, 5_000_000, 'admin_adjustment', 'adjust:'.uniqid(), 'Test funds');
        $plan = $this->approve($slug, 1000);
        $this->placeOrder($user, $slug, 'tok-'.uniqid(), ['use_wallet' => 1, 'setup' => [$plan->id => $setup]]);

        return Service::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_checkout_asks_only_web_apps_for_an_address_and_never_for_an_ai_key(): void
    {
        $user = $this->customer();
        $this->approve('hermes-workspace-launch');
        $this->actingAs($user)->post('/cart', ['plan' => 'hermes-workspace-launch', 'billing_interval' => 'monthly']);
        $this->get('/checkout')->assertOk()->assertSee('Setup details')->assertSee('Use a DeveloperJet address')
            ->assertSee('Use my own domain')->assertDontSee('AI provider key');

        $other = $this->customer();
        $this->approve('hermes-cli-launch');
        $this->actingAs($other)->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);
        $this->get('/checkout')->assertOk()->assertDontSee('AI provider key')->assertDontSee('Use my own domain')->assertDontSee('Setup details');
    }

    public function test_own_domain_reaches_the_service_and_a_posted_ai_key_is_ignored(): void
    {
        $service = $this->paidOrder('hermes-workspace-launch', [
            'domain_mode' => 'own', 'domain' => 'Workspace.Example.com', 'ai_provider' => 'openai', 'ai_api_key' => self::KEY,
        ]);

        $this->assertSame('workspace.example.com', $service->custom_domain);
        $this->assertFalse($service->domain_verified);
        $this->assertNotEmpty($service->config['domain_token']);
        $this->assertSame('own', $service->config['domain_mode']);
        $this->assertFalse($service->aiConfigured(), 'Checkout no longer takes AI keys; the customer adds one on the server after delivery.');
        $this->assertNull($service->appDomain(), 'An unverified customer domain is never used by the installer.');

        // The key no longer sits on the order item, and is never stored in plain text anywhere.
        $item = OrderItem::where('order_id', $service->order_id)->whereNotNull('plan_id')->firstOrFail();
        $this->assertArrayNotHasKey('ai_key', $item->config['setup']);
        $this->assertStringNotContainsString(self::KEY, (string) $item->getRawOriginal('config'));
        $this->assertDatabaseCount('customer_api_keys', 0);
        $this->assertDatabaseMissing('audit_logs', ['meta' => self::KEY]);
    }

    public function test_default_address_is_set_by_staff_and_used_by_the_installer(): void
    {
        Setting::set('n8n.resale_verified', '1'); // n8n sales are gated until the licence is verified
        $service = $this->paidOrder('n8n-launch', ['domain_mode' => 'default']);
        $this->assertSame('default', $service->config['domain_mode']);
        $this->assertNull($service->custom_domain);
        $this->assertNull($service->appDomain());

        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.orders.show', $service->order_id))->assertOk()
            ->assertSee('VPS IP address')->assertSee('App address (subdomain)');

        $this->post(route('admin.services.domain', $service), ['assigned_domain' => 'http://bad/'])->assertSessionHasErrors('assigned_domain');
        $this->post(route('admin.services.domain', $service), ['assigned_domain' => 'Client1.DeveloperJet.com'])->assertSessionHas('success');
        $this->assertSame('client1.developerjet.com', $service->fresh()->appDomain());
        $this->assertDatabaseHas('audit_logs', ['action' => 'service.domain_assigned']);

        // The same address can not be given to a second service.
        $second = Service::create(['user_id' => $service->user_id, 'plan_id' => $service->plan_id, 'label' => 'Second n8n', 'kind' => 'n8n', 'status' => 'pending_provisioning']);
        $this->actingAs($admin)->post(route('admin.services.domain', $second), ['assigned_domain' => 'client1.developerjet.com'])->assertSessionHasErrors('assigned_domain');

        // Customer sees the address; staff without servers.assign can not set one.
        $this->actingAs($service->user, 'web')->get(route('dashboard.services.show', $service))->assertOk()->assertSee('client1.developerjet.com');
        $this->actingAs($this->staff('billing-staff'))->post(route('admin.services.domain', $service), ['assigned_domain' => 'x1.developerjet.com'])->assertForbidden();
    }

    public function test_before_delivery_the_customer_is_told_where_the_key_command_will_appear(): void
    {
        $service = $this->paidOrder('hermes-cli-launch', []);
        $this->assertFalse($service->aiConfigured());
        $this->actingAs($service->user, 'web')->get(route('dashboard.services.show', $service))->assertOk()
            ->assertSee('Add your AI API key')->assertSee('Once your server is delivered')->assertDontSee('One step left');
    }

    public function test_invalid_setup_is_rejected_and_the_key_is_never_flashed_to_the_session(): void
    {
        $user = $this->customer();
        $plan = $this->approve('hermes-workspace-launch');
        $this->actingAs($user)->post('/cart', ['plan' => 'hermes-workspace-launch', 'billing_interval' => 'monthly']);
        $response = $this->withSession(['checkout_token' => 'tok-bad'])->post('/checkout', ['accept_terms' => 1, 'setup' => [$plan->id => [
            'domain_mode' => 'own', 'domain' => 'not a domain', 'ai_provider' => 'openai', 'ai_api_key' => self::KEY,
        ]]]);

        $response->assertSessionHasErrors('setup.'.$plan->id.'.domain');
        $this->assertStringNotContainsString(self::KEY, json_encode(session()->all()));
        $this->assertSame(0, Order::where('user_id', $user->id)->count());
    }
}
