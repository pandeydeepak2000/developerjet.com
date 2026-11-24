<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ProductCategory;
use App\Models\ResourceProfile;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Owner-provided catalogue (2026-11-24).
 *
 *  - AI agent families (Hermes CLI, Hermes Workspace, n8n, OpenClaw) use the 10 "AI Agent VPS" tiers
 *    published on ambitionhost.in/agent-hosting (names, resources and monthly INR prices exactly as shown
 *    there), so the "Setup n8n / Hermes / OpenClaw" links on AmbitionHost land on the same plan here.
 *  - Docker VPS uses a selection of the owner's ambitionhost.in/vps-hosting prices with DeveloperJet names.
 *
 * The owner supplied these prices, so they are stored as approved. Only monthly prices exist; other billing
 * periods stay blank until set in Admin → Products & plans. Plans are created once and never overwritten,
 * so later admin edits survive re-running this seeder. The earlier placeholder plans (Launch/Grow/Scale,
 * Starter/Pro/Business, Docker drafts) are hidden only if nobody has ordered them.
 *
 * Run: php artisan db:seed --class=AgentHostingCatalogSeeder --force
 */
class AgentHostingCatalogSeeder extends Seeder
{
    /** slug => [name, tagline, vCPU, RAM GB, disk GB, disk type, monthly INR] */
    public const AGENT_TIERS = [
        'maple' => ['Maple', 'A compact starting point', 2, 4, 40, 'SSD', 699],
        'aurora' => ['Aurora', 'More room to explore', 4, 6, 80, 'SSD', 1149],
        'edge' => ['Edge', 'An 8 GB foundation', 4, 8, 100, 'NVMe', 1399],
        'rise' => ['Rise', 'Space for your next workflow', 6, 12, 200, 'NVMe', 2299],
        'boost' => ['Boost', 'A step up in resources', 8, 16, 250, 'NVMe', 3099],
        'prime' => ['Prime', 'Room for a growing stack', 10, 24, 300, 'NVMe', 4149],
        'power' => ['Power', 'More memory for your tools', 12, 32, 400, 'NVMe', 5249],
        'ultra' => ['Ultra', 'Expand your possibilities', 16, 48, 500, 'NVMe', 7099],
        'titan' => ['Titan', 'The largest storage option', 20, 64, 1000, 'NVMe', 9999],
        'infinity' => ['Infinity', 'The highest memory option', 18, 96, 900, 'NVMe', 15000],
    ];

    /** category slug => [category name, description, sort order] */
    public const AGENT_FAMILIES = [
        'hermes-cli' => ['Hermes CLI', 'Hermes CLI installed and configured on a VPS for SSH or PuTTY access.', 1],
        'hermes-workspace' => ['Hermes Workspace', 'Hermes Workspace installed with Docker on a VPS.', 2],
        'n8n' => ['n8n Managed', 'n8n installed on a managed VPS.', 3],
        'openclaw' => ['OpenClaw Managed Hosting', 'Standalone OpenClaw assistant hosting.', 5],
    ];

    /** slug => [name, tagline, vCPU, RAM GB, disk GB, disk type, bandwidth TB|null, monthly INR] */
    public const DOCKER_PLANS = [
        'docker-vps-nano' => ['Nano', 'Containers for side projects', 1, 2, 40, 'SSD', 4, 428],
        'docker-vps-micro' => ['Micro', 'A small app plus a database', 2, 4, 80, 'SSD', 8, 856],
        'docker-vps-mini' => ['Mini', 'Room for a few services', 3, 6, 120, 'SSD', 12, 1283],
        'docker-vps-standard' => ['Standard', 'NVMe speed for everyday stacks', 4, 8, 100, 'NVMe', null, 1359],
        'docker-vps-pro' => ['Pro', 'Production apps and CI runners', 8, 16, 250, 'NVMe', null, 3125],
        'docker-vps-max' => ['Max', 'Heavier workloads and many containers', 12, 32, 400, 'NVMe', null, 5499],
        'docker-vps-mega' => ['Mega', 'Large stacks with lots of memory', 16, 48, 500, 'NVMe', null, 7349],
    ];

    /** Placeholder plans replaced by this catalogue (hidden only when never ordered). */
    public const RETIRED = [
        'hermes-cli-launch', 'hermes-cli-grow', 'hermes-cli-scale',
        'hermes-workspace-launch', 'hermes-workspace-grow', 'hermes-workspace-scale',
        'n8n-launch', 'n8n-grow', 'n8n-scale',
        'openclaw-starter', 'openclaw-pro', 'openclaw-business',
        'docker-vps-a', 'docker-vps-b', 'docker-vps-c',
    ];

    public function run(): void
    {
        foreach (self::AGENT_FAMILIES as $categorySlug => [$categoryName, $description, $order]) {
            $category = ProductCategory::firstOrCreate(['slug' => $categorySlug], [
                'name' => $categoryName, 'description' => $description, 'sort_order' => $order, 'is_active' => true,
            ]);
            $i = 0;
            foreach (self::AGENT_TIERS as $tier => [$name, $tagline, $vcpu, $ram, $disk, $diskType, $monthly]) {
                $profile = $this->profile("agent-{$tier}", $name, $vcpu, $ram, $disk, $diskType, null);
                $this->plan($category, "{$categorySlug}-{$tier}", $name, [
                    'tagline' => $tagline,
                    'highlight' => $profile->summary(),
                    'features' => [],
                    'resource_profile_id' => $profile->id,
                    'is_featured' => $tier === 'edge',
                    'sort_order' => 10 + (++$i),
                    'kind' => 'hosting', 'fulfilment' => 'provisioned', 'access_model' => 'managed',
                ], $monthly);
            }
        }

        $docker = ProductCategory::firstOrCreate(['slug' => 'docker-vps'], [
            'name' => 'Docker VPS Hosting', 'description' => 'An unmanaged developer VPS with Docker Engine and Compose for your own containers.',
            'sort_order' => 7, 'is_active' => true,
        ]);
        $i = 0;
        foreach (self::DOCKER_PLANS as $slug => [$name, $tagline, $vcpu, $ram, $disk, $diskType, $bandwidth, $monthly]) {
            $profile = $this->profile($slug, 'Docker '.$name, $vcpu, $ram, $disk, $diskType, $bandwidth);
            $this->plan($docker, $slug, $name, [
                'tagline' => $tagline,
                'highlight' => $profile->summary(),
                'features' => [],
                'resource_profile_id' => $profile->id,
                'is_featured' => $slug === 'docker-vps-standard',
                'sort_order' => 10 + (++$i),
                'kind' => 'hosting', 'fulfilment' => 'provisioned', 'access_model' => 'unmanaged',
            ], $monthly);
        }

        foreach (Plan::whereIn('slug', self::RETIRED)->where('is_active', true)->get() as $old) {
            if (! $old->orderItemsExist() && ! Service::where('plan_id', $old->id)->exists()) {
                $old->forceFill(['is_active' => false, 'is_purchasable' => false])->save();
            }
        }
    }

    private function profile(string $slug, string $name, int $vcpu, int $ram, int $disk, string $diskType, ?int $bandwidth): ResourceProfile
    {
        return ResourceProfile::updateOrCreate(['slug' => $slug], [
            'name' => $name.' · '.$vcpu.' vCPU · '.$ram.' GB RAM',
            'vcpu' => $vcpu, 'ram_gb' => $ram, 'disk_gb' => $disk, 'disk_type' => $diskType,
            'bandwidth_tb' => $bandwidth, 'status' => 'displayed',
            'notes' => 'Owner-provided specification (AmbitionHost published plans, 2026-11-24).',
        ]);
    }

    private function plan(ProductCategory $category, string $slug, string $name, array $attributes, int $monthlyRupees): void
    {
        $plan = Plan::firstOrCreate(['slug' => $slug], $attributes + [
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_purchasable' => true,
            'price_status' => 'approved',
        ]);

        if ($plan->prices()->doesntExist()) {
            $plan->prices()->create(['billing_interval' => 'monthly', 'amount_minor' => $monthlyRupees * 100, 'currency' => 'INR']);
        }
    }
}
