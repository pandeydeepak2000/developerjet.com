<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\InsufficientFundsException;
use App\Services\TaxService;
use App\Services\WalletService;
use App\Support\Audit;
use App\Support\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __construct(private WalletService $wallets, private TaxService $taxes) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('wallet.view'), 403);

        $wallets = Wallet::with('user')->when(trim((string) $request->query('q')), fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where('email', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderByDesc('balance_minor')->paginate(10, ['*'], 'wallets')->withQueryString();
        $historySearch = trim((string) $request->query('tx_q'));
        $history = WalletTransaction::with(['wallet.user', 'actor'])->when($historySearch, fn ($q) => $q->where('reference', 'like', '%'.$historySearch.'%')->orWhereHas('wallet.user', fn ($u) => $u->where('email', 'like', '%'.$historySearch.'%')))->latest('id')->paginate(10, ['*'], 'history')->withQueryString();

        return view('admin.wallet.index', [
            'topups' => TopupRequest::with('user')->orderByRaw("status = 'pending' desc")->latest('id')->paginate(10, ['*'], 'topups'),
            'wallets' => $wallets,
            'history' => $history,
        ]);
    }

    /** Credit a verified top-up exactly once (row lock + status check + unique ledger reference). */
    public function approve(Request $request, TopupRequest $topup): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('wallet.approve'), 403);
        $request->validate(['review_note' => ['nullable', 'string', 'max:250']]);

        $credited = DB::transaction(function () use ($request, $topup) {
            $locked = TopupRequest::whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                return false;
            }

            $class = (string) Setting::get('wallet.tax_class', 'wallet_topup');
            $this->wallets->credit($locked->user, $locked->amount_minor, 'topup', "topup:{$locked->id}", 'Wallet top-up (verified by staff)', $request->user(), [
                'topup_request_id' => $locked->id,
                'tax_class' => $class,
                'tax_configured' => $this->taxes->configured($class),
                'tax_minor' => $this->taxes->compute($locked->amount_minor, $class),
            ]);
            $locked->update(['status' => 'credited', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->input('review_note')]);

            return true;
        });

        Audit::log($credited ? 'wallet.topup_credited' : 'wallet.topup_duplicate_blocked', $topup);

        return back()->with($credited ? 'success' : 'error', $credited ? 'Wallet credited.' : 'This request was already processed.');
    }

    public function reject(Request $request, TopupRequest $topup): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('wallet.approve'), 403);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:250']]);

        $updated = TopupRequest::whereKey($topup->id)->where('status', 'pending')
            ->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note']]);
        Audit::log('wallet.topup_rejected', $topup);

        return back()->with($updated ? 'success' : 'error', $updated ? 'Request rejected.' : 'This request was already processed.');
    }

    /** Manual ledger adjustment with a mandatory Transaction ID; always audit-logged. */
    public function adjust(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('wallet.create'), 403);
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:250'],
            'transaction_id' => ['required', 'string', 'min:3', 'max:120'],
        ]);

        $customer = User::where('email', $data['email'])->where('role', 'customer')->firstOrFail();
        $amount = (int) round($data['amount'] * 100);
        $transactionId = trim($data['transaction_id']);
        $reference = 'adjust:'.$transactionId;

        if (WalletTransaction::where('reference', $reference)->exists()) {
            return back()->withErrors(['transaction_id' => 'This Transaction ID is already used for a wallet adjustment.'])->withInput();
        }

        try {
            $tx = $data['type'] === 'credit'
                ? $this->wallets->credit($customer, $amount, 'admin_adjustment', $reference, $data['reason'], $request->user(), ['transaction_id' => $transactionId])
                : $this->wallets->debit($customer, $amount, 'admin_adjustment', $reference, $data['reason'], $request->user(), ['transaction_id' => $transactionId]);
        } catch (InsufficientFundsException $e) {
            return back()->with('error', $e->getMessage());
        }

        Audit::log('wallet.adjustment', $tx, ['type' => $data['type'], 'amount_minor' => $amount, 'customer' => $customer->id, 'reason' => $data['reason'], 'transaction_id' => $transactionId]);

        return back()->with('success', 'Adjustment recorded.');
    }
}
