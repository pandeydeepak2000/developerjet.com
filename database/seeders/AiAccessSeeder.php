<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * DeveloperJet AI All-Access — one API key for the leading AI models (GPT, Claude, Gemini, DeepSeek, Qwen, Grok…),
 * OpenAI- and Anthropic-compatible, with a monthly usage credit. Owner decision 2026-11-27: access is bought from an AI
 * gateway (ModelGo) and the key is delivered by staff from the admin order page; prices approved by the owner:
 * ₹999 / ₹2,499 / ₹5,999 / ₹14,999 a month (yearly = 12 × monthly), credit ≈ 70% of the price at ₹88 = $1.
 *
 * Upgrades the earlier untouched "coming soon" drafts (pending, no prices); a plan an admin has priced or edited is
 * never overwritten.
 */
class AiAccessSeeder extends Seeder
{
    /** slug => [name, tagline, monthly INR, usage credit USD, API keys, support, featured] */
    public const PLANS = [
        'ai-access-starter' => ['AI Starter', 'Every top model through one API key', 999, 8, 1, 'Ticket support', false],
        'ai-access-pro' => ['AI Pro', 'For daily coding agents and automations', 2499, 20, 2, 'Ticket support', true],
        'ai-access-team' => ['AI Team', 'Shared credit with a key per teammate', 5999, 50, 5, 'Ticket support', false],
        'ai-access-business' => ['AI Business', 'High-volume usage for products and teams', 14999, 125, 15, 'Priority support', false],
    ];

    public function run(): void
    {
        $category = ProductCategory::firstOrCreate(['slug' => 'ai-all-access'], [
            'name' => 'AI All-Access', 'sort_order' => 8, 'is_active' => true,
            'description' => 'One API key for GPT, Claude, Gemini, DeepSeek and more, with a monthly usage credit.',
        ]);
        if (str_contains((string) $category->description, 'Coming soon')) {
            $category->forceFill(['description' => 'One API key for GPT, Claude, Gemini, DeepSeek and more, with a monthly usage credit.'])->save();
        }

        $i = 0;
        foreach (self::PLANS as $slug => [$name, $tagline, $monthly, $credit, $keys, $support, $featured]) {
            $attributes = [
                'product_category_id' => $category->id, 'name' => $name, 'tagline' => $tagline,
                'highlight' => '$'.$credit.' AI usage credit every month',
                'features' => ['OpenAI- and Anthropic-compatible API', 'Works with Claude Code, Cursor, n8n and the OpenAI SDK', 'Key delivered to your dashboard after payment'],
                'comparison' => [
                    'Monthly usage credit' => '$'.$credit,
                    'API keys' => (string) $keys,
                    'Models' => 'GPT, Claude, Gemini, DeepSeek, Qwen and more',
                    'Works with' => 'Claude Code, Cursor, n8n, any OpenAI SDK',
                    'Support' => $support,
                ],
                'is_active' => true, 'is_purchasable' => true, 'is_featured' => $featured, 'price_status' => 'approved',
                'sort_order' => ++$i, 'kind' => 'ai_access', 'fulfilment' => 'provisioned', 'visibility' => 'public',
            ];

            $plan = Plan::where('slug', $slug)->first();
            if (! $plan) {
                $plan = Plan::create($attributes + ['slug' => $slug]);
            } elseif ($plan->price_status === 'pending' && $plan->prices()->doesntExist()) {
                $plan->forceFill($attributes)->save(); // the untouched coming-soon draft
            } else {
                continue; // priced or edited in Admin: never overwrite
            }

            $plan->prices()->create(['billing_interval' => 'monthly', 'amount_minor' => $monthly * 100, 'currency' => 'INR']);
            $plan->prices()->create(['billing_interval' => 'yearly', 'amount_minor' => $monthly * 12 * 100, 'currency' => 'INR']);
        }
    }
}
