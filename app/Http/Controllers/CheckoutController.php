<?php

namespace App\Http\Controllers;

use App\Models\CustomerApiKey;
use App\Models\Plan;
use App\Models\Service;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Services\Payments\GatewayManager;
use App\Services\TaxService;
use App\Services\WalletService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $wallet = $wallets->walletFor($request->user());
        $walletPricing = $summary['unavailable'] === 0 ? $orders->computeLines($summary, true) : null;

        return view('pages.checkout', [
            'summary' => $summary,
            'pricing' => $summary['unavailable'] === 0 ? $orders->computeLines($summary) : null,
            // Wallet route: no GST, offered only when the balance covers the whole GST-free total.
            'walletPricing' => $walletPricing,
            'walletCovers' => $walletPricing !== null && $wallet->balance_minor >= $walletPricing['total'],
            'gstPercent' => app(TaxService::class)->standardPercent(),
            'gateways' => $gateways->available(),
            'wallet' => $wallet,
            'savedKeys' => CustomerApiKey::where('user_id', $request->user()->id)->latest('id')->get(),
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
            'payment_method' => ['nullable', 'string', 'in:razorpay,wallet'],
            'accept_terms' => ['accepted'],
        ], ['accept_terms.accepted' => 'Please accept the Terms and Refund Policy to continue.']);

        $token = $request->session()->get('checkout_token');
        abort_unless($token, 419);

        $cart = $carts->current();
        $summary = $carts->summary($cart);
        $setup = $this->setupChoices($request, $summary);

        try {
            $order = $orders->place($request->user(), $summary, $this->billingSnapshot($request->user(), $data), $token, $request->boolean('use_wallet') || ($data['payment_method'] ?? null) === 'wallet', null, $setup);
        } catch (OrderException $e) {
            return back()->withInput($request->except('setup'))->with('error', $e->getMessage());
        }

        $cart?->items()->delete();
        $request->session()->forget('checkout_token');

        $paid = $order->status !== 'pending_payment';
        $online = ($data['payment_method'] ?? null) === 'razorpay';

        return redirect()->route('dashboard.orders.show', $online && ! $paid ? ['order' => $order, 'gateway' => 'razorpay', 'pay' => 1] : $order)
            ->with('success', $paid
                ? 'Payment received. Our team is now preparing your server — follow the progress here.'
                : ($online ? 'Order saved. Complete the payment in the secure window to confirm it.' : 'Order placed. Complete payment to start provisioning.'))
            ->with('preferred_gateway', $online ? 'razorpay' : null);
    }

    /**
     * Invoice billing details come from the customer's saved profile (Profile → Billing details). Older API callers
     * may still post billing_name / billing_address / gstin; those act only as fallbacks when the profile is empty.
     */
    private function billingSnapshot(User $user, array $data): array
    {
        $country = $user->billing_country ? (CustomerProfileController::COUNTRIES[$user->billing_country] ?? $user->billing_country) : null;
        $address = $user->billing_address
            ? trim(implode("\n", array_filter([
                $user->billing_address,
                collect([$user->billing_city, $user->billing_state, $user->billing_postcode])->filter()->implode(', '),
                $country,
            ])))
            : ($data['billing_address'] ?? null);

        return array_filter([
            'billing_name' => $user->billing_company ?: ($data['billing_name'] ?? $user->name),
            'billing_contact' => $user->billing_company ? $user->name : null,
            'billing_address' => $address,
            'billing_state' => $user->billing_state,
            'billing_country' => $user->billing_country,
            'gstin' => $user->gstin ?: ($data['gstin'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Which setup questions a plan asks at checkout: ['domain' => bool, 'ai' => bool]. */
    public static function setupNeeds(Plan $plan): array
    {
        $kind = OrderService::KIND_BY_CATEGORY[$plan->category?->slug] ?? null;

        return [
            'domain' => $plan->kind === 'hosting' && in_array($kind, Service::DOMAIN_KINDS, true),
            // Owner decision (2026-11-25): no AI key at checkout. After delivery the customer's dashboard shows the
            // command to add their own key on the server.
            'ai' => false,
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
            'setup.*.api_key_id' => ['nullable', 'string', 'max:20'],
            'setup.*.ai_api_key' => ['nullable', 'string', 'min:8', 'max:300', 'not_regex:/\s/'],
        ], [
            'setup.*.domain.required_if' => 'Enter your domain, or choose "Use a DeveloperJet address".',
            'setup.*.domain.regex' => 'Enter a domain like n8n.yourcompany.com (no http:// or slashes).',
            'setup.*.ai_api_key.min' => 'That API key looks too short. Paste the full key, or choose to add it later.',
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
            if ($needs['ai']) {
                $pick = (string) ($input['api_key_id'] ?? '');
                if (ctype_digit($pick) && ($saved = CustomerApiKey::where('user_id', $request->user()->id)->find((int) $pick))) {
                    $choice['api_key_id'] = $saved->id; // a key already saved on the account
                } elseif (($pick === 'new' || $pick === '') && ! empty($input['ai_provider']) && ! empty($input['ai_api_key'])) {
                    // A new key is saved to the account straight away (encrypted), so it can be reused and managed.
                    $saved = CustomerApiKey::store($request->user(), (string) $input['ai_provider'], (string) $input['ai_api_key'], 'Added at checkout');
                    Audit::log('customer.api_key_added', $saved, ['provider' => $saved->provider], $request->user()->id);
                    $choice['api_key_id'] = $saved->id;
                }
            }
            if ($choice) {
                $out[$plan->id] = $choice;
            }
        }

        return $out;
    }
}
