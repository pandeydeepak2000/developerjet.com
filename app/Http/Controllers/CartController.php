<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index(): View
    {
        return view('pages.cart', ['summary' => $this->cart->summary($this->cart->current())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'slug')],
            'billing_interval' => ['required', Rule::in(Plan::INTERVALS)],
            'addons' => ['sometimes', 'array', 'max:10'],
            'addons.*' => ['integer'],
        ]);

        $plan = Plan::with('prices')->where('slug', $data['plan'])->firstOrFail();

        try {
            $this->cart->add($plan, $data['billing_interval'], $data['addons'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cart.index')->with('status', "{$plan->name} added to your cart.");
    }

    public function destroy(int $item): RedirectResponse
    {
        $this->cart->remove($item);

        return redirect()->route('cart.index')->with('status', 'Item removed.');
    }
}
