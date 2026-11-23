<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendRenewalReminder;
use App\Models\Addon;
use App\Models\BillingAction;
use App\Models\CancellationRequest;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionReminder;
use App\Services\Billing\LifecycleService;
use App\Services\Billing\SubscriptionService;
use App\Services\RenewalService;
use App\Support\Audit;
use App\Support\BillingSettings;
use App\Support\SubscriptionStatus as S;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Admin → Billing: renewal queues and per-subscription actions. Every action checks its own permission on the
 * server (hidden buttons are not the protection). Nothing here touches real infrastructure: "confirm suspended" /
 * "confirm reactivated" record what staff did by hand; adapter actions run only when a verified adapter exists and
 * live suspension is enabled in Settings.
 */
class BillingController extends Controller
{
    public const TABS = [
        'upcoming' => 'Upcoming renewals', 'overdue' => 'Overdue & grace', 'suspension' => 'Suspension queue',
        'reactivation' => 'Reactivation queue', 'cancellations' => 'Cancellation requests', 'failed' => 'Failed renewals',
        'ended' => 'Cancelled & expired', 'reminders' => 'Reminder log', 'all' => 'All subscriptions',
    ];

    public function __construct(private SubscriptionService $subs, private LifecycleService $lifecycle) {}

    public function index(Request $request): View
    {
        $this->allow($request, 'subscriptions.view');
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'upcoming';
        $term = trim((string) $request->query('q'));

        $query = Subscription::with('user', 'service', 'plan')
            ->when($term !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('email', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('name', 'like', '%'.addcslashes($term, '%_\\').'%')));

        $rows = match ($tab) {
            'upcoming' => $query->whereIn('status', [S::ACTIVE, S::RENEWAL_DUE])->where('next_due_at', '<=', now()->addDays(30))->orderBy('next_due_at'),
            'overdue' => $query->whereIn('status', [S::GRACE, S::OVERDUE, S::LEGACY_PAST_DUE])->orderBy('next_due_at'),
            'suspension' => $query->whereIn('status', [S::SUSPENSION_PENDING, S::SUSPENDED])->orderBy('next_due_at'),
            'reactivation' => $query->where('status', S::REACTIVATION_PENDING)->orderBy('updated_at'),
            'failed' => $query->where('renewal_failures', '>', 0)->orderByDesc('last_renewal_failed_at'),
            'ended' => $query->whereIn('status', [S::CANCELLED, S::EXPIRED])->latest('updated_at'),
            'all' => $query->latest('id'),
            default => null,
        };

        return view('admin.billing.index', [
            'tab' => $tab,
            'rows' => $rows?->paginate(20)->withQueryString(),
            'reminders' => $tab === 'reminders'
                ? SubscriptionReminder::with('subscription.user', 'invoice')->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(25)->withQueryString()
                : null,
            'cancellations' => $tab === 'cancellations'
                ? CancellationRequest::with('subscription.plan', 'user', 'reviewer')->when($request->query('status', 'pending') !== 'all', fn ($q) => $q->where('status', $request->query('status', 'pending')))->latest('id')->paginate(25)->withQueryString()
                : null,
            'counts' => [
                'cancellations' => CancellationRequest::where('status', 'pending')->count(),
                'failed' => Subscription::where('renewal_failures', '>', 0)->count(),
                'overdue' => Subscription::whereIn('status', [S::GRACE, S::OVERDUE, S::LEGACY_PAST_DUE])->count(),
                'suspension' => Subscription::where('status', S::SUSPENSION_PENDING)->count(),
                'reactivation' => Subscription::where('status', S::REACTIVATION_PENDING)->count(),
                'failed_reminders' => SubscriptionReminder::where('status', 'failed')->count(),
            ],
            'openActions' => BillingAction::where('status', 'open')->count(),
            'settings' => [
                'invoice_days' => BillingSettings::invoiceDaysBefore(), 'offsets' => BillingSettings::beforeOffsets(), 'due_day' => BillingSettings::dueDayReminder(),
                'overdue_offsets' => BillingSettings::overdueOffsets(), 'grace' => BillingSettings::graceDays(), 'auto_suspend' => BillingSettings::autoSuspend(),
                'approval' => BillingSettings::suspensionNeedsApproval(), 'live' => BillingSettings::liveSuspension(), 'reminders' => BillingSettings::remindersEnabled(),
            ],
        ]);
    }

    public function show(Request $request, Subscription $subscription): View
    {
        $this->allow($request, 'subscriptions.view');

        return view('admin.billing.show', [
            'sub' => $subscription->load('user', 'service.server', 'plan', 'invoices', 'order'),
            'events' => $subscription->events()->with('actor')->limit(50)->get(),
            'reminders' => $subscription->reminders()->limit(30)->get(),
            'actions' => $subscription->actions()->with('resolver')->limit(20)->get(),
            'addons' => $subscription->addons()->latest('id')->get(),
            'availableAddons' => Addon::where('is_active', true)->where('billing_type', 'recurring')->orderBy('name')->get(),
            'cancellations' => $subscription->cancellationRequests()->with('reviewer')->get(),
            'renewalInvoices' => $subscription->invoices()->where('type', 'invoice')->orderByDesc('period_start')->get(),
            'canEdit' => $request->user()->hasPermission('subscriptions.edit'),
            'canPrice' => $request->user()->hasPermission('subscriptions.edit') && $request->user()->hasPermission('plans.approve'),
        ]);
    }

    public function action(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->allow($request, 'subscriptions.edit');
        $data = $request->validate([
            'action' => ['required', 'in:invoice,exempt,unexempt,request_suspension,suspend_adapter,confirm_suspended,reactivate_adapter,confirm_reactivated,cancel,price'],
            'note' => ['nullable', 'string', 'max:300'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'confirm' => ['nullable', 'boolean'],
        ]);
        $actor = $request->user();
        $note = trim((string) ($data['note'] ?? ''));

        try {
            switch ($data['action']) {
                case 'invoice':
                    $invoice = $this->subs->ensureRenewalInvoice($subscription);
                    if ($invoice && $subscription->fresh()->status === S::ACTIVE) {
                        $this->subs->transition($subscription->fresh(), S::RENEWAL_DUE, 'Renewal invoice issued by staff.', $actor);
                    }
                    $msg = $invoice ? "Renewal invoice {$invoice->number} is ready." : 'No invoice: this subscription has no agreed or approved price.';
                    break;
                case 'exempt':
                case 'unexempt':
                    $this->subs->setExempt($subscription, $data['action'] === 'exempt', $actor);
                    $msg = $data['action'] === 'exempt' ? 'Exempted from automatic suspension.' : 'Exemption removed.';
                    break;
                case 'request_suspension':
                    $this->requireConfirm($data);
                    $this->subs->beginSuspension($subscription, $note ?: 'Suspension requested by staff.');
                    $msg = 'Suspension queued. Suspend the service on the provider, then confirm here.';
                    break;
                case 'suspend_adapter':
                    $this->requireConfirm($data);
                    $msg = $this->subs->suspendNow($subscription, $actor, 'Suspended via verified adapter by staff.')
                        ? 'Service suspended by the verified adapter.' : 'No verified adapter (or it failed): suspend manually, then use “Confirm suspended”.';
                    break;
                case 'confirm_suspended':
                    $this->requireConfirm($data);
                    $this->subs->suspendNow($subscription, $actor, 'Staff confirmed the service was suspended manually.'.($note ? ' '.$note : ''), true);
                    $msg = 'Recorded as suspended. Customer data was not deleted.';
                    break;
                case 'reactivate_adapter':
                    $msg = $this->subs->attemptReactivation($subscription, $actor)
                        ? 'Reactivated and health-checked by the verified adapter.' : 'No verified adapter (or it failed): reactivate manually, then confirm.';
                    break;
                case 'confirm_reactivated':
                    $this->requireConfirm($data);
                    $this->subs->confirmReactivated($subscription, $actor, 'Staff confirmed the service works again.'.($note ? ' '.$note : ''));
                    $msg = 'Marked active.';
                    break;
                case 'cancel':
                    $this->requireConfirm($data);
                    $this->subs->cancel($subscription, $actor, $note ?: 'Cancelled by staff.');
                    $msg = 'Subscription cancelled. The service and its data were not deleted.';
                    break;
                case 'price':
                    $this->allow($request, 'plans.approve');
                    abort_if(! isset($data['price']) || $note === '', 422, 'A new price and a reason are required.');
                    $this->subs->changeAgreedPrice($subscription, (int) round($data['price'] * 100), $actor, $note);
                    $msg = 'Agreed price updated. It applies from the next renewal invoice; issued invoices are unchanged.';
                    break;
            }
        } catch (InvalidArgumentException $e) {
            return back()->with('error', 'Not allowed in the current status: '.$e->getMessage());
        }

        Audit::log('billing.admin.'.$data['action'], $subscription, [], $actor->id);

        return back()->with('success', $msg ?? 'Done.');
    }

    /** Approve or reject a customer cancellation request. */
    public function reviewCancellation(Request $request, CancellationRequest $cancellation): RedirectResponse
    {
        $this->allow($request, 'subscriptions.edit');
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:500']]);

        try {
            if ($data['decision'] === 'approve') {
                $this->lifecycle->approve($cancellation, $request->user(), $data['note'] ?? null);
                $msg = $cancellation->type === 'immediate' ? 'Cancelled now. A retention-review task was created; nothing was deleted.' : 'Approved: the subscription ends at the end of its paid period and will not renew.';
            } else {
                abort_if(trim((string) ($data['note'] ?? '')) === '', 422, 'Add a note for the customer explaining why.');
                $this->lifecycle->reject($cancellation, $request->user(), $data['note']);
                $msg = 'Request rejected; the customer was notified.';
            }
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $msg);
    }

    /** Re-run renewal processing for one subscription now (after fixing the cause of a failure). */
    public function retryRenewal(Request $request, Subscription $subscription, RenewalService $renewals): RedirectResponse
    {
        $this->allow($request, 'subscriptions.edit');
        $stats = ['invoices' => 0, 'reminded' => 0, 'overdue' => 0, 'suspension_pending' => 0, 'errors' => 0, 'orders' => 0];

        try {
            if (in_array($subscription->status, S::OPEN_STATES, true) && $subscription->next_due_at) {
                $renewals->process($subscription, now(), $stats);
            }
            $this->lifecycle->clearFailures($subscription);
        } catch (\Throwable $e) {
            $this->lifecycle->recordFailure($subscription, $e);

            return back()->with('error', 'Renewal processing failed again: '.class_basename($e).'. Details are in the subscription record and the log.');
        }
        Audit::log('billing.renewal.retried', $subscription, $stats, $request->user()->id);

        return back()->with('success', 'Renewal processing completed'.($stats['invoices'] ? ' — renewal invoice issued.' : '.'));
    }

    /** Add a recurring add-on at an explicitly agreed price (pricing authority required, like agreed-price changes). */
    public function addAddon(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->allow($request, 'subscriptions.edit');
        $this->allow($request, 'plans.approve');
        $data = $request->validate([
            'addon_id' => ['required', 'integer', 'exists:addons,id'], 'price' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ]);
        $addon = Addon::where('is_active', true)->where('billing_type', 'recurring')->findOrFail($data['addon_id']);

        try {
            $this->lifecycle->addAddon($subscription, $addon, (int) round($data['price'] * 100), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $addon->name.' added. It is billed from the next renewal invoice.');
    }

    public function removeAddon(Request $request, Subscription $subscription, SubscriptionAddon $addon): RedirectResponse
    {
        $this->allow($request, 'subscriptions.edit');
        abort_unless($addon->subscription_id === $subscription->id, 404);
        $this->lifecycle->cancelAddon($addon, $request->user());

        return back()->with('success', $addon->name.' will not be billed on future renewals.');
    }

    public function retryReminder(Request $request, SubscriptionReminder $reminder): RedirectResponse
    {
        $this->allow($request, 'subscriptions.edit');
        abort_unless($reminder->status === 'failed', 422, 'Only failed reminders can be retried.');

        $reminder->update(['status' => 'pending', 'error' => null]);
        SendRenewalReminder::dispatch($reminder->id);
        Audit::log('billing.reminder.retry', $reminder->subscription, ['reminder_id' => $reminder->id], $request->user()->id);

        return back()->with('success', 'Reminder queued again.');
    }

    private function requireConfirm(array $data): void
    {
        abort_unless(! empty($data['confirm']), 422, 'Please tick the confirmation box.');
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403, 'You do not have permission to do that.');
    }
}
