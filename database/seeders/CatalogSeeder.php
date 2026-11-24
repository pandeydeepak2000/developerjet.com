<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Plan;
use App\Models\ProductCategory;
use App\Models\ResourceProfile;
use Illuminate\Database\Seeder;

/**
 * Seeds the 13 AI hosting plan records (SRS 5.1) idempotently.
 *
 * Price/resource policy:
 *  - The 9 Hermes CLI / Workspace / n8n plans carry exactly the values already displayed on the
 *    existing site. Their price_status is "illustrative" (NOT owner-approved) and resource profiles
 *    are "displayed" (NOT verified).
 *  - AI Complete Bundle and the 3 OpenClaw plans have NO price and NO resources: status "pending".
 *    They cannot be added to a cart until the owner approves values (SRS Q-001 / Q-002).
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];
        foreach ([
            ['hermes-cli', 'Hermes CLI', 'Hermes CLI installed and configured on a VPS for SSH or PuTTY access.', 1],
            ['hermes-workspace', 'Hermes Workspace', 'Hermes Workspace installed with Docker on a VPS.', 2],
            ['n8n', 'n8n Hosting', 'Self-hosted n8n installed on your own VPS.', 3],
            ['ai-complete-bundle', 'AI Complete Bundle', 'Hermes CLI + Workspace + n8n together.', 4],
            ['openclaw', 'OpenClaw Hosting', 'OpenClaw installed on your own VPS.', 5],
        ] as [$slug, $name, $description, $order]) {
            $categories[$slug] = ProductCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'sort_order' => $order, 'is_active' => true],
            );
        }

        $profiles = [];
        foreach ([
            ['displayed-2c-4g', '2 vCPU · 4 GB RAM', 2, 4, 80, 4],
            ['displayed-4c-8g', '4 vCPU · 8 GB RAM', 4, 8, 160, 8],
            ['displayed-8c-16g', '8 vCPU · 16 GB RAM', 8, 16, 320, 16],
        ] as [$slug, $name, $vcpu, $ram, $disk, $bandwidth]) {
            $profiles[$slug] = ResourceProfile::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'vcpu' => $vcpu, 'ram_gb' => $ram, 'disk_gb' => $disk, 'disk_type' => 'NVMe',
                'bandwidth_tb' => $bandwidth, 'status' => 'displayed',
                'notes' => 'Shown on the existing site. Not yet verified against the real VPS inventory (SRS Q-001).',
            ]);
        }

        $tiers = [
            // name, description variants per family are set below
            'launch' => ['Launch', 'displayed-2c-4g', 2499, 24990, false, 1],
            'grow' => ['Grow', 'displayed-4c-8g', 4999, 49990, true, 2],
            'scale' => ['Scale', 'displayed-8c-16g', 9999, 99990, false, 3],
        ];

        $families = [
            'hermes-cli' => [
                'descriptions' => ['launch' => 'A capable home for a personal Hermes setup.', 'grow' => 'More room for regular workloads and projects.', 'scale' => 'Extra headroom for heavier workloads.'],
                'features' => [
                    'launch' => ['80 GB NVMe storage', '4 TB monthly bandwidth', 'Hermes setup guidance'],
                    'grow' => ['160 GB NVMe storage', '8 TB monthly bandwidth', 'Hermes setup guidance'],
                    'scale' => ['320 GB NVMe storage', '16 TB monthly bandwidth', 'Hermes setup guidance'],
                ],
                'profiles' => true,
            ],
            'hermes-workspace' => [
                'descriptions' => ['launch' => 'A capable start for your Hermes Workspace.', 'grow' => 'More room for your team and projects.', 'scale' => 'Extra capacity for a larger Workspace.'],
                'features' => [
                    'launch' => ['80 GB NVMe storage', '4 TB monthly bandwidth'],
                    'grow' => ['160 GB NVMe storage', '8 TB monthly bandwidth'],
                    'scale' => ['320 GB NVMe storage', '16 TB monthly bandwidth'],
                ],
                'profiles' => true,
            ],
        ];

        foreach ($families as $categorySlug => $family) {
            foreach ($tiers as $tierSlug => [$name, $profileSlug, $monthly, $yearly, $featured, $order]) {
                $profile = $profiles[$profileSlug];
                $this->plan($categories[$categorySlug], "{$categorySlug}-{$tierSlug}", $name, [
                    'tagline' => $family['descriptions'][$tierSlug],
                    'highlight' => $profile->summary(),
                    'features' => $family['features'][$tierSlug],
                    'resource_profile_id' => $profile->id,
                    'is_featured' => $featured,
                    'sort_order' => $order,
                ], $monthly, $yearly);
            }
        }

        // n8n: displayed plans describe workflow limits only; VPS size is "confirmed before purchase".
        foreach ([
            'launch' => ['Launch', 'Automate the small things first.', 'Up to 5 active workflows', ['n8n installed on your own VPS', 'Full root access'], 2499, 24990, false, 1],
            'grow' => ['Grow', 'For workflows that are going places.', 'Up to 25 active workflows', ['n8n installed on your own VPS', 'Priority support'], 5999, 59990, true, 2],
            'scale' => ['Scale', 'More headroom for bigger ideas.', 'Up to 100 active workflows', ['n8n installed on your own VPS', 'Guided workflow onboarding'], 11999, 119990, false, 3],
        ] as $tierSlug => [$name, $tagline, $highlight, $features, $monthly, $yearly, $featured, $order]) {
            $this->plan($categories['n8n'], "n8n-{$tierSlug}", $name, [
                'tagline' => $tagline, 'highlight' => $highlight, 'features' => $features,
                'is_featured' => $featured, 'sort_order' => $order,
            ], $monthly, $yearly);
        }

        // Pending plans: no price, no resources, not purchasable.
        $this->plan($categories['ai-complete-bundle'], 'ai-complete-bundle', 'AI Complete Bundle', [
            'tagline' => 'Hermes CLI, Hermes Workspace and n8n together on one server of your own.',
            'highlight' => 'Pricing and resources to be confirmed',
            'features' => ['Hermes CLI', 'Hermes Workspace', 'n8n'],
            'is_featured' => true, 'sort_order' => 1,
        ]);

        foreach ([['starter', 'Starter'], ['pro', 'Pro'], ['business', 'Business']] as $i => [$slug, $name]) {
            $this->plan($categories['openclaw'], "openclaw-{$slug}", $name, [
                'tagline' => 'Independent OpenClaw assistant hosting and onboarding.',
                'highlight' => 'Pricing and resources to be confirmed',
                'features' => [],
                'sort_order' => $i + 1,
            ]);
        }

        // Optional add-ons: no invented prices.
        $domain = Addon::updateOrCreate(['slug' => 'custom-domain'], [
            'name' => 'Custom domain setup',
            'description' => 'Use your own validated domain instead of the default subdomain (SRS FR-013).',
            'price_minor' => null, 'billing_type' => 'one_time', 'is_active' => true,
        ]);
        $domain->plans()->sync(Plan::whereIn('product_category_id', [$categories['hermes-workspace']->id, $categories['n8n']->id])->pluck('id'));

        $n8nOnCli = Addon::updateOrCreate(['slug' => 'n8n-on-hermes-cli'], [
            'name' => 'n8n alongside Hermes CLI',
            'description' => 'Add n8n to a Hermes CLI plan (SRS AC-06). Price to be confirmed.',
            'price_minor' => null, 'billing_type' => 'recurring', 'is_active' => true,
        ]);
        $n8nOnCli->plans()->sync(Plan::where('product_category_id', $categories['hermes-cli']->id)->pluck('id'));
    }

    private function plan(ProductCategory $category, string $slug, string $name, array $attributes, ?int $monthlyRupees = null, ?int $yearlyRupees = null): void
    {
        $priced = $monthlyRupees !== null;

        // Create only: re-running the seeder must never overwrite values an admin has since edited.
        $plan = Plan::firstOrCreate(['slug' => $slug], $attributes + [
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'access_model' => 'unmanaged',
            'is_purchasable' => $priced,
            'price_status' => $priced ? 'illustrative' : 'pending',
        ]);

        if ($priced && $plan->prices()->doesntExist()) {
            $plan->prices()->createMany([
                ['billing_interval' => 'monthly', 'amount_minor' => $monthlyRupees * 100, 'currency' => 'INR'],
                ['billing_interval' => 'yearly', 'amount_minor' => $yearlyRupees * 100, 'currency' => 'INR'],
            ]);
        }
    }
}
