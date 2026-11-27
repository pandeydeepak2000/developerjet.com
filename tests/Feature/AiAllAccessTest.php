<?php

namespace Tests\Feature;

use App\Models\DocArticle;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\WalletService;
use Database\Seeders\AiAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * AI All-Access (owner decision 2026-11-27): one API key for many AI models with a monthly usage credit.
 * Four approved plans; paying creates an ai_access service; staff deliver the key; the customer sees it in the panel.
 */
class AiAllAccessTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const KEY = 'mg-test-FAKE-KEY-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_four_approved_plans_with_owner_prices_and_yearly_twelve_times_monthly(): void
    {
        $plans = Plan::where('kind', 'ai_access')->with('prices')->orderBy('sort_order')->get();

        $this->assertSame(['AI Starter', 'AI Pro', 'AI Team', 'AI Business'], $plans->pluck('name')->all());
        $this->assertSame([99900, 249900, 599900, 1499900], $plans->map(fn ($p) => $p->priceFor('monthly'))->all());
        foreach ($plans as $plan) {
            $this->assertSame('approved', $plan->price_status);
            $this->assertTrue($plan->canBePurchased());
            $this->assertFalse($plan->isComingSoon());
            $this->assertSame($plan->priceFor('monthly') * 12, $plan->priceFor('yearly'));
            $this->assertArrayHasKey('Monthly usage credit', $plan->comparison);
        }
        $this->assertSame('$20', $plans[1]->comparison['Monthly usage credit']);
    }

    public function test_seeder_upgrades_only_untouched_drafts_and_never_overwrites_admin_edits(): void
    {
        Plan::where('slug', 'ai-access-team')->update(['name' => 'Renamed by admin']);
        $this->seed(AiAccessSeeder::class);

        $this->assertSame('Renamed by admin', Plan::where('slug', 'ai-access-team')->value('name'));
        $this->assertSame(4, Plan::where('kind', 'ai_access')->count());
        $this->assertSame(2, Plan::where('slug', 'ai-access-team')->first()->prices()->count(), 'Re-running adds no duplicate prices.');
    }

    public function test_page_shows_buyable_plans_models_and_tools_and_no_coming_soon(): void
    {
        $this->get('/ai-all-access')->assertOk()
            ->assertSee('One API key for every top AI model.')
            ->assertSee('₹999')->assertSee('₹14,999')->assertSee('AI Pro')
            ->assertSee('Claude Code')->assertSee('DeepSeek')->assertSee('Monthly usage credit')
            ->assertDontSee('coming soon')->assertDontSee('Request early access')->assertDontSee('Pricing to be confirmed');
    }

    public function test_buying_creates_a_service_staff_deliver_the_key_and_the_customer_sees_it(): void
    {
        $user = $this->customer();
        app(WalletService::class)->credit($user, 5_000_000, 'admin_adjustment', 'adjust:aa', 'Test funds');
        $this->placeOrder($user, 'ai-access-pro', 'tok-aa', ['use_wallet' => 1]);

        $service = Service::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('ai_access', $service->kind);
        $this->assertSame('pending_provisioning', $service->status);
        $this->assertNull($service->server_id);

        // Before delivery the customer is told the key is being prepared.
        $this->actingAs($user, 'web')->get(route('dashboard.services.show', $service))->assertOk()
            ->assertSee('We are creating your API key')->assertDontSee(self::KEY);

        // Staff deliver it from the order page.
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.orders.show', $service->order_id))->assertOk()
            ->assertSee('Deliver API access')->assertSee('ModelGo console')->assertDontSee('VPS IP address');
        $this->post(route('admin.services.access', $service), ['base_url' => 'https://api.example-gateway.test/v1'])->assertSessionHasErrors('api_key');
        $this->post(route('admin.services.access', $service), ['api_key' => self::KEY, 'base_url' => 'https://api.example-gateway.test/v1', 'credit' => '$20'])
            ->assertSessionHas('success');

        $service->refresh();
        $this->assertSame('active', $service->status);
        $this->assertSame('active', $service->order->fresh()->status);
        $this->assertSame(self::KEY, $service->ai_api_key);
        $this->assertStringNotContainsString(self::KEY, (string) $service->getRawOriginal('ai_api_key'), 'Encrypted at rest.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'service.ai_access_delivered']);
        $this->assertDatabaseMissing('audit_logs', ['meta' => self::KEY]);

        // Customer panel: base URL, key (masked until Show), setup snippets.
        $page = $this->actingAs($user, 'web')->get(route('dashboard.services.show', $service))->assertOk()
            ->assertSee('Your API access')->assertSee('https://api.example-gateway.test/v1')
            ->assertSee('ANTHROPIC_BASE_URL')->assertSee('OPENAI_BASE_URL')->assertSee('$20');
        $this->assertStringContainsString('type="password" value="'.self::KEY.'"', $page->getContent());

        // Other customers never see it.
        $this->actingAs($this->customer(), 'web')->get(route('dashboard.services.show', $service))->assertForbidden();
    }

    public function test_key_can_be_replaced_and_access_suspended_by_staff_only(): void
    {
        $user = $this->customer();
        app(WalletService::class)->credit($user, 5_000_000, 'admin_adjustment', 'adjust:ab', 'Test funds');
        $this->placeOrder($user, 'ai-access-starter', 'tok-ab', ['use_wallet' => 1]);
        $service = Service::where('user_id', $user->id)->firstOrFail();
        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.services.access', $service), ['api_key' => self::KEY, 'base_url' => 'https://a.example.test/v1'])->assertSessionHas('success');

        // Replace the URL only: the key is kept.
        $this->post(route('admin.services.access', $service), ['base_url' => 'https://b.example.test/v1'])->assertSessionHas('success');
        $this->assertSame(self::KEY, $service->fresh()->ai_api_key);
        $this->assertSame('https://b.example.test/v1', $service->fresh()->config['api_base_url']);

        $this->post(route('admin.services.access-status', $service))->assertSessionHas('success');
        $this->assertSame('suspended', $service->fresh()->status);
        $this->actingAs($user, 'web')->get(route('dashboard.services.show', $service))->assertSee('This service is suspended.')->assertDontSee(self::KEY);

        $this->actingAs($admin)->post(route('admin.services.access-status', $service));
        $this->assertSame('active', $service->fresh()->status);

        $this->actingAs($this->staff('billing-staff'))->post(route('admin.services.access', $service), ['base_url' => 'https://x.example.test'])->assertForbidden();
    }

    public function test_request_access_form_still_records_a_request_without_charging(): void
    {
        $plan = Plan::where('slug', 'ai-access-team')->first();
        $this->get(route('services.request.create', $plan))->assertRedirect(route('login'));

        $user = $this->customer();
        $this->actingAs($user)->post(route('services.request.store', $plan), ['req' => [
            'use_case' => 'Use several models in our support bot.', 'team_size' => '2_10', 'providers' => 'GPT and Claude',
        ]])->assertRedirect();

        $sr = ServiceRequest::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('ai_access', $sr->kind);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_admin_can_edit_and_sell_the_plans(): void
    {
        $plan = Plan::where('slug', 'ai-access-business')->first();
        $admin = $this->staff();

        $this->actingAs($admin)->get(route('admin.plans.edit', $plan))->assertOk()->assertSee('AI All-Access (API key with monthly credit)');
        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'product_category_id' => $plan->product_category_id, 'name' => 'AI Enterprise', 'slug' => $plan->slug,
            'tax_class' => 'standard', 'visibility' => 'public', 'kind' => 'ai_access', 'fulfilment' => 'provisioned',
            'price_status' => 'approved', 'monthly' => 19999, 'is_active' => 1, 'is_purchasable' => 1,
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame('AI Enterprise', $plan->name);
        $this->assertTrue($plan->canBePurchased());
    }

    public function test_product_appears_in_nav_home_plans_comparison_and_docs(): void
    {
        $this->get('/')->assertOk()->assertSee('<strong>AI All-Access</strong>', false)
            ->assertSee('One API key for every top AI model')->assertSee(route('ai-all-access'));
        $this->get('/plans')->assertOk()->assertSee('id="cat-ai-all-access"', false)->assertSee('AI Starter');
        $this->get('/hermes-workspace')->assertOk(); // matrix still renders with the product key
        $this->get('/ai-all-access')->assertSee('class="dj-table"', false)->assertSee('Included — monthly usage credit');

        $this->assertTrue(DocArticle::where('slug', 'ai-all-access')->where('is_published', true)->exists());
        $this->get('/docs/ai-all-access')->assertOk()->assertSee('does <strong>not</strong> include ChatGPT Plus', false)->assertSee('ANTHROPIC_BASE_URL');
    }
}
