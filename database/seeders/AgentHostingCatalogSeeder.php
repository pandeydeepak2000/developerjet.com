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
 * The owner supplied these prices, so they are stored as approved. Yearly = 12 × monthly (no discount, owner
 * decision); other billing periods stay blank until set in Admin → Products & plans. Plans are created once and never overwritten,
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
        'n8n' => ['n8n Hosting', 'Self-hosted n8n installed on your own VPS.', 3],
        'openclaw' => ['OpenClaw Hosting', 'OpenClaw installed on your own VPS.', 5],
    ];

    /**
     * Which VPS tiers each product is sold on, with its own plan names (2026-11-24 owner request: different
     * names per product, sizes chosen per workload). Slugs stay "{family}-{tier}" so AmbitionHost deep links
     * keep working. Sizing rationale:
     *  - Hermes CLI is a terminal agent that calls an AI API: light, 4–16 GB is plenty.
     *  - Hermes Workspace runs Docker + a web UI + database: starts at 6 GB.
     *  - n8n: 4 GB is a comfortable single-instance minimum; bigger tiers for many/heavy workflows.
     *  - OpenClaw drives a headless browser for its tools: starts at 4 GB, grows with concurrent tasks.
     *
     * family => [tier slug => [plan name, tagline]]
     */
    public const FAMILY_PLANS = [
        'hermes-cli' => [
            'maple' => ['Spark', 'Personal Hermes CLI for one developer'],
            'aurora' => ['Pulse', 'Room for longer agent sessions'],
            'edge' => ['Drive', 'Daily driver for regular projects'],
            'rise' => ['Surge', 'Parallel sessions and bigger repos'],
            'boost' => ['Turbo', 'Heavy local tooling and builds'],
        ],
        'hermes-workspace' => [
            'aurora' => ['Desk', 'Workspace for one person'],
            'edge' => ['Studio', 'Comfortable for a small team'],
            'rise' => ['Team', 'More projects and users'],
            'boost' => ['Agency', 'Busy teams with many workspaces'],
            'prime' => ['Office', 'Large teams and long histories'],
            'power' => ['Enterprise', 'Maximum memory for big workspaces'],
        ],
        'n8n' => [
            'maple' => ['Trigger', 'First automations and small workflows'],
            'aurora' => ['Node', 'A growing set of daily workflows'],
            'edge' => ['Flow', 'Business workflows with room to spare'],
            'rise' => ['Pipeline', 'Many active workflows and webhooks'],
            'boost' => ['Engine', 'Heavy data processing workflows'],
            'prime' => ['Orchestra', 'High-volume automation for teams'],
            'power' => ['Nexus', 'Large automation estates'],
        ],
        'openclaw' => [
            'maple' => ['Scout', 'Personal assistant, light browsing tasks'],
            'aurora' => ['Ranger', 'More tools and longer tasks'],
            'edge' => ['Hunter', 'Regular browser automation'],
            'rise' => ['Guardian', 'Several assistants side by side'],
            'boost' => ['Sentinel', 'Heavy browser and tool use'],
            'prime' => ['Commander', 'Team-scale assistant workloads'],
        ],
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
            $offered = self::FAMILY_PLANS[$categorySlug];
            $i = 0;
            foreach (self::AGENT_TIERS as $tier => [$tierName, , $vcpu, $ram, $disk, $diskType, $monthly]) {
                $slug = "{$categorySlug}-{$tier}";
                if (! isset($offered[$tier])) {
                    // This product is not sold on this VPS size (e.g. a 96 GB tier for Hermes CLI).
                    $this->retire($slug);

                    continue;
                }
                [$name, $tagline] = $offered[$tier];
                $profile = $this->profile("agent-{$tier}", $tierName, $vcpu, $ram, $disk, $diskType, null);
                $this->plan($category, $slug, $name, [
                    'tagline' => $tagline,
                    'highlight' => $profile->summary(),
                    'features' => [],
                    'resource_profile_id' => $profile->id,
                    'is_featured' => $tier === 'edge',
                    'sort_order' => 10 + (++$i),
                    'kind' => 'hosting', 'fulfilment' => 'provisioned', 'access_model' => 'unmanaged',
                ], $monthly, $tierName);
            }
        }

        $docker = ProductCategory::firstOrCreate(['slug' => 'docker-vps'], [
            'name' => 'Docker VPS Hosting', 'description' => 'A developer VPS with Docker Engine and Compose and full root access.',
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

        foreach (self::RETIRED as $slug) {
            $this->retire($slug);
        }

        $this->bundle();
        $this->nodejs();
    }

    /**
     * Node.js Hosting (2026-11-27 owner request, "like Hostinger's Node.js hosting, 3 plans"): a self-hosted VPS with
     * Node.js 22 LTS, PM2, Git and Caddy installed. Sold on three existing VPS tiers at the owner-provided tier prices.
     * agent tier => [plan name, tagline]
     */
    public const NODE_PLANS = [
        'maple' => ['Node Starter', 'One Node.js app or API with HTTPS'],
        'edge' => ['Node Pro', 'Production apps, workers and a database'],
        'boost' => ['Node Business', 'Several apps, heavier traffic and builds'],
    ];

    private function nodejs(): void
    {
        $category = ProductCategory::firstOrCreate(['slug' => 'nodejs-hosting'], [
            'name' => 'Node.js Hosting', 'description' => 'A VPS with Node.js, PM2 and automatic HTTPS, ready for your app.',
            'sort_order' => 6, 'is_active' => true,
        ]);
        $i = 0;
        foreach (self::NODE_PLANS as $tier => [$name, $tagline]) {
            [$tierName, , $vcpu, $ram, $disk, $diskType, $monthly] = self::AGENT_TIERS[$tier];
            $profile = $this->profile("agent-{$tier}", $tierName, $vcpu, $ram, $disk, $diskType, null);
            $this->plan($category, "nodejs-hosting-{$tier}", $name, [
                'tagline' => $tagline,
                'highlight' => $profile->summary(),
                'features' => [],
                'resource_profile_id' => $profile->id,
                'is_featured' => $tier === 'edge',
                'sort_order' => 10 + (++$i),
                'kind' => 'hosting', 'fulfilment' => 'provisioned', 'access_model' => 'unmanaged',
            ], $monthly);
        }
    }

    /**
     * AI Complete Bundle: one heavy plan running Hermes CLI + Hermes Workspace + n8n on one server.
     * Sized on the "Power" VPS (12 vCPU / 32 GB / 400 GB NVMe): Workspace (Docker, UI, DB) + n8n + CLI with headroom.
     * Price chosen at the owner's request ("high amount, decide yourself"): ₹5,999/month = the ₹5,249 Power VPS plus
     * setup of three applications, still below buying the three separately; yearly = 12 × monthly.
     * Only the untouched pending placeholder is upgraded; a bundle an admin has priced or edited is left alone.
     */
    public const BUNDLE_MONTHLY = 5999;

    private function bundle(): void
    {
        $category = ProductCategory::firstOrCreate(['slug' => 'ai-complete-bundle'], [
            'name' => 'AI Complete Bundle', 'description' => 'Hermes CLI + Workspace + n8n together.', 'sort_order' => 4, 'is_active' => true,
        ]);
        [, , $vcpu, $ram, $disk, $diskType] = self::AGENT_TIERS['power'];
        $profile = $this->profile('agent-power', 'Power', $vcpu, $ram, $disk, $diskType, null);
        $attributes = [
            'name' => 'Complete',
            'tagline' => 'Hermes CLI, Hermes Workspace and n8n on one powerful server of your own',
            'highlight' => $profile->summary(),
            'features' => ['Hermes CLI, Hermes Workspace and n8n installed', 'Each application isolated on the server', 'One invoice and one renewal'],
            'resource_profile_id' => $profile->id,
            'is_featured' => true, 'sort_order' => 1,
            'kind' => 'hosting', 'fulfilment' => 'provisioned', 'access_model' => 'unmanaged',
            'is_active' => true, 'is_purchasable' => true, 'price_status' => 'approved',
        ];

        $plan = Plan::where('slug', 'ai-complete-bundle')->first();
        if (! $plan) {
            $plan = Plan::create($attributes + ['slug' => 'ai-complete-bundle', 'product_category_id' => $category->id]);
        } elseif ($plan->price_status === 'pending' && $plan->prices()->doesntExist()) {
            $plan->forceFill($attributes)->save();
        } else {
            return; // already priced or edited in Admin: never overwrite
        }

        $plan->prices()->create(['billing_interval' => 'monthly', 'amount_minor' => self::BUNDLE_MONTHLY * 100, 'currency' => 'INR']);
        $plan->prices()->create(['billing_interval' => 'yearly', 'amount_minor' => self::BUNDLE_MONTHLY * 12 * 100, 'currency' => 'INR']);
    }

    /** Hide a plan nobody has ordered; plans with orders or services are left alone. */
    private function retire(string $slug): void
    {
        $plan = Plan::where('slug', $slug)->where('is_active', true)->first();
        if ($plan && ! $plan->orderItemsExist() && ! Service::where('plan_id', $plan->id)->exists()) {
            $plan->forceFill(['is_active' => false, 'is_purchasable' => false])->save();
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

    /**
     * Create the plan once. If an earlier run created it with the generic tier name (e.g. "Maple") and nobody has
     * renamed it since, switch it to the product-specific name. Monthly and yearly prices are added only when
     * missing; yearly = 12 × monthly (owner: no yearly discount). Admin edits are never overwritten.
     */
    private function plan(ProductCategory $category, string $slug, string $name, array $attributes, int $monthlyRupees, ?string $previousName = null): void
    {
        $plan = Plan::firstOrCreate(['slug' => $slug], $attributes + [
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_purchasable' => true,
            'price_status' => 'approved',
        ]);

        if ($previousName !== null && $plan->name === $previousName && $name !== $previousName) {
            $plan->forceFill(['name' => $name, 'tagline' => $attributes['tagline'], 'sort_order' => $attributes['sort_order'], 'is_active' => true, 'is_purchasable' => true])->save();
        }

        if (! $plan->prices()->where('billing_interval', 'monthly')->exists()) {
            $plan->prices()->create(['billing_interval' => 'monthly', 'amount_minor' => $monthlyRupees * 100, 'currency' => 'INR']);
        }
        if (! $plan->prices()->where('billing_interval', 'yearly')->exists()) {
            $plan->prices()->create(['billing_interval' => 'yearly', 'amount_minor' => $monthlyRupees * 12 * 100, 'currency' => 'INR']);
        }
    }
}
