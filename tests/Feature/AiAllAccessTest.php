<?php

namespace Tests\Feature;

use App\Models\DocArticle;
use App\Models\Plan;
use App\Models\ServiceRequest;
use Database\Seeders\AiAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** AI All-Access (BYOK workspace): coming soon, draft plans, request-access only, never sellable. */
class AiAllAccessTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_three_draft_plans_exist_unpriced_and_unpurchasable(): void
    {
        $plans = Plan::where('kind', 'ai_access')->with('prices')->orderBy('sort_order')->get();

        $this->assertSame(['AI Access Starter', 'AI Access Team', 'AI Access Business'], $plans->pluck('name')->all());
        foreach ($plans as $plan) {
            $this->assertSame('pending', $plan->price_status);
            $this->assertCount(0, $plan->prices);
            $this->assertFalse($plan->is_purchasable);
            $this->assertFalse($plan->canBePurchased());
            $this->assertTrue($plan->isComingSoon());
        }
    }

    public function test_seeder_is_create_only(): void
    {
        Plan::where('slug', 'ai-access-team')->update(['name' => 'Renamed by admin']);
        $this->seed(AiAccessSeeder::class);

        $this->assertSame('Renamed by admin', Plan::where('slug', 'ai-access-team')->value('name'));
        $this->assertSame(3, Plan::where('kind', 'ai_access')->count());
    }

    public function test_page_is_honest_about_status_byok_and_subscriptions(): void
    {
        $html = $this->get('/ai-all-access')->assertOk()
            ->assertSee('COMING SOON')
            ->assertSee('not yet available to order')
            ->assertSee('Pricing to be confirmed')
            ->assertSee('bring your own key')
            ->assertSee('Does it include ChatGPT Plus, Claude Pro, Gemini Advanced or Grok?')
            ->assertSee('AI Access Starter')->assertSee('AI Access Team')->assertSee('AI Access Business')
            ->assertSee('Request access')->assertSee('Contact Sales')
            ->getContent();

        $this->assertStringNotContainsString('₹', $html);
        $this->assertStringNotContainsString('class="plan-add-to-cart"', $html);
        $this->assertStringNotContainsString('data-plan-card', $html);
        $this->assertSame(3, substr_count($html, 'class="plan-request"'));
        // Capabilities are labelled planned, never available.
        $this->assertSame(7, substr_count($html, 'class="dj-cap__status">Planned<'));
        $this->assertStringNotContainsString('dj-cap__status">Available', $html);
    }

    public function test_named_providers_are_shown_as_planned_official_api_integrations(): void
    {
        $html = $this->get('/ai-all-access')->assertOk()
            ->assertSeeInOrder(['ChatGPT', 'OpenAI'])->assertSee('Claude')->assertSee('Anthropic')
            ->assertSee('Gemini')->assertSee('Google')->assertSee('Grok')->assertSee('xAI')
            ->assertSee('Choosing a model')
            ->assertSee('are not included and cannot be used in place of an API key')
            ->getContent();

        $this->assertStringNotContainsString('Provider A', $html);
        $this->assertStringNotContainsString('Provider B', $html);
        $this->assertSame(4, substr_count($html, 'class="dj-provider"'));
        // No provider is claimed to be live, and no model quotas or API prices are displayed.
        $this->assertStringNotContainsString('dj-cap__status">Available', $html);
        $this->assertStringNotContainsString('₹', $html);
        $this->assertStringNotContainsString('$', strip_tags(substr($html, strpos($html, 'id="providers"'), 6000)));
    }

    public function test_cannot_be_added_to_cart_even_if_an_approved_price_is_forced(): void
    {
        $plan = Plan::where('slug', 'ai-access-starter')->first();
        $plan->prices()->create(['billing_interval' => 'monthly', 'amount_minor' => 99900, 'currency' => 'INR', 'is_active' => true]);
        $plan->forceFill(['price_status' => 'approved', 'is_purchasable' => true])->save();

        $this->assertFalse($plan->fresh()->canBePurchased());
        $this->post('/cart', ['plan' => 'ai-access-starter', 'billing_interval' => 'monthly']);
        $this->assertDatabaseMissing('cart_items', ['plan_id' => $plan->id]);
        $this->get('/ai-all-access')->assertDontSee('₹999')->assertDontSee('class="plan-add-to-cart"', false);
    }

    public function test_request_access_requires_login_and_records_a_request_without_charging(): void
    {
        $plan = Plan::where('slug', 'ai-access-team')->first();
        $this->get(route('services.request.create', $plan))->assertRedirect(route('login'));

        $user = $this->customer();
        $this->actingAs($user)->get(route('services.request.create', $plan))->assertOk()
            ->assertSee('AI All-Access early-access request')->assertSee('nothing is charged')
            ->assertDontSee('name="files[]"', false);

        $this->actingAs($user)->post(route('services.request.store', $plan), ['req' => [
            'use_case' => 'Compare answers from two providers for research.', 'team_size' => '2_10', 'providers' => 'Two providers',
        ]])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Nothing has been charged'));

        $sr = ServiceRequest::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('ai_access', $sr->kind);
        $this->assertSame('submitted', $sr->status);
        $this->assertNull($sr->quote_minor);
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($user)->get(route('dashboard.requests.show', $sr))->assertOk()->assertSee('We have recorded your request');
    }

    public function test_staff_cannot_quote_an_access_request(): void
    {
        $plan = Plan::where('slug', 'ai-access-starter')->first();
        $user = $this->customer();
        $sr = ServiceRequest::create(['number' => 'QR90001', 'user_id' => $user->id, 'plan_id' => $plan->id, 'kind' => 'ai_access', 'requirements' => ['use_case' => 'x']]);

        $admin = $this->staff();
        $this->actingAs($admin)->get(route('admin.requests.show', $sr))->assertOk()->assertSee('Access request')->assertDontSee('Send quote');
        $this->actingAs($admin)->post(route('admin.requests.quote', $sr), ['quote' => 100])->assertForbidden();
        $this->assertNull($sr->fresh()->quote_minor);
    }

    public function test_admin_can_manage_plans_but_never_make_them_purchasable(): void
    {
        $plan = Plan::where('slug', 'ai-access-business')->first();
        $admin = $this->staff();

        $this->actingAs($admin)->get(route('admin.plans.edit', $plan))->assertOk()->assertSee('AI All-Access (BYOK workspace');

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'product_category_id' => $plan->product_category_id, 'name' => 'AI Access Enterprise', 'slug' => $plan->slug,
            'tax_class' => 'standard', 'visibility' => 'public', 'kind' => 'ai_access', 'fulfilment' => 'provisioned',
            'price_status' => 'approved', 'monthly' => 999, 'is_active' => 1, 'is_purchasable' => 1,
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame('AI Access Enterprise', $plan->name);
        $this->assertFalse($plan->is_purchasable);
        $this->assertFalse($plan->canBePurchased());
    }

    public function test_product_appears_in_nav_home_plans_comparison_and_docs(): void
    {
        $this->get('/')->assertOk()->assertSee('<strong>AI All-Access</strong>', false)
            ->assertSee('AI All-Access: your AI models in one workspace')->assertSee(route('ai-all-access'));
        $this->get('/plans')->assertOk()->assertSee('id="cat-ai-all-access"', false)->assertSee('AI Access Starter');
        $this->get('/hermes-workspace')->assertOk(); // matrix still renders with the new product key
        $this->get('/ai-all-access')->assertSee('class="dj-table"', false)->assertSee('Your own API keys from each provider (BYOK)');

        $this->assertTrue(DocArticle::where('slug', 'ai-all-access')->where('is_published', true)->exists());
        $this->get('/docs/ai-all-access')->assertOk()->assertSee('does <strong>not</strong> include ChatGPT Plus', false);
    }
}
