<?php

namespace App\Http\Controllers;

use App\Models\TopupRequest;
use App\Services\Notifier;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Manual add-funds: the customer only *requests*; an admin credits after independently verifying the funds. */
class WalletTopupController extends Controller
{
    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Throttle open requests so the queue cannot be flooded.
        if (TopupRequest::where('user_id', $request->user()->id)->where('status', 'pending')->count() >= 5) {
            return back()->with('error', 'You already have several pending top-up requests. Please wait for the team to review them.');
        }

        $topup = TopupRequest::create([
            'user_id' => $request->user()->id,
            'amount_minor' => (int) round($data['amount'] * 100),
            'payment_reference' => $data['payment_reference'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        Audit::log('wallet.topup_requested', $topup);
        $notifier->admin('wallet_topup_request', "topup:{$topup->id}", "Wallet top-up request #{$topup->id}: ".Money::inr($topup->amount_minor).' from '.$request->user()->email);

        return back()->with('success', 'Top-up request submitted. Funds are credited only after our team verifies your payment.');
    }
}
