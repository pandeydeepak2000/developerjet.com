<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Plan;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_thirteen_ai_plans_are_seeded_in_correct_families(): void
    {
        $this->assertSame(13, Plan::count());

        $counts = ProductCategory::withCount('plans')->pluck('plans_count', 'slug')->all();
        $this->assertSame([
            'hermes-cli' => 3, 'hermes-workspace' => 3, 'n8n' => 3, 'ai-complete-bundle' => 1, 'openclaw' => 3,
        ], $counts);
    }

    public function test_seeder_is_idempotent_and_keeps_admin_changes(): void
    {
        Plan::where('slug', 'n8n-grow')->first()->prices()->where('billing_interval', 'monthly')->update(['amount_minor' => 123400]);

        $this->seed(CatalogSeeder::class);

        $this->assertSame(13, Plan::count());
        $this->assertSame(123400, Plan::where('slug', 'n8n-grow')->first()->priceFor('monthly'));
    }

    public function test_existing_displayed_plans_keep_their_prices_as_illustrative(): void
    {
        $launch = Plan::with('prices')->where('slug', 'hermes-cli-launch')->first();
        $this->assertSame(249900, $launch->priceFor('monthly'));
        $this->assertSame(2499000, $launch->priceFor('yearly'));
        $this->assertSame('illustrative', $launch->price_status);
        $this->assertSame('2 vCPU · 4 GB RAM', $launch->resourceProfile->summary());
        $this->assertSame('displayed', $launch->resourceProfile->status);
    }

    public function test_bundle_and_openclaw_have_no_invented_prices_or_resources(): void
    {
        $pending = Plan::with('prices')->whereHas('category', fn ($q) => $q->whereIn('slug', ['ai-complete-bundle', 'openclaw']))->get();

        $this->assertCount(4, $pending);
        foreach ($pending as $plan) {
            $this->assertSame('pending', $plan->price_status);
            $this->assertCount(0, $plan->prices);
            $this->assertNull($plan->resource_profile_id);
            $this->assertFalse($plan->canBePurchased());
        }

        // n8n displays workflow limits only; no VPS resources were invented for it.
        $this->assertNull(Plan::where('slug', 'n8n-launch')->first()->resource_profile_id);
    }

    public function test_addons_have_no_invented_prices(): void
    {
        $this->assertSame(0, Addon::whereNotNull('price_minor')->count());
        $this->assertGreaterThan(0, Addon::count());
    }

    public function test_plans_page_lists_all_plans_and_marks_pending_pricing(): void
    {
        $response = $this->get('/plans')->assertOk()
            ->assertSee('AI Complete Bundle')
            ->assertSee('OpenClaw Hosting')
            ->assertSee('Pricing to be confirmed');

        $this->assertSame(4, substr_count($response->getContent(), 'plans-price-pending'));
        $this->assertSame(13, substr_count($response->getContent(), '<article class="dj-plan"'));
    }

    public function test_product_pages_render_database_plans_with_cart_buttons(): void
    {
        $this->get('/hermes-cli')->assertOk()->assertSee('₹2,499')->assertSee('Add to cart')->assertSee('data-billing-input', false);

        Plan::where('slug', 'hermes-cli-launch')->update(['is_purchasable' => false]);
        $html = $this->get('/hermes-cli')->getContent();
        $this->assertSame(2, substr_count($html, 'class="plan-add-to-cart"'));
    }

    public function test_admin_gate(): void
    {
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('manage-catalog'));

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->assertTrue(Gate::forUser($admin)->allows('manage-catalog'));
    }
}
