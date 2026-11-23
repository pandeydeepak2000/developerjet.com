<?php

namespace App\Support;

use App\Models\Plan;

/**
 * Product family cards (config/product_families.php) enriched with a starting price.
 * A starting price is shown ONLY when a plan in the family has an owner-approved monthly price; illustrative and
 * pending prices never appear on family cards.
 */
class ProductFamilies
{
    /** @return array<string, array> keyed by family key, each with an added `url` and `from_minor` (int|null). */
    public static function all(bool $homeOnly = false): array
    {
        $families = array_filter(config('product_families', []), fn ($f) => ! $homeOnly || ($f['home'] ?? false));
        $slugs = array_values(array_unique(array_merge(...array_values(array_map(fn ($f) => $f['categories'] ?? [], $families)))));

        $approved = $slugs ? Plan::with(['prices', 'category'])->active()->where('price_status', 'approved')
            ->whereHas('category', fn ($q) => $q->whereIn('slug', $slugs)->where('is_active', true))->get() : collect();

        foreach ($families as $key => &$family) {
            $prices = $approved->filter(fn (Plan $p) => in_array($p->category->slug, $family['categories'] ?? [], true) && ! $p->isComingSoon())
                ->map(fn (Plan $p) => $p->priceFor('monthly'))->filter(fn ($v) => $v !== null);
            $family['key'] = $key;
            $family['from_minor'] = $family['status'] === 'available' && $prices->isNotEmpty() ? $prices->min() : null;
            $family['url'] = route($family['route'][0], $family['route'][1] ?? []);
        }

        return $families;
    }
}
