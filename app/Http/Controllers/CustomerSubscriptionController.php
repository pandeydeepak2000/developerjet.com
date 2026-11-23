<?php

namespace App\Http\Controllers;

use App\Models\CancellationRequest;
use App\Models\Subscription;
use App\Services\Billing\LifecycleService;
use App\Support\BillingSettings;
use App\Support\SubscriptionStatus as S;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Customer view of their own recurring services: paid-through dates, renewals, add-ons, cancellation and history. */
class CustomerSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $base = Subscription::forCustomer($request->user())
            ->with(['service', 'plan', 'invoices' => fn ($q) => $q->where('type', 'invoice')->latest('id')->limit(3)]);

        return view('dashboard.subscriptions', [
            'subscriptions' => (clone $base)->whereNotIn('status', [S::CANCELLED, S::EXPIRED])->orderBy('next_due_at')->paginate(15),
            'ended' => (clone $base)->whereIn('status', [S::CANCELLED, S::EXPIRED])->latest('updated_at')->limit(50)->get(),
        ]);
    }

    public function show(Request $request, Subscription $subscription): View
    {
        abort_unless($subscription->user_id === $request->user()->id, 404); // isolation: never reveal another customer's record

        return view('dashboard.subscription-show', [
            'sub' => $subscription->load('service', 'plan', 'invoices'),
            'events' => $subscription->events()->whereIn('type', ['status', 'invoice', 'renewal'])->limit(30)->get(),
            'addons' => $subscription->addons()->where('status', 'active')->get(),
            'renewals' => $subscription->invoices()->where('type', 'invoice')->orderByDesc('period_start')->get(),
            'cancellation' => $subscription->cancellationRequests()->whereIn('status', ['pending', 'approved'])->first(),
            'lastCancellation' => $subscription->cancellationRequests()->first(),
            'canCancel' => BillingSettings::cancellationEnabled() && ! in_array($subscription->status, [S::CANCELLED, S::EXPIRED], true),
            'allowImmediate' => BillingSettings::immediateCancellationAllowed(),
            'needsApproval' => BillingSettings::cancellationNeedsApproval(),
        ]);
    }

    public function requestCancellation(Request $request, Subscription $subscription, LifecycleService $lifecycle): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'type' => ['required', 'in:end_of_period,immediate'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ]);

        try {
            $req = $lifecycle->requestCancellation($subscription, $request->user(), $data['type'], $data['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', match ($req->status) {
            'approved' => 'Cancellation confirmed. Your service stays active until '.$req->effective_at?->format('d M Y').' and will not renew.',
            'completed' => 'Your subscription has been cancelled.',
            default => 'Cancellation request received. Our team will review it and email you.',
        });
    }

    public function withdrawCancellation(Request $request, Subscription $subscription, CancellationRequest $cancellation, LifecycleService $lifecycle): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id && $cancellation->subscription_id === $subscription->id, 404);

        try {
            $lifecycle->withdraw($cancellation, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cancellation withdrawn. Your subscription will renew as normal.');
    }
}
