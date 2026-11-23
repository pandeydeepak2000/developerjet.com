<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** Billing period keys (1, 3, 6, 12 months). See the BillingCycle support class. */
    public const INTERVALS = ['monthly', 'quarterly', 'semiannual', 'yearly'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_purchasable' => 'boolean',
            'not_included' => 'array',
            'comparison' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ResourceProfile::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function priceFor(string $interval): ?int
    {
        $price = $this->prices->firstWhere('billing_interval', $interval);

        return $price && $price->is_active ? $price->amount_minor : null;
    }

    /**
     * Price a customer can actually be charged for this period: plan price approved, the period row available,
     * priced, and not individually held back as "pending". Returns the full pricing snapshot or null.
     *
     * @return array{interval: string, amount_minor: int, setup_minor: int, discount_percent: int, currency: string}|null
     */
    public function pricing(string $interval, bool $requireApproval = true): ?array
    {
        if ($requireApproval && ! $this->isApproved()) {
            return null;
        }
        $row = $this->prices->firstWhere('billing_interval', $interval);
        if (! $row || ! $row->is_active || $row->amount_minor === null || ! $row->isApproved()) {
            return null;
        }

        return [
            'interval' => $interval,
            'amount_minor' => (int) $row->amount_minor,
            'setup_minor' => (int) ($row->setup_fee_minor ?? $this->setup_fee_minor ?? 0),
            'discount_percent' => (int) ($row->discount_percent ?? $this->discount_percent ?? 0),
            'currency' => $row->currency ?: 'INR',
        ];
    }

    /** Price that may be shown and put in a cart (period available, priced, not held as pending). Ordering still
     *  requires plan approval (OrderService::assertPurchasable). */
    public function listPrice(string $interval): ?int
    {
        return $this->pricing($interval, false)['amount_minor'] ?? null;
    }

    public function chargeablePriceFor(string $interval): ?int
    {
        return $this->pricing($interval)['amount_minor'] ?? null;
    }

    /** @return list<string> periods offered in the cart (see listPrice) */
    public function cartIntervals(): array
    {
        return array_values(array_filter(self::INTERVALS, fn ($i) => $this->listPrice($i) !== null));
    }

    /** Service kinds whose backend does not exist yet: shown publicly, but never sold (request-access only). */
    public const NOT_SELLABLE_KINDS = ['ai_access'];

    /** A plan can be added to a cart only when flagged purchasable and a monthly price exists. */
    public function canBePurchased(): bool
    {
        return $this->is_active && $this->is_purchasable && ! $this->isComingSoon() && $this->listPrice('monthly') !== null;
    }

    /** AI All-Access (BYOK workspace) is in development: it can be requested, never bought. */
    public function isComingSoon(): bool
    {
        return in_array($this->kind, self::NOT_SELLABLE_KINDS, true);
    }

    public function orderItemsExist(): bool
    {
        return OrderItem::where('plan_id', $this->id)->exists();
    }

    /** Only owner-approved prices may be charged. Illustrative/pending prices are display-only. */
    public function isApproved(): bool
    {
        return $this->price_status === 'approved';
    }

    public function hasPublishedPrice(): bool
    {
        return $this->price_status !== 'pending' && $this->priceFor('monthly') !== null;
    }
}
