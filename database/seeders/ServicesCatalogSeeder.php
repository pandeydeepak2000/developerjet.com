<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Phase 4 professional-service plans. NO prices are invented: every plan is quote-based, not purchasable, price status
 * "pending". Plan names are proposals for the owner to confirm. Create-only, so admin edits are never overwritten.
 */
class ServicesCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['wordpress-services', 'WordPress Services', 'wordpress', 10, [['Starter Site', 'A focused WordPress site.'], ['Business Site', 'A fuller WordPress business website.'], ['Store', 'WordPress with e-commerce requirements.']]],
            ['laravel-development', 'Laravel Development', 'laravel', 11, [['Starter Build', 'A scoped Laravel application.'], ['Professional Build', 'Custom features, dashboard and integrations.'], ['Enterprise Build', 'Larger, multi-module systems.']]],
            ['web-development', 'Web Development', 'web', 12, [['Landing Page', 'A single high-quality landing page.'], ['Business Website', 'A multi-page business website.'], ['Custom Development', 'Custom web development, quoted per requirements.']]],
            ['seo-services', 'SEO Services', 'seo', 13, [['SEO Basic', 'Onboarding, audit and a monthly task checklist.'], ['SEO Growth', 'Ongoing optimisation work and monthly reports.'], ['SEO Advanced', 'Extended optimisation with deeper reporting.']]],
        ];

        foreach ($groups as [$slug, $name, $kind, $order, $plans]) {
            $category = ProductCategory::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $name.' delivered as managed projects.', 'sort_order' => $order, 'is_active' => true]);

            foreach ($plans as $i => [$planName, $tagline]) {
                Plan::firstOrCreate(['slug' => $slug.'-'.str($planName)->slug()], [
                    'product_category_id' => $category->id, 'name' => $planName, 'tagline' => $tagline,
                    'highlight' => 'Quote on request', 'features' => [], 'is_active' => true, 'is_purchasable' => false,
                    'price_status' => 'pending', 'sort_order' => $i + 1, 'kind' => $kind, 'fulfilment' => 'quote',
                ]);
            }
        }
    }
}
