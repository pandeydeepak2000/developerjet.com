<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Service;
use App\Services\CartService;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Services\Payments\GatewayManager;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Checkout is gated by `auth` + `verified`. Placing an order only creates an unpaid order; "Paid" is reached solely via
 * verified payment (see PaymentService).
 */
class CheckoutController extends Controller
{
    public function show(CartService $carts, OrderService $orders, GatewayManager $gateways, WalletService $wallets, Request $request): View|RedirectResponse
    {
        $summary = $carts->summary($carts->current());

        if (! $summary['lines']) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // One token per checkout page load: a double-click or resubmit maps to the same order.
        $request->session()->put('checkout_token', Str::uuid()->toString());

        return view('pages.checkout', [
            'summary' => $summary,
            'pricing' => $summary['unavailable'] === 0 ? $orders->computeLines($summary) : null,
            'gateways' => $gateways->available(),
            'wallet' => $wallets->walletFor($request->user()),
            'approved' => collect($summary['lines'])->every(fn ($l) => $l['plan']->isApproved()),
        ]);
    }

    public function store(Request $request, CartService $carts, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'billing_name' => ['nullable', 'string', 'max:120'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9A-Z]{15}$/'],
            'use_wallet' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', 'string', 'in:razorpay'],
            'accept_terms' => ['accepted'],
        ], ['accept_terms.accepted' => 'Please accept the Terms and Refund Policy to continue.']);

        $token = $request->session()->get('checkout_token');
        abort_unless($token, 419);

        $cart = $carts->current();
        $summary = $carts->summary($cart);
        $setup = $this->setupChoices($request, $summary);

        try {
            $order = $orders->place($request->user(), $summary, [
                'billing_name' => $data['billing_name'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'gstin' => $data['gstin'] ?? null,
            ], $token, $request->boolean('use_wallet'), null, $setup);
        } catch (OrderException $e) {
            return back()->withInput($request->except('setup'))->with('error', $e->getMessage());
        }

        $cart?->items()->delete();
        $request->session()->forget('checkout_token');

        return redirect()->route('dashboard.orders.show', $order)
            ->with('success', 'Order placed. Complete payment to start provisioning.')
            ->with('preferred_gateway', $data['payment_method'] ?? null);
    }

    /** Which setup questions a plan asks at checkout: ['domain' => bool, 'ai' => bool]. */
    public static function setupNeeds(Plan $plan): array
    {
        $kind = OrderService::KIND_BY_CATEGORY[$plan->category?->slug] ?? null;

        return [
            'domain' => $plan->kind === 'hosting' && in_array($kind, Service::DOMAIN_KINDS, true),
            'ai' => $plan->kind === 'hosting' && in_array($kind, Service::AI_KINDS, true),
        ];
    }

    /**
     * Optional per-plan setup from checkout, keyed by plan id. Own domain: validated hostname, verified later by a
     * DNS TXT record. AI key: encrypted immediately and never logged; it moves to the service when the order is paid.
     *
     * @return array<int, array<string, string>>
     */
    private function setupChoices(Request $request, array $summary): array
    {
        $providers = array_keys(config('provisioning.ai_providers'));
        $request->validate([
            'setup' => ['nullable', 'array'],
            'setup.*.domain_mode' => ['nullable', 'in:default,own'],
            'setup.*.domain' => ['nullable', 'required_if:setup.*.domain_mode,own', 'string', 'max:190', 'regex:/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/i'],
            'setup.*.ai_provider' => ['nullable', 'in:'.implode(',', $providers)],
            'setup.*.ai_api_key' => ['nullable', 'required_with:setup.*.ai_provider', 'string', 'min:8', 'max:300'],
        ], [
            'setup.*.domain.required_if' => 'Enter your domain, or choose "Use a DeveloperJet address".',
            'setup.*.domain.regex' => 'Enter a domain like n8n.yourcompany.com (no http:// or slashes).',
            'setup.*.ai_api_key.required_with' => 'Paste the API key for the provider you selected, or leave the provider empty to add it later.',
        ]);

        $out = [];
        foreach ($summary['lines'] as $line) {
            $plan = $line['plan'];
            $needs = self::setupNeeds($plan);
            $input = (array) $request->input('setup.'.$plan->id, []);
            $choice = [];
            if ($needs['domain']) {
                $own = ($input['domain_mode'] ?? 'default') === 'own' && ! empty($input['domain']);
                $choice['domain_mode'] = $own ? 'own' : 'default';
                if ($own) {
                    $choice['domain'] = strtolower(trim((string) $input['domain']));
                }
            }
            if ($needs['ai'] && ! empty($input['ai_provider']) && ! empty($input['ai_api_key'])) {
                $choice['ai_provider'] = (string) $input['ai_provider'];
                $choice['ai_key'] = Crypt::encryptString(trim((string) $input['ai_api_key']));
            }
            if ($choice) {
                $out[$plan->id] = $choice;
            }
        }

        return $out;
    }
}
