<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * DeveloperJet AI All-Access — a planned bring-your-own-key (BYOK) AI workspace. Create-only (never overwrites admin edits).
 *
 * NOTHING here is priced, approved or live: three draft plan slots with placeholder names, no prices, no model quotas and
 * no seat counts. They are quote-fulfilled and never purchasable (also enforced in Plan::canBePurchased and the admin form).
 * The only customer action is "Request access", which uses the existing service-request workflow and charges nothing.
 */
class AiAccessSeeder extends Seeder
{
    public function run(): void
    {
        $category = ProductCategory::firstOrCreate(['slug' => 'ai-all-access'], [
            'name' => 'AI All-Access',
            'description' => 'A planned workspace for using supported AI models with your own API keys. Coming soon — not yet available to order.',
            'sort_order' => 8, 'is_active' => true,
        ]);

        $tbc = 'To be confirmed';
        $plans = [
            ['ai-access-starter', 'AI Access Starter', 'Draft plan for individuals exploring several AI models.', 'Individuals'],
            ['ai-access-team', 'AI Access Team', 'Draft plan for small teams sharing one AI workspace.', 'Small teams'],
            ['ai-access-business', 'AI Access Business', 'Draft plan for organisations that need administration and reporting.', 'Organisations'],
        ];

        foreach ($plans as $i => [$slug, $name, $tagline, $audience]) {
            Plan::firstOrCreate(['slug' => $slug], [
                'product_category_id' => $category->id, 'name' => $name, 'tagline' => $tagline,
                'highlight' => 'Pricing to be confirmed',
                'features' => [],
                // "Label: value" rows shown on the plan card and plan comparison. Editable in Admin → Plans.
                'comparison' => [
                    'Intended for' => $audience,
                    'AI provider access' => 'Your own API keys',
                    'Workspace members' => $tbc,
                    'Support' => $tbc,
                ],
                'is_active' => true, 'is_purchasable' => false, 'is_featured' => false, 'price_status' => 'pending',
                'sort_order' => $i + 1, 'kind' => 'ai_access', 'fulfilment' => 'quote', 'visibility' => 'public',
            ]);
        }
    }
}
