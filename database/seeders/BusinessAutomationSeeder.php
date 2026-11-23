<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Business Automation (Digital Services) — three DRAFT plans. Create-only: never overwrites admin edits.
 * No prices, deliverables, workflow counts, timelines or results are invented: every plan is quote-based
 * (fulfilment "quote"), not purchasable, price status "pending". Names are proposals for the owner to confirm in
 * Admin → Products & plans.
 */
class BusinessAutomationSeeder extends Seeder
{
    public function run(): void
    {
        $category = ProductCategory::firstOrCreate(['slug' => 'business-automation'], [
            'name' => 'Business Automation',
            'description' => 'Custom workflow automation for businesses, scoped and quoted per requirement.',
            'sort_order' => 14, 'is_active' => true,
        ]);

        $tbc = 'To be confirmed';
        $plans = [
            ['automation-starter', 'Automation Starter', 'Draft plan for a first, focused automation.', 'Small businesses automating one process'],
            ['automation-growth', 'Automation Growth', 'Draft plan for connecting several tools and processes.', 'Growing teams with several connected tools'],
            ['automation-enterprise', 'Automation Enterprise', 'Draft plan for larger, multi-department automation programmes.', 'Organisations with complex or regulated processes'],
        ];

        foreach ($plans as $i => [$slug, $name, $tagline, $audience]) {
            Plan::firstOrCreate(['slug' => $slug], [
                'product_category_id' => $category->id, 'name' => $name, 'tagline' => $tagline,
                'highlight' => 'Quote on request',
                'features' => [],
                // "Label: value" rows on the plan card. Editable in Admin → Plans; nothing here is a commitment.
                'comparison' => [
                    'Suited to' => $audience,
                    'Scope' => 'Agreed in your written quotation',
                    'Workflows included' => $tbc,
                    'Support after handover' => $tbc,
                ],
                'is_active' => true, 'is_purchasable' => false, 'is_featured' => false, 'price_status' => 'pending',
                'sort_order' => $i + 1, 'kind' => 'automation', 'fulfilment' => 'quote', 'visibility' => 'public',
            ]);
        }
    }
}
