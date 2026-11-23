<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Guest + persistent cart. Guests are tracked by a random token held in the session;
 * on login the guest cart is merged into the customer's persistent cart.
 * Prices are always read live from the catalogue, never from the client.
 */
class CartService
{
    public const SESSION_KEY = 'cart_token';

    public function current(bool $create = false): ?Cart
    {
        $user = auth()->user();

        if ($user) {
            return $create ? Cart::firstOrCreate(['user_id' => $user->id]) : Cart::where('user_id', $user->id)->first();
        }

        $token = session(self::SESSION_KEY);

        if ($token && ($cart = Cart::where('guest_token', $token)->first())) {
            return $cart;
        }

        if (! $create) {
            return null;
        }

        $token = Str::random(48);
        session([self::SESSION_KEY => $token]);

        return Cart::create(['guest_token' => $token]);
    }

    /**
     * @param  array<int>  $addonIds
     */
    public function add(Plan $plan, string $interval, array $addonIds = []): CartItem
    {
        $plan->loadMissing('prices');

        if (! in_array($interval, Plan::INTERVALS, true)) {
            throw new InvalidArgumentException('Unsupported billing interval.');
        }

        if (! $plan->canBePurchased() || $plan->listPrice($interval) === null) {
            throw new InvalidArgumentException('This plan is not available to purchase yet.');
        }

        $validAddons = Addon::query()
            ->whereIn('id', $addonIds)
            ->where('is_active', true)
            ->whereNotNull('price_minor')
            ->whereHas('plans', fn ($q) => $q->whereKey($plan->id))
            ->pluck('id')->all();

        $cart = $this->current(create: true);

        return $cart->items()->updateOrCreate(
            ['plan_id' => $plan->id],
            ['billing_interval' => $interval, 'addon_ids' => $validAddons],
        );
    }

    public function remove(int $itemId): void
    {
        $this->current()?->items()->whereKey($itemId)->delete();
    }

    public function count(): int
    {
        $user = auth()->user();
        $cart = $user
            ? Cart::where('user_id', $user->id)->first()
            : (($t = session(self::SESSION_KEY)) ? Cart::where('guest_token', $t)->first() : null);

        return $cart ? $cart->items()->count() : 0;
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>, total_minor: int, unavailable: int}
     */
    public function summary(?Cart $cart): array
    {
        $lines = [];
        $total = 0;
        $unavailable = 0;

        $items = $cart ? $cart->items()->with('plan.prices', 'plan.category', 'plan.resourceProfile')->get() : collect();
        $allAddonIds = $items->pluck('addon_ids')->flatten()->filter()->unique()->all();
        $addons = $allAddonIds ? Addon::whereIn('id', $allAddonIds)->get()->keyBy('id') : collect();

        foreach ($items as $item) {
            $pricing = $item->plan->pricing($item->billing_interval, false);
            $price = $pricing['amount_minor'] ?? null;
            $available = $item->plan->canBePurchased() && $price !== null;

            $lineAddons = collect($item->addon_ids ?? [])
                ->map(fn ($id) => $addons->get($id))
                ->filter(fn ($a) => $a && $a->is_active && $a->isPriced())
                ->values();

            $lineTotal = $available ? $price + $lineAddons->sum('price_minor') : 0;
            $available ? $total += $lineTotal : $unavailable++;

            $lines[] = [
                'item' => $item,
                'plan' => $item->plan,
                'interval' => $item->billing_interval,
                'price_minor' => $price,
                'setup_minor' => $pricing['setup_minor'] ?? 0,
                'discount_percent' => $pricing['discount_percent'] ?? 0,
                'addons' => $lineAddons,
                'line_total_minor' => $lineTotal,
                'available' => $available,
            ];
        }

        return ['lines' => $lines, 'total_minor' => $total, 'unavailable' => $unavailable];
    }

    /** Merge the session's guest cart into the user's persistent cart (called on login). */
    public function mergeGuestInto(User $user): void
    {
        $token = session(self::SESSION_KEY);

        if (! $token || ! ($guest = Cart::where('guest_token', $token)->first())) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        DB::transaction(function () use ($guest, $user) {
            $userCart = Cart::firstOrCreate(['user_id' => $user->id]);
            $existing = $userCart->items()->pluck('plan_id')->all();

            foreach ($guest->items as $item) {
                if (! in_array($item->plan_id, $existing, true)) {
                    $item->update(['cart_id' => $userCart->id]);
                }
            }

            $guest->delete();
        });

        session()->forget(self::SESSION_KEY);
    }
}
