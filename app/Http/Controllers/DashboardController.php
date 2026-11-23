<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Models\TopupRequest;
use App\Models\Wallet;
use App\Services\Provisioning\RecipeRegistry;
use App\Support\SubscriptionStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(Request $request): View
    {
        $user = $request->user();

        return view('dashboard.overview', [
            'orderCount' => $user->orders()->count(),
            'serviceCount' => $user->services()->count(),
            'invoiceCount' => $user->invoices()->count(),
            'wallet' => $user->wallet ?? new Wallet(['balance_minor' => 0, 'currency' => 'INR']),
            'upcoming' => Subscription::forCustomer($user)->with('service', 'plan')
                ->whereIn('status', SubscriptionStatus::OPEN_STATES)->whereNotNull('next_due_at')
                ->where('next_due_at', '<=', now()->addDays(30))->orderBy('next_due_at')->limit(5)->get(),
            'unpaidCount' => $user->invoices()->where('type', 'invoice')->where('status', 'unpaid')->count(),
        ]);
    }

    public function orders(Request $request): View
    {
        return view('dashboard.orders', ['orders' => $request->user()->orders()->latest()->paginate(15)]);
    }

    public function services(Request $request): View
    {
        return view('dashboard.services', [
            'services' => $request->user()->services()
                ->with('plan.resourceProfile', 'order', 'subscription', 'latestLiveDeployment')
                ->latest()->paginate(15),
            'registry' => app(RecipeRegistry::class),
        ]);
    }

    public function wallet(Request $request): View
    {
        $wallet = $request->user()->wallet;

        return view('dashboard.wallet', [
            'wallet' => $wallet ?? new Wallet(['balance_minor' => 0, 'currency' => 'INR']),
            'transactions' => $wallet ? $wallet->transactions()->paginate(15) : collect(),
            'topups' => TopupRequest::where('user_id', $request->user()->id)->latest()->limit(10)->get(),
        ]);
    }

    /** Gateway payment attempts and wallet payments for the signed-in customer's own orders only. */
    public function payments(Request $request): View
    {
        $userId = $request->user()->id;

        return view('dashboard.payments', [
            'attempts' => PaymentAttempt::with('order:id,number,user_id')
                ->whereHas('order', fn ($q) => $q->where('user_id', $userId))->latest('id')->paginate(20),
            'walletPayments' => Order::where('user_id', $userId)->where('wallet_applied_minor', '>', 0)->latest('id')->limit(20)->get(['id', 'number', 'wallet_applied_minor', 'paid_at', 'created_at']),
        ]);
    }

    public function invoices(Request $request): View
    {
        return view('dashboard.invoices', ['invoices' => $request->user()->invoices()->latest()->paginate(15)]);
    }
}
