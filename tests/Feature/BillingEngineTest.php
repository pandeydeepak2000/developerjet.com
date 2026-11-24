<?php

namespace Tests\Feature;

use App\Jobs\SendRenewalReminder;
use App\Mail\TemplatedMail;
use App\Models\BillingAction;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionReminder;
use App\Models\User;
use App\Services\Billing\ServiceSuspender;
use App\Services\Billing\SubscriptionService;
use App\Services\OrderService;
use App\Services\RenewalService;
use App\Services\WalletService;
use App\Support\BillingCycle;
use App\Support\Setting;
use App\Support\SubscriptionStatus as S;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class BillingEngineTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- calendar maths

    public function test_month_end_and_leap_year_dates_keep_the_anchor_day(): void
    {
        $jan31 = Carbon::parse('2028-01-31 10:00');
        $feb = BillingCycle::advance($jan31, 'monthly');
        $this->assertSame('2028-02-29', $feb->toDateString()); // leap year
        $this->assertSame('2028-03-31', BillingCycle::advance($feb, 'monthly', 31)->toDateString()); // no drift to the 29th
        $this->assertSame('2027-02-28', BillingCycle::advance(Carbon::parse('2027-01-31'), 'monthly')->toDateString());

        $this->assertSame('2028-02-29', BillingCycle::advance(Carbon::parse('2027-11-30'), 'quarterly', 30)->toDateString());
        $this->assertSame('2028-05-30', BillingCycle::advance(Carbon::parse('2028-02-29'), 'quarterly', 30)->toDateString());
        $this->assertSame('2028-02-29', BillingCycle::advance(Carbon::parse('2027-08-31'), 'semiannual')->toDateString());
        $this->assertSame('2029-02-28', BillingCycle::advance(Carbon::parse('2028-02-29'), 'yearly')->toDateString());
        $this->assertSame('2032-02-29', BillingCycle::advance(Carbon::parse('2031-02-28'), 'yearly', 29)->toDateString());
        $this->assertSame('10:00:00', $feb->format('H:i:s'));
    }

    public function test_four_cycles_map_to_months_and_labels(): void
    {
        $this->assertSame(['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'yearly' => 12], BillingCycle::MONTHS);
        $this->assertSame(Plan::INTERVALS, BillingCycle::keys());
        $this->assertSame('Billed every 3 months', BillingCycle::billedPhrase('quarterly'));
    }

    // ---------------------------------------------------------------- admin plan pricing

    public function test_admin_sets_four_independent_periods_and_only_approvers_can_approve(): void
    {
        $plan = Plan::where('slug', 'hermes-cli-launch')->firstOrFail();
        $payload = fn (array $extra = []) => [
            'product_category_id' => $plan->product_category_id, 'name' => $plan->name, 'slug' => $plan->slug, 'tax_class' => 'standard',
            'visibility' => 'public', 'kind' => 'hosting', 'fulfilment' => 'provisioned', 'price_status' => 'approved', 'is_active' => 1, 'is_purchasable' => 1,
            'periods' => [
                'monthly' => ['price' => 1000, 'available' => 1, 'approved' => 1],
                'quarterly' => ['price' => 2850, 'discount' => 5, 'setup' => 0, 'available' => 1, 'approved' => 1],
                'semiannual' => ['price' => 5500, 'setup' => 250, 'available' => 1, 'approved' => 1],
                'yearly' => ['price' => 10000, 'available' => 0, 'approved' => 1],
            ],
        ] + $extra;

        $this->actingAs($this->staff())->put(route('admin.plans.update', $plan), $payload())->assertRedirect();
        $plan = $plan->fresh('prices');

        $this->assertSame(285000, $plan->chargeablePriceFor('quarterly'));
        $this->assertSame(5, $plan->pricing('quarterly')['discount_percent']);
        $this->assertSame(25000, $plan->pricing('semiannual')['setup_minor']);
        $this->assertNull($plan->chargeablePriceFor('yearly'), 'unavailable period is not chargeable');
        $this->assertSame(['monthly', 'quarterly', 'semiannual'], $plan->cartIntervals());

        // An editor without plans.approve changing a price puts that period back to pending.
        $payload2 = $payload();
        $payload2['periods']['quarterly']['price'] = 2500;
        $this->actingAs($this->staff('admin'))->put(route('admin.plans.update', $plan), $payload2)->assertRedirect();
        $plan = $plan->fresh('prices');
        $this->assertSame('pending', $plan->prices->firstWhere('billing_interval', 'quarterly')->approval_status);
        $this->assertNull($plan->chargeablePriceFor('quarterly'));
        $this->assertSame(100000, $plan->chargeablePriceFor('monthly'), 'unchanged periods keep their approval');

        $this->actingAs($this->staff())->get(route('admin.plans.edit', $plan))->assertOk()
            ->assertSee('Quarterly (3 months)')->assertSee('Semiannual (6 months)')->assertSee('Annual (12 months)')->assertSee('name="periods[quarterly][price]"', false);
    }

    public function test_quarterly_purchase_snapshots_price_and_charges_setup_only_once(): void
    {
        $plan = $this->quarterlyPlan(setupRupees: 500);
        [$user, $order] = $this->buy($plan, 'quarterly');

        $sub = Subscription::firstOrFail();
        $this->assertSame('quarterly', $sub->billing_interval);
        $this->assertSame(300000, $sub->price_minor);
        $this->assertSame(BillingCycle::advance($sub->period_start, 'quarterly')->toDateString(), $sub->period_end->toDateString());
        $this->assertSame(50000, $order->items()->whereNotNull('plan_id')->value('setup_minor'));

        // Owner later changes the list price: the customer's agreed price is kept.
        $plan->prices()->where('billing_interval', 'quarterly')->update(['amount_minor' => 999900]);
        $sub->update(['next_due_at' => now()->addDays(5)]);
        app(RenewalService::class)->run();

        $invoice = Invoice::where('subscription_id', $sub->id)->firstOrFail();
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(300000, $invoice->subtotal_minor, 'agreed price, no setup fee on renewal');
        $this->assertSame('quarterly', $invoice->billing_interval);
        $this->assertSame(0, Order::find($invoice->order_id)->items()->whereNotNull('plan_id')->value('setup_minor'));
    }

    // ---------------------------------------------------------------- renewal invoices

    public function test_invoice_is_issued_seven_days_before_due_and_timing_is_configurable(): void
    {
        [, , $sub] = $this->subscribed();
        $sub->update(['next_due_at' => now()->addDays(8)]);
        app(RenewalService::class)->run();
        $this->assertSame(0, Invoice::where('subscription_id', $sub->id)->count());

        $sub->update(['next_due_at' => now()->addDays(7)->addHour()]);
        app(RenewalService::class)->run();
        $this->assertSame(1, Invoice::where('subscription_id', $sub->id)->count());
        $this->assertSame(S::RENEWAL_DUE, $sub->fresh()->status);

        [, , $sub2] = $this->subscribed('second@example.test');
        Setting::set('billing.invoice_days_before', 14);
        $sub2->update(['next_due_at' => now()->addDays(13)]);
        app(RenewalService::class)->run();
        $this->assertSame(1, Invoice::where('subscription_id', $sub2->id)->count());
    }

    public function test_renewal_invoice_is_unique_per_period_with_existing_numbering(): void
    {
        Setting::set('invoice.prefix', 'AUN');
        [, , $sub] = $this->subscribed();
        $sub->update(['next_due_at' => now()->addDays(3)]);

        app(RenewalService::class)->run();
        app(RenewalService::class)->run();
        app(SubscriptionService::class)->ensureRenewalInvoice($sub->fresh());

        $invoices = Invoice::where('subscription_id', $sub->id)->get();
        $this->assertCount(1, $invoices);
        $this->assertStringStartsWith('AUN', $invoices->first()->number);
        $this->assertSame(1, Order::where('snapshot->renewal_subscription_id', $sub->id)->count());

        // Database constraint as the last line of defence.
        $this->expectException(UniqueConstraintViolationException::class);
        Invoice::create(['user_id' => $sub->user_id, 'number' => 'DUP1', 'type' => 'invoice', 'status' => 'unpaid', 'currency' => 'INR',
            'total_minor' => 1, 'subscription_id' => $sub->id, 'period_start' => $invoices->first()->period_start]);
    }

    public function test_renewal_invoice_appears_in_dashboard_with_pdf_and_is_not_marked_paid(): void
    {
        [$user, , $sub] = $this->subscribed();
        $sub->update(['next_due_at' => now()->addDays(2)]);
        app(RenewalService::class)->run();
        $invoice = Invoice::where('subscription_id', $sub->id)->firstOrFail();

        $this->assertNull($invoice->paid_at);
        $this->actingAs($user)->get(route('dashboard.invoices'))->assertOk()->assertSee($invoice->number)->assertSee('Renew now')->assertSee('Unpaid');
        $this->actingAs($user)->get(route('dashboard.subscriptions'))->assertOk()->assertSee('Renew now')->assertSee('Renewal Due');
        $pdf = $this->actingAs($user)->get(route('dashboard.invoices.download', $invoice));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    // ---------------------------------------------------------------- reminders

    public function test_seven_and_three_day_reminders_are_sent_once_each(): void
    {
        [, , $sub] = $this->subscribed();
        $due = now()->addDays(7)->startOfDay()->addHours(12);
        $sub->update(['next_due_at' => $due]);

        app(RenewalService::class)->run(now());
        app(RenewalService::class)->run(now()); // same day re-run
        $this->assertSame(['before:7'], SubscriptionReminder::where('status', 'sent')->pluck('kind')->all());

        app(RenewalService::class)->run($due->copy()->subDays(3)->startOfDay()->addHours(8));
        app(RenewalService::class)->run($due->copy()->subDays(3)->startOfDay()->addHours(9));
        $this->assertEqualsCanonicalizing(['before:7', 'before:3'], SubscriptionReminder::where('status', 'sent')->pluck('kind')->all());
        Mail::assertSent(TemplatedMail::class, 2);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->template === 'renewal_reminder'
            && str_contains(TemplatedMail::renderTemplate($m->template, $m->vars)[1], $m->vars['invoice'])
            && $m->vars['amount'] !== '' && str_contains($m->vars['url'], '/dashboard/orders/'));
    }

    public function test_custom_offsets_due_day_overdue_reminders_and_late_runs(): void
    {
        Setting::set('billing.reminder_offsets', '10,5,1');
        Setting::set('billing.invoice_days_before', 10);
        Setting::set('billing.overdue_offsets', '2');
        [, , $sub] = $this->subscribed();
        $due = now()->addDays(10)->startOfDay()->addHours(12);
        $sub->update(['next_due_at' => $due]);

        // The scheduler was down until 1 day before: only the most recent reminder is sent, older ones are skipped.
        app(RenewalService::class)->run($due->copy()->subDay()->startOfDay()->addHours(8));
        $this->assertSame('sent', SubscriptionReminder::where('kind', 'before:1')->value('status'));
        $this->assertSame(['skipped', 'skipped'], SubscriptionReminder::whereIn('kind', ['before:10', 'before:5'])->pluck('status')->all());

        app(RenewalService::class)->run($due->copy()->startOfDay()->addHours(8));
        $this->assertSame('sent', SubscriptionReminder::where('kind', 'due')->value('status'));

        app(RenewalService::class)->run($due->copy()->addDays(1));
        $this->assertNull(SubscriptionReminder::where('kind', 'overdue:2')->first());
        app(RenewalService::class)->run($due->copy()->addDays(2)->addHour());
        $this->assertSame('sent', SubscriptionReminder::where('kind', 'overdue:2')->value('status'));
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->template === 'renewal_overdue');
    }

    public function test_reminders_can_be_disabled(): void
    {
        Setting::set('billing.reminders_enabled', '0');
        [, , $sub] = $this->subscribed();
        $sub->update(['next_due_at' => now()->addDays(3)]);
        app(RenewalService::class)->run();

        $this->assertSame(1, Invoice::where('subscription_id', $sub->id)->count(), 'invoices still issued');
        $this->assertSame(0, SubscriptionReminder::count());
        Mail::assertNothingSent();
    }

    public function test_failed_reminder_is_logged_alerted_and_can_be_retried_by_staff(): void
    {
        [, , $sub] = $this->subscribed();
        $sub->update(['next_due_at' => now()->addDays(3)]);
        app(RenewalService::class)->run();
        $reminder = SubscriptionReminder::firstOrFail();

        // Simulate a delivery failure on the queue worker.
        $reminder->update(['status' => 'pending']);
        (new SendRenewalReminder($reminder->id))->failed(new RuntimeException('SMTP 421 try later'));
        $this->assertSame('failed', $reminder->fresh()->status);
        $this->assertStringContainsString('SMTP 421', $reminder->fresh()->error);
        $this->assertTrue(NotificationLog::where('event', 'reminder_failed')->exists());

        $this->actingAs($this->staff('billing-staff'))->post(route('admin.billing.reminders.retry', $reminder))->assertForbidden();

        Queue::fake();
        $this->actingAs($this->staff())->post(route('admin.billing.reminders.retry', $reminder))->assertSessionHas('success');
        Queue::assertPushed(SendRenewalReminder::class, fn ($job) => $job->reminderId === $reminder->id);
        $this->assertSame('pending', $reminder->fresh()->status);
    }

    // ---------------------------------------------------------------- overdue, grace, suspension

    public function test_grace_period_then_overdue_without_auto_suspension_alerts_staff_only(): void
    {
        [, $service, $sub] = $this->subscribed();
        $due = now()->addDays(2);
        $sub->update(['next_due_at' => $due]);
        app(RenewalService::class)->run();

        app(RenewalService::class)->run($due->copy()->addHour());
        $sub->refresh();
        $this->assertSame(S::GRACE, $sub->status);
        $this->assertSame($due->copy()->addDays(3)->toDateString(), $sub->grace_ends_at->toDateString());

        app(RenewalService::class)->run($due->copy()->addDays(3)->addHour());
        $this->assertSame(S::OVERDUE, $sub->fresh()->status);
        $this->assertSame(0, BillingAction::count(), 'auto-suspension is OFF by default');
        $this->assertNotSame('suspended', $service->fresh()->status);
        $this->assertTrue(NotificationLog::where('event', 'invoice_due')->exists());
    }

    public function test_auto_suspension_queues_a_staff_task_per_service_and_never_suspends_without_adapter(): void
    {
        Setting::set('billing.auto_suspend', '1');
        [$user, $service, $sub] = $this->subscribed();
        [, $otherService, $otherSub] = $this->subscribed('same-user-second@example.test', $user);
        $due = now()->addDays(1);
        $sub->update(['next_due_at' => $due]);
        $otherSub->update(['next_due_at' => now()->addDays(40)]);

        app(RenewalService::class)->run();
        app(RenewalService::class)->run($due->copy()->addDays(4));
        app(RenewalService::class)->run($due->copy()->addDays(4)->addHour());

        $this->assertSame(S::SUSPENSION_PENDING, $sub->fresh()->status);
        $this->assertSame(1, BillingAction::where('subscription_id', $sub->id)->where('type', 'suspend')->where('status', 'open')->count());
        $this->assertNotSame('suspended', $service->fresh()->status, 'no verified adapter: nothing physical happens');
        $this->assertSame(S::ACTIVE, $otherSub->fresh()->status, 'suspension is per service, not per account');
        $this->assertTrue(NotificationLog::where('event', 'suspension_pending')->exists());
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->template === 'suspension_warning');
    }

    public function test_exempt_subscription_is_not_queued_for_suspension(): void
    {
        Setting::set('billing.auto_suspend', '1');
        [, , $sub] = $this->subscribed();
        $sub->update(['next_due_at' => now()->addDay(), 'suspension_exempt' => true]);
        app(RenewalService::class)->run();
        app(RenewalService::class)->run(now()->addDays(6));

        $this->assertSame(S::OVERDUE, $sub->fresh()->status);
        $this->assertSame(0, BillingAction::count());
    }

    public function test_suspension_adapter_failure_keeps_pending_and_alerts(): void
    {
        $this->bindSuspender(supports: true, failSuspend: true);
        Setting::set('billing.auto_suspend', '1');
        Setting::set('billing.suspension_requires_approval', '0');
        Setting::set('billing.live_suspension_enabled', '1');
        [, $service, $sub] = $this->subscribed();
        $due = now()->addDay();
        $sub->update(['next_due_at' => $due]);
        app(RenewalService::class)->run();
        app(RenewalService::class)->run($due->copy()->addDays(4));
        app(RenewalService::class)->run($due->copy()->addDays(4)->addHour());

        $this->assertSame(S::SUSPENSION_PENDING, $sub->fresh()->status);
        $this->assertSame('active', $service->fresh()->status);
        $this->assertSame(1, BillingAction::where('type', 'suspend')->where('status', 'open')->count());
        $this->assertStringContainsString('Adapter suspension failed', BillingAction::first()->reason);
    }

    public function test_verified_adapter_suspends_only_when_live_mode_is_enabled(): void
    {
        $fake = $this->bindSuspender(supports: true);
        Setting::set('billing.auto_suspend', '1');
        Setting::set('billing.suspension_requires_approval', '0');
        [, $service, $sub] = $this->subscribed();
        $due = now()->addDay();
        $sub->update(['next_due_at' => $due]);
        app(RenewalService::class)->run();
        app(RenewalService::class)->run($due->copy()->addDays(5));
        $this->assertSame(S::SUSPENSION_PENDING, $sub->fresh()->status, 'live suspension is OFF by default');
        $this->assertSame(0, $fake->suspended);

        Setting::set('billing.live_suspension_enabled', '1');
        $this->actingAs($this->staff())->post(route('admin.billing.action', $sub), ['action' => 'suspend_adapter', 'confirm' => 1])->assertSessionHas('success');
        $this->assertSame(S::SUSPENDED, $sub->fresh()->status);
        $this->assertSame('suspended', $service->fresh()->status);
        $this->assertSame(1, $fake->suspended);
    }

    // ---------------------------------------------------------------- payment, reactivation, idempotency

    public function test_paying_while_suspended_requires_reactivation_before_active(): void
    {
        [$user, $service, $sub] = $this->suspendedSubscription();
        $order = Order::find(Invoice::where('subscription_id', $sub->id)->value('order_id'));
        $oldEnd = $sub->fresh()->period_end;

        $this->pay($user, $order);

        $sub->refresh();
        $this->assertSame(S::REACTIVATION_PENDING, $sub->status);
        $this->assertSame('suspended', $service->fresh()->status, 'never marked active before the action succeeds');
        $this->assertTrue($sub->period_end->gt($oldEnd));
        $this->assertSame('paid', Invoice::where('subscription_id', $sub->id)->value('status'));
        $this->assertSame(1, BillingAction::where('type', 'reactivate')->where('status', 'open')->count());
        $this->assertTrue(NotificationLog::where('event', 'reactivation_pending')->exists());

        $this->actingAs($this->staff())->post(route('admin.billing.action', $sub), ['action' => 'confirm_reactivated'])->assertStatus(422); // confirmation required
        $this->actingAs($this->staff())->post(route('admin.billing.action', $sub), ['action' => 'confirm_reactivated', 'confirm' => 1])->assertSessionHas('success');
        $this->assertSame(S::ACTIVE, $sub->fresh()->status);
        $this->assertSame('active', $service->fresh()->status);
        $this->assertSame(0, BillingAction::where('status', 'open')->count());
    }

    public function test_verified_adapter_reactivates_after_health_check_or_stays_pending(): void
    {
        Setting::set('billing.live_suspension_enabled', '1');
        $fake = $this->bindSuspender(supports: true, healthy: false);
        [$user, $service, $sub] = $this->suspendedSubscription();
        $this->pay($user, Order::find(Invoice::where('subscription_id', $sub->id)->value('order_id')));
        $this->assertSame(1, $fake->reactivated);
        $this->assertSame(S::REACTIVATION_PENDING, $sub->fresh()->status, 'failed health check keeps it pending');

        $fake->healthy = true;
        $this->actingAs($this->staff())->post(route('admin.billing.action', $sub), ['action' => 'reactivate_adapter'])->assertSessionHas('success');
        $this->assertSame(S::ACTIVE, $sub->fresh()->status);
        $this->assertSame('active', $service->fresh()->status);
    }

    public function test_paying_during_grace_extends_from_paid_through_date_and_is_idempotent(): void
    {
        [$user, , $sub] = $this->subscribed();
        $due = now()->addDays(2);
        $sub->update(['next_due_at' => $due, 'period_end' => $due, 'anchor_day' => $due->day]); // consistent: due == paid-through
        app(RenewalService::class)->run();
        app(RenewalService::class)->run($due->copy()->addDay());
        $this->assertSame(S::GRACE, $sub->fresh()->status);
        $invoice = Invoice::where('subscription_id', $sub->id)->firstOrFail();
        $order = Order::find($invoice->order_id);

        $this->pay($user, $order);
        app(OrderService::class)->markPaid($order->fresh(), null, 'wallet'); // duplicate callback
        app(OrderService::class)->applyWallet($order->fresh());

        $sub->refresh();
        $this->assertSame(S::ACTIVE, $sub->status);
        $this->assertSame($due->toDateString(), $sub->period_start->toDateString(), 'continuous: starts at the old due date');
        $this->assertSame(BillingCycle::advance($due, 'monthly', $sub->anchor_day)->toDateString(), $sub->period_end->toDateString());
        $this->assertSame(1, $sub->events()->where('type', 'renewal')->count());
        $this->assertSame($invoice->number, $invoice->fresh()->number);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());
    }

    public function test_paying_before_suspension_cancels_the_pending_task(): void
    {
        Setting::set('billing.auto_suspend', '1');
        [$user, , $sub] = $this->subscribed();
        $due = now()->addDay();
        $sub->update(['next_due_at' => $due]);
        app(RenewalService::class)->run();
        app(RenewalService::class)->run($due->copy()->addDays(5));
        $this->assertSame(S::SUSPENSION_PENDING, $sub->fresh()->status);

        $this->pay($user, Order::find(Invoice::where('subscription_id', $sub->id)->value('order_id')));
        $this->assertSame(S::ACTIVE, $sub->fresh()->status);
        $this->assertSame('cancelled', BillingAction::first()->status);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        [, , $sub] = $this->subscribed();
        $this->expectException(\InvalidArgumentException::class);
        app(SubscriptionService::class)->transition($sub, S::SUSPENDED, 'skip the queue');
    }

    // ---------------------------------------------------------------- isolation + permissions

    public function test_customers_only_see_their_own_subscriptions(): void
    {
        [$owner, , $sub] = $this->subscribed();
        $intruder = $this->customer(['email' => 'intruder@example.test']);

        $this->actingAs($intruder)->get(route('dashboard.subscriptions.show', $sub))->assertNotFound();
        $this->actingAs($intruder)->get(route('dashboard.subscriptions'))->assertOk()->assertDontSee($sub->service->label);
        $this->actingAs($owner)->get(route('dashboard.subscriptions.show', $sub))->assertOk()->assertSee('Paid through');
        $this->actingAs($intruder)->get('/admin/billing')->assertNotFound();
    }

    public function test_billing_permissions_are_enforced_on_the_server(): void
    {
        [, , $sub] = $this->subscribed();

        $this->actingAs($this->staff('technical-support'))->get(route('admin.billing.index'))->assertForbidden();
        $this->actingAs($this->staff('billing-staff'))->get(route('admin.billing.index'))->assertOk();
        $this->actingAs($this->staff('billing-staff'))->get(route('admin.billing.show', $sub))->assertOk()->assertSee('needs subscriptions.edit');
        $this->actingAs($this->staff('billing-staff'))->post(route('admin.billing.action', $sub), ['action' => 'exempt'])->assertForbidden();

        // admin role has subscriptions.* but not plans.approve: may exempt, may not change the agreed price.
        $this->actingAs($this->staff('admin'))->post(route('admin.billing.action', $sub), ['action' => 'exempt'])->assertSessionHas('success');
        $this->assertTrue($sub->fresh()->suspension_exempt);
        $this->actingAs($this->staff('admin'))->post(route('admin.billing.action', $sub), ['action' => 'price', 'price' => 1, 'note' => 'x'])->assertForbidden();

        $this->actingAs($this->staff())->post(route('admin.billing.action', $sub), ['action' => 'price', 'price' => 1200, 'note' => 'Loyalty price agreed by email'])->assertSessionHas('success');
        $this->assertSame(120000, $sub->fresh()->price_minor);
        $this->assertSame(1, $sub->events()->where('type', 'price')->count());

        foreach (['upcoming', 'overdue', 'suspension', 'reactivation', 'reminders', 'all'] as $tab) {
            $this->actingAs($this->staff())->get(route('admin.billing.index', ['tab' => $tab]))->assertOk();
        }
        $this->actingAs($this->staff())->get(route('admin.settings.edit', ['tab' => 'billing']))->assertOk()->assertSee('Billing &amp; renewals', false)->assertSee('billing__grace_days', false);
    }

    public function test_legacy_past_due_subscription_without_snapshot_still_renews(): void
    {
        $plan = $this->approve('hermes-cli-launch', 1000);
        $user = $this->customer();
        $service = Service::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'label' => 'Legacy', 'kind' => 'hermes_cli', 'status' => 'active']);
        $sub = Subscription::create(['user_id' => $user->id, 'service_id' => $service->id, 'plan_id' => $plan->id, 'billing_interval' => 'monthly',
            'status' => 'past_due', 'period_start' => now()->subMonth(), 'period_end' => now()->subDay(), 'next_due_at' => now()->subDay()]);

        app(RenewalService::class)->run();

        $invoice = Invoice::where('subscription_id', $sub->id)->firstOrFail();
        $this->assertSame(100000, $invoice->subtotal_minor, 'falls back to the approved plan price');
        $this->assertSame(S::GRACE, $sub->fresh()->status);
    }

    public function test_legacy_subscription_renews_at_its_original_order_price_not_the_new_list_price(): void
    {
        [, , $sub] = $this->subscribed();
        $sub->forceFill(['price_minor' => null])->save(); // as created before snapshots existed
        Plan::where('slug', 'hermes-cli-launch')->first()->prices()->where('billing_interval', 'monthly')->update(['amount_minor' => 777700]);
        $sub->update(['next_due_at' => now()->addDays(3)]);

        app(RenewalService::class)->run();

        $this->assertSame(100000, Invoice::where('subscription_id', $sub->id)->value('subtotal_minor'));
        $this->assertSame(100000, $sub->fresh()->price_minor);
    }

    // ---------------------------------------------------------------- helpers

    private function quarterlyPlan(int $setupRupees = 0): Plan
    {
        $plan = $this->approve('hermes-cli-launch', 1000);
        $plan->update(['setup_fee_minor' => $setupRupees * 100]);
        $plan->prices()->updateOrCreate(['billing_interval' => 'quarterly'], ['amount_minor' => 300000, 'is_active' => true, 'approval_status' => 'approved']);

        return $plan->fresh('prices');
    }

    /** @return array{0: User, 1: Order} */
    private function buy(Plan $plan, string $interval, ?User $user = null): array
    {
        $user ??= $this->customer();
        app(WalletService::class)->credit($user, 5000000, 'topup', 'seed-'.$user->id.'-'.uniqid());
        $this->actingAs($user);
        $this->post('/cart', ['plan' => $plan->slug, 'billing_interval' => $interval])->assertRedirect();
        $this->withSession(['checkout_token' => 'tok-'.uniqid()])->post('/checkout', ['accept_terms' => 1, 'use_wallet' => 1]);

        return [$user, Order::where('user_id', $user->id)->latest('id')->firstOrFail()];
    }

    /** @return array{0: User, 1: Service, 2: Subscription} */
    private function subscribed(string $email = 'billing@example.test', ?User $user = null): array
    {
        $plan = $this->approve('hermes-cli-launch', 1000);
        $user ??= $this->customer(['email' => $email]);
        $this->buy($plan, 'monthly', $user);
        $sub = Subscription::where('user_id', $user->id)->latest('id')->firstOrFail();
        $service = $sub->service;
        $service->update(['status' => 'active']);

        return [$user, $service->fresh(), $sub];
    }

    private function suspendedSubscription(): array
    {
        [$user, $service, $sub] = $this->subscribed();
        $due = now()->addDay();
        $sub->update(['next_due_at' => $due]);
        app(RenewalService::class)->run();
        app(RenewalService::class)->run($due->copy()->addDays(5));
        $this->actingAs($this->staff())->post(route('admin.billing.action', $sub), ['action' => 'confirm_suspended', 'confirm' => 1])->assertSessionHas('success');
        $this->assertSame(S::SUSPENDED, $sub->fresh()->status);
        $this->assertSame('suspended', $service->fresh()->status);

        return [$user, $service, $sub];
    }

    private function pay(User $user, Order $order): void
    {
        app(WalletService::class)->credit($user, 1000000, 'topup', 'pay-'.$order->id.'-'.uniqid());
        $this->actingAs($user);
        app(OrderService::class)->applyWallet($order->fresh());
    }

    private function bindSuspender(bool $supports, bool $failSuspend = false, bool $healthy = true): object
    {
        $fake = new class($supports, $failSuspend, $healthy) implements ServiceSuspender
        {
            public int $suspended = 0;

            public int $reactivated = 0;

            public function __construct(public bool $supports, public bool $failSuspend, public bool $healthy) {}

            public function supports(Service $service): bool
            {
                return $this->supports;
            }

            public function suspend(Service $service): void
            {
                if ($this->failSuspend) {
                    throw new RuntimeException('provider API timeout');
                }
                $this->suspended++;
            }

            public function reactivate(Service $service): void
            {
                $this->reactivated++;
            }

            public function healthy(Service $service): bool
            {
                return $this->healthy;
            }
        };
        $this->app->instance(ServiceSuspender::class, $fake);

        return $fake;
    }
}
