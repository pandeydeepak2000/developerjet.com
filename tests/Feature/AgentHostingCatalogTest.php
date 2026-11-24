<?php

namespace Tests\Feature;

use App\Models\Plan;
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

        foreach (['hermes-cli', 'hermes-workspace', 'n8n', 'openclaw'] as $family) {
            $plans = Plan::active()->whereHas('category', fn ($q) => $q->where('slug', $family))->orderBy('sort_order')->get();
            $this->assertSame(['Maple', 'Aurora', 'Edge', 'Rise', 'Boost', 'Prime', 'Power', 'Ultra', 'Titan', 'Infinity'], $plans->pluck('name')->all(), $family);
        }

        $edge = Plan::where('slug', 'n8n-edge')->with('resourceProfile')->firstOrFail();
        $this->assertSame(139900, $edge->priceFor('monthly'));
        $this->assertSame([4, 8, 100, 'NVMe'], [$edge->resourceProfile->vcpu, $edge->resourceProfile->ram_gb, $edge->resourceProfile->disk_gb, $edge->resourceProfile->disk_type]);
        $this->assertTrue($edge->isApproved());
        $this->assertSame(1500000, Plan::where('slug', 'openclaw-infinity')->firstOrFail()->priceFor('monthly'));

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
        $this->assertSame(10, Plan::whereHas('category', fn ($q) => $q->where('slug', 'n8n'))->where('is_active', true)->count());
    }

    public function test_more_than_three_plans_render_as_an_arrow_carousel_with_deep_link_anchors(): void
    {
        $this->seed(AgentHostingCatalogSeeder::class);

        $this->get('/n8n')->assertOk()
            ->assertSee('data-plan-carousel', false)
            ->assertSee('10 plans')
            ->assertSee('id="plan-n8n-edge"', false)
            ->assertSee('Next plans');
        $this->get('/docker-vps')->assertOk()->assertSee('data-plan-carousel', false)->assertSee('7 plans');
    }

    public function test_three_or_fewer_plans_keep_the_normal_grid(): void
    {
        // Without the new catalogue, OpenClaw still has its 3 placeholder plans.
        $this->get('/openclaw')->assertOk()->assertDontSee('data-plan-carousel', false)->assertSee('dj-plan-grid--3', false);
    }
}
