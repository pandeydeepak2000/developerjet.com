<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\ProductCategory;
use Database\Seeders\AgentHostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class AgentHostingCatalogTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_agent_tiers_match_the_owner_published_names_resources_and_prices(): void
    {
        $this->seed(AgentHostingCatalogSeeder::class);

        $expected = [
            'hermes-cli' => ['Spark', 'Pulse', 'Drive', 'Surge', 'Turbo'],
            'hermes-workspace' => ['Desk', 'Studio', 'Team', 'Agency', 'Office', 'Enterprise'],
            'n8n' => ['Trigger', 'Node', 'Flow', 'Pipeline', 'Engine', 'Orchestra', 'Nexus'],
            'openclaw' => ['Scout', 'Ranger', 'Hunter', 'Guardian', 'Sentinel', 'Commander'],
        ];
        $all = [];
        foreach ($expected as $family => $names) {
            $plans = Plan::active()->whereHas('category', fn ($q) => $q->where('slug', $family))->orderBy('sort_order')->get();
            $this->assertSame($names, $plans->pluck('name')->all(), $family);
            $all = array_merge($all, $names);
        }
        $this->assertSame(count($all), count(array_unique($all)), 'Every product uses its own plan names.');

        // Same VPS tier underneath: n8n "Flow" is the 4 vCPU / 8 GB "Edge" size at the owner's price.
        $flow = Plan::where('slug', 'n8n-edge')->with('resourceProfile')->firstOrFail();
        $this->assertSame('Flow', $flow->name);
        $this->assertSame(139900, $flow->priceFor('monthly'));
        $this->assertSame(139900 * 12, $flow->priceFor('yearly'));
        $this->assertSame([4, 8, 100, 'NVMe'], [$flow->resourceProfile->vcpu, $flow->resourceProfile->ram_gb, $flow->resourceProfile->disk_gb, $flow->resourceProfile->disk_type]);
        $this->assertTrue($flow->isApproved());

        // Workspace never starts below 6 GB RAM; Hermes CLI is not sold on the huge tiers.
        $this->assertSame(6, Plan::where('slug', 'hermes-workspace-aurora')->firstOrFail()->resourceProfile->ram_gb);
        $this->assertNull(Plan::where('slug', 'hermes-workspace-maple')->first());
        $this->assertNull(Plan::where('slug', 'hermes-cli-infinity')->first());

        $docker = Plan::active()->whereHas('category', fn ($q) => $q->where('slug', 'docker-vps'))->orderBy('sort_order')->get();
        $this->assertSame(['Nano', 'Micro', 'Mini', 'Standard', 'Pro', 'Max', 'Mega'], $docker->pluck('name')->all());
        $this->assertSame('unmanaged', $docker->first()->access_model);
        $this->assertSame(42800, $docker->first()->priceFor('monthly'));

        // Placeholder plans that nobody ordered are hidden.
        $this->assertFalse((bool) Plan::where('slug', 'hermes-cli-launch')->value('is_active'));
        $this->assertFalse((bool) Plan::where('slug', 'docker-vps-a')->value('is_active'));
    }

    public function test_seeder_never_overwrites_admin_edits_and_is_idempotent(): void
    {
        $this->seed(AgentHostingCatalogSeeder::class);
        Plan::where('slug', 'n8n-maple')->update(['name' => 'Maple Special']);
        $this->seed(AgentHostingCatalogSeeder::class);

        $this->assertSame('Maple Special', Plan::where('slug', 'n8n-maple')->value('name'));
        $this->assertSame(7, Plan::whereHas('category', fn ($q) => $q->where('slug', 'n8n'))->where('is_active', true)->count());
    }

    public function test_more_than_three_plans_render_as_an_arrow_carousel_with_deep_link_anchors(): void
    {
        $this->seed(AgentHostingCatalogSeeder::class);

        $this->get('/n8n')->assertOk()
            ->assertSee('data-plan-carousel', false)
            ->assertSee('7 plans')
            ->assertSee('data-billing-toggle="annual"', false)
            ->assertSee('₹16,788 billed for 12 months')
            ->assertDontSee('Save ₹0')
            ->assertSee('id="plan-n8n-edge"', false)
            ->assertSee('Next plans');
        $this->get('/docker-vps')->assertOk()->assertSee('data-plan-carousel', false)->assertSee('7 plans');
    }

    public function test_plans_from_an_earlier_run_get_product_names_and_unused_sizes_are_hidden(): void
    {
        // Simulate the first version of this seeder: generic tier names on every family and size.
        $category = ProductCategory::where('slug', 'n8n')->firstOrFail();
        foreach (['maple' => 'Maple', 'ultra' => 'Ultra'] as $tier => $name) {
            Plan::create(['slug' => "n8n-{$tier}", 'name' => $name, 'product_category_id' => $category->id, 'is_active' => true, 'is_purchasable' => true, 'price_status' => 'approved']);
        }

        $this->seed(AgentHostingCatalogSeeder::class);

        $this->assertSame('Trigger', Plan::where('slug', 'n8n-maple')->value('name'));
        $this->assertFalse((bool) Plan::where('slug', 'n8n-ultra')->value('is_active'));
    }

    public function test_ai_complete_bundle_becomes_one_heavy_priced_plan(): void
    {
        $this->seed(AgentHostingCatalogSeeder::class);

        $bundle = Plan::where('slug', 'ai-complete-bundle')->with('resourceProfile')->firstOrFail();
        $this->assertTrue($bundle->isApproved());
        $this->assertTrue($bundle->canBePurchased());
        $this->assertSame([12, 32, 400], [$bundle->resourceProfile->vcpu, $bundle->resourceProfile->ram_gb, $bundle->resourceProfile->disk_gb]);
        $this->assertSame(599900, $bundle->priceFor('monthly'));
        $this->assertSame(599900 * 12, $bundle->priceFor('yearly'));
        $this->assertSame(1, Plan::active()->whereHas('category', fn ($q) => $q->where('slug', 'ai-complete-bundle'))->count());

        $this->get('/ai-complete-bundle')->assertOk()
            ->assertSee('₹5,999')
            ->assertSee('data-billing-toggle="annual"', false)
            ->assertSee('32 GB')
            ->assertDontSee('data-plan-carousel', false);

        // An admin's own price is never overwritten by re-running the seeder.
        $bundle->prices()->where('billing_interval', 'monthly')->update(['amount_minor' => 700000]);
        $this->seed(AgentHostingCatalogSeeder::class);
        $this->assertSame(700000, $bundle->fresh()->priceFor('monthly'));
    }

    public function test_three_or_fewer_plans_keep_the_normal_grid(): void
    {
        // Without the new catalogue, OpenClaw still has its 3 placeholder plans.
        $this->get('/openclaw')->assertOk()->assertDontSee('data-plan-carousel', false)->assertSee('dj-plan-grid--3', false);
    }
}
