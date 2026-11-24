<?php

namespace Tests\Feature;

use App\Models\CustomerApiKey;
use App\Models\Service;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Saved AI provider keys: added once on the account, reused by services, never shown in full. */
class CustomerApiKeysTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const KEY = 'sk-proj-FAKEKEY-for-tests-9z8y';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function aiService($user): Service
    {
        $plan = $this->approve('hermes-cli-launch');

        return Service::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'label' => 'Hermes CLI · Mine', 'kind' => 'hermes_cli', 'status' => 'pending_provisioning']);
    }

    public function test_customer_adds_a_key_and_only_the_last_four_characters_are_ever_shown(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web')->get(route('dashboard.keys'))->assertOk()->assertSee('No keys saved yet')->assertSee('OpenRouter');

        $this->post(route('dashboard.keys.store'), ['provider' => 'openai', 'label' => 'Company key', 'secret' => self::KEY])
            ->assertRedirect(route('dashboard.keys'));

        $key = CustomerApiKey::firstOrFail();
        $this->assertSame('9z8y', $key->last4);
        $this->assertSame(self::KEY, $key->secret);
        $this->assertStringNotContainsString(self::KEY, (string) $key->getRawOriginal('secret'));

        $page = $this->get(route('dashboard.keys'))->assertOk()->assertSee('Company key')->assertSee('sk-…9z8y')->getContent();
        $this->assertStringNotContainsString(self::KEY, $page);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.api_key_added']);

        $this->post(route('dashboard.keys.store'), ['provider' => 'openai', 'secret' => 'has spaces in it'])->assertSessionHasErrors('secret');
    }

    public function test_service_can_use_a_saved_key_and_other_customers_keys_are_rejected(): void
    {
        $user = $this->customer();
        $service = $this->aiService($user);
        $key = CustomerApiKey::store($user, 'anthropic', 'sk-ant-FAKE-abcd1234', 'Claude key');
        $foreign = CustomerApiKey::store($this->customer(), 'openai', 'sk-OTHER-zzzz9999');

        $this->actingAs($user, 'web')->get(route('dashboard.services.show', $service))->assertOk()
            ->assertSee('Use a saved key')->assertSee('Claude key');

        $this->post(route('dashboard.services.ai', $service), ['api_key_id' => $foreign->id])->assertNotFound();
        $this->assertFalse($service->fresh()->aiConfigured());

        $this->post(route('dashboard.services.ai', $service), ['api_key_id' => $key->id])->assertSessionHas('success');
        $service->refresh();
        $this->assertSame('anthropic', $service->ai_provider);
        $this->assertSame('sk-ant-FAKE-abcd1234', $service->ai_api_key);
        $this->assertSame($key->id, $service->config['api_key_id']);
        $this->get(route('dashboard.keys'))->assertSee('Used by: Hermes CLI · Mine');
    }

    public function test_adding_a_new_key_on_the_service_page_also_saves_it_to_the_account(): void
    {
        $user = $this->customer();
        $service = $this->aiService($user);
        $this->actingAs($user, 'web')->post(route('dashboard.services.ai', $service), ['ai_provider' => 'google', 'ai_api_key' => 'AIzaFAKE-key-5555'])->assertSessionHas('success');

        $this->assertSame(1, CustomerApiKey::where('user_id', $user->id)->where('provider', 'google')->count());
        $this->assertTrue($service->fresh()->aiConfigured());
    }

    public function test_removing_a_key_unlinks_it_but_services_keep_working(): void
    {
        $user = $this->customer();
        $service = $this->aiService($user);
        $key = CustomerApiKey::store($user, 'openai', self::KEY);
        $key->applyTo($service);

        $this->actingAs($user, 'web')->delete(route('dashboard.keys.destroy', $key))->assertRedirect(route('dashboard.keys'));
        $this->assertDatabaseMissing('customer_api_keys', ['id' => $key->id]);
        $service->refresh();
        $this->assertArrayNotHasKey('api_key_id', $service->config);
        $this->assertTrue($service->aiConfigured());

        $other = CustomerApiKey::store($this->customer(), 'openai', self::KEY);
        $this->delete(route('dashboard.keys.destroy', $other))->assertNotFound();
    }

    public function test_checkout_offers_saved_keys_and_applies_the_chosen_one(): void
    {
        $user = $this->customer();
        $key = CustomerApiKey::store($user, 'openrouter', 'sk-or-FAKE-7777', 'Router');
        app(WalletService::class)->credit($user, 5_000_000, 'admin_adjustment', 'adjust:k', 'Funds');
        $plan = $this->approve('hermes-cli-launch');

        $this->actingAs($user, 'web')->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);
        $this->get('/checkout')->assertOk()->assertSee('Use saved: Router')->assertSee('Add a new key');

        $this->placeOrder($user, 'hermes-cli-launch', 'tok-key', ['use_wallet' => 1, 'setup' => [$plan->id => ['api_key_id' => (string) $key->id]]]);
        $service = Service::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame('openrouter', $service->ai_provider);
        $this->assertSame($key->id, $service->config['api_key_id']);
    }
}
