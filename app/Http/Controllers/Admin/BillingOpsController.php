<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Billing operations: overdue invoices, payment reconciliation and the billing audit trail.
 * Read-only except "Run reconciliation", which settles payments the gateway reports as captured through the same
 * idempotent path as webhooks (never double-credits). With no gateway configured it contacts nothing.
 */
class BillingOpsController extends Controller
{
    public const TABS = ['overdue' => 'Overdue invoices', 'reconciliation' => 'Payment reconciliation', 'audit' => 'Billing audit log'];

    public const AUDIT_PREFIXES = ['billing.', 'order.', 'wallet.', 'invoice.', 'payment.', 'webhook.', 'admin.addons.', 'admin.plan', 'settings.updated'];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('invoices.view'), 403);
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'overdue';
        $data = ['tab' => $tab];

        if ($tab === 'overdue') {
            $data['invoices'] = Invoice::with('user')->where('type', 'invoice')->where('status', 'unpaid')
                ->whereNotNull('due_at')->where('due_at', '<', now())->orderBy('due_at')->paginate(25)->withQueryString();
            $data['dueSoon'] = Invoice::where('type', 'invoice')->where('status', 'unpaid')->whereBetween('due_at', [now(), now()->addDays(7)])->count();
        } elseif ($tab === 'reconciliation') {
            abort_unless($request->user()->hasPermission('payments.view'), 403);
            $data['stale'] = PaymentAttempt::with('order')->where('status', 'created')->where('created_at', '<', now()->subMinutes(10))->latest('id')->limit(50)->get();
            $data['mismatch'] = PaymentAttempt::with('order')->where('status', 'amount_mismatch')->latest('id')->limit(50)->get();
            // Verified money whose order is still not marked paid would indicate a fulfilment gap.
            $data['unsettled'] = PaymentAttempt::with('order')->where('status', 'verified')->whereHas('order', fn ($q) => $q->whereNull('paid_at'))->latest('id')->limit(50)->get();
            $data['paidWithoutInvoice'] = Order::whereNotNull('paid_at')->whereDoesntHave('invoices')->latest('id')->limit(50)->get();
        } else {
            abort_unless($request->user()->hasPermission('security_logs.view') || $request->user()->hasPermission('invoices.export'), 403);
            $data['logs'] = AuditLog::with('user')->where(function ($q) {
                foreach (self::AUDIT_PREFIXES as $prefix) {
                    $q->orWhere('action', 'like', $prefix.'%');
                }
            })->latest('id')->paginate(40)->withQueryString();
        }

        return view('admin.billing.ops', $data);
    }

    public function reconcile(Request $request, PaymentService $payments): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('payments.view') && $request->user()->hasPermission('orders.edit'), 403);
        $settled = $payments->reconcile();
        Audit::log('payment.reconcile_run', null, ['settled' => $settled]);

        return back()->with('success', "Reconciliation finished: {$settled} payment(s) settled. Gateways that are not configured were skipped.");
    }
}
