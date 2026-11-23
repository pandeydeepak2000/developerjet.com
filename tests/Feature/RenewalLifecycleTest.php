<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\BillingAction;
use App\Models\CancellationRequest;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\User;
use App\Services\Billing\LifecycleService;
use App\Services\RenewalService;
use App\Services\WalletService;
use App\Support\Setting;
use App\Support\SubscriptionStatus as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestone 7: renewal add-ons, cancellation requests, period-end cancellation, expiry, failed-renewal handling. */
class RenewalLifecycleTest extends TestCase
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

    /** @return array{0: User, 1: Subscription} */
    private function subscribed(?User $user = null): array
    {
        $plan = $this->approve('hermes-cli-launch', 1000);
        $user ??= $this->customer();
        app(WalletService::class)->credit($user, 5000000, 'topup', 'seed-'.$user->id.'-'.uniqid());
        $this->actingAs($user, 'web');
        $this->post('/cart', ['plan' => $plan->slug, 'billing_interval' => 'monthly'])->assertRedirect();
        $this->withSession(['checkout_token' => 'tok-'.uniqid()])->post('/checkout', ['accept_terms' => 1, 'use_wallet' => 1]);
        $sub = Subscription::where('user_id', $user->id)->latest('id')->firstOrFail();
        $sub->service->update(['status' => 'active']);

        return [$user, $sub->fresh()];
    }

    private function recurringAddon(): Addon
    {
        return Addon::create(['slug' => 'extra-backup', 'name' => 'Extra backups', 'billing_type' => 'recurring', 'is_active' => true, 'price_minor' => null]);
    }

    // ---------------------------------------------------------------- add-ons

    public function test_agreed_addons_are_billed_on_renewal_invoices_with_snapshot_prices(): void
    {
        [, $sub] = $this->subscribed();
        $addon = $this->recurringAddon();
        $super = $this->staff();

        // Pricing authority + re-authentication are required.
        $this->actingAs($super)->post(route('admin.billing.addons.store', $sub), ['addon_id' => $addon->id, 'price' => 250])->assertRedirect(route('admin.confirm-access'));
        $this->actingAs($this->staff('billing-staff'))->withSession(['staff_reauth_at' => time()])
            ->post(route('admin.billing.addons.store', $sub), ['addon_id' => $addon->id, 'price' => 250])->assertForbidden();
        $this->actingAs($super);
        $this->withSession(['staff_reauth_at' => time()])->post(route('admin.billing.addons.store', $sub), ['addon_id' => $addon->id, 'price' => 250])->assertSessionHas('success');

        $row = SubscriptionAddon::firstOrFail();
        $this->assertSame(25000, (int) $row->price_minor);
        $this->assertTrue($row->starts_at->equalTo($sub->next_due_at)); // from the next renewal: no mid-cycle charge

        // Catalogue changes never alter the agreed price.
        $addon->update(['price_minor' => 99900]);
        Carbon::setTestNow($sub->next_due_at->copy()->subDays(7));
        app(RenewalService::class)->run();
        $invoice = Invoice::where('subscription_id', $sub->id)->whereNotNull('period_start')->firstOrFail();
        $order = Order::findOrFail($invoice->order_id);
        $this->assertTrue($order->items()->where('name', 'Extra backups')->where('unit_minor', 25000)->exists());
        $this->assertSame(100000 + 25000, (int) $order->items()->sum('unit_minor'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.subscription.addon_added']);
    }

    public function test_removed_addons_stop_at_the_next_renewal_and_one_time_addons_are_never_rebilled(): void
    {
        [, $sub] = $this->subscribed();
        $addon = $this->recurringAddon();
        Addon::create(['slug' => 'setup-help', 'name' => 'Setup help', 'billing_type' => 'one_time', 'is_active' => true, 'price_minor' => 5000]);

        // Snapshot from the purchase: only recurring, priced add-ons.
        $item = new OrderItem(['config' => ['addons' => [
            ['slug' => 'extra-backup', 'name' => 'Extra backups', 'price_minor' => 20000],
            ['slug' => 'setup-help', 'name' => 'Setup help', 'price_minor' => 5000],
            ['slug' => 'unpriced', 'name' => 'Unpriced', 'price_minor' => null],
        ]]]);
        app(LifecycleService::class)->snapshotAddons($sub, $item);
        $this->assertSame(['extra-backup'], $sub->addons()->pluck('slug')->all());

        $this->actingAs($this->staff())->delete(route('admin.billing.addons.destroy', [$sub, $sub->addons()->first()]))->assertSessionHas('success');
        $this->assertSame(0, app(LifecycleService::class)->renewalAddonLines($sub, $sub->next_due_at)->count());
    }

    // ---------------------------------------------------------------- cancellation

    public function test_customer_requests_end_of_period_cancellation_staff_approve_and_it_ends_without_deleting(): void
    {
        [$user, $sub] = $this->subscribed();
        $paidThrough = $sub->period_end->copy();

        $this->actingAs($user, 'web')->post(route('dashboard.subscriptions.cancel', $sub), ['type' => 'end_of_period'])->assertSessionHasErrors('confirm');
        $this->post(route('dashboard.subscriptions.cancel', $sub), ['type' => 'immediate', 'confirm' => 1])->assertSessionHas('error'); // immediate not allowed by default
        $this->post(route('dashboard.subscriptions.cancel', $sub), ['type' => 'end_of_period', 'reason' => 'Moving on', 'confirm' => 1])->assertSessionHas('success');
        $this->post(route('dashboard.subscriptions.cancel', $sub), ['type' => 'end_of_period', 'confirm' => 1])->assertSessionHas('error'); // only one live request
        $req = CancellationRequest::firstOrFail();
        $this->assertSame('pending', $req->status);
        $this->get(route('dashboard.subscriptions.show', $sub))->assertOk()->assertSee('Awaiting review');

        // Another customer cannot touch it.
        $this->actingAs($this->customer(), 'web')->post(route('dashboard.subscriptions.cancel', $sub), ['type' => 'end_of_period', 'confirm' => 1])->assertNotFound();

        $this->actingAs($this->staff())->get(route('admin.billing.index', ['tab' => 'cancellations']))->assertOk()->assertSee('Moving on');
        $this->post(route('admin.billing.cancellations.review', $req), ['decision' => 'approve'])->assertSessionHas('success');
        $fresh = $sub->fresh();
        $this->assertTrue($fresh->cancel_at_period_end);
        $this->assertTrue($fresh->cancel_at->equalTo($paidThrough));
        $this->assertSame(S::ACTIVE, $fresh->status); // still usable until the paid period ends

        // No renewal invoice is issued for a subscription that ends at period end.
        Carbon::setTestNow($paidThrough->copy()->subDays(7));
        app(RenewalService::class)->run();
        $this->assertSame(0, Invoice::where('subscription_id', $sub->id)->whereNotNull('period_start')->count());

        Carbon::setTestNow($paidThrough->copy()->addMinute());
        $stats = app(RenewalService::class)->run();
        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame(S::CANCELLED, $sub->fresh()->status);
        $this->assertSame('completed', $req->fresh()->status);
        $this->assertTrue(BillingAction::where('subscription_id', $sub->id)->where('type', 'retention_review')->where('status', 'open')->exists());
        $this->assertNotNull($sub->service->fresh()); // nothing deleted

        $this->actingAs($user, 'web')->get(route('dashboard.subscriptions'))->assertOk()->assertSee('Cancelled and expired services');
    }

    public function test_approved_cancellation_voids_an_already_issued_unpaid_renewal_invoice_and_can_be_withdrawn(): void
    {
        [$user, $sub] = $this->subscribed();
        Carbon::setTestNow($sub->next_due_at->copy()->subDays(7));
        app(RenewalService::class)->run();
        $invoice = Invoice::where('subscription_id', $sub->id)->whereNotNull('period_start')->firstOrFail();
        $this->assertSame('unpaid', $invoice->status);

        $this->actingAs($user, 'web')->post(route('dashboard.subscriptions.cancel', $sub), ['type' => 'end_of_period', 'confirm' => 1]);
        $req = CancellationRequest::firstOrFail();
        app(LifecycleService::class)->approve($req, $this->staff());
        $this->assertSame('cancelled', $invoice->fresh()->status);

        $this->post(route('dashboard.subscriptions.cancel.withdraw', [$sub, $req]))->assertSessionHas('success');
        $this->assertFalse($sub->fresh()->cancel_at_period_end);
        $this->assertSame('withdrawn', $req->fresh()->status);
    }

    public function test_settings_control_approval_immediate_cancellation_and_rejection_needs_a_note(): void
    {
        [$user, $sub] = $this->subscribed();
        Setting::set('billing.cancellation_requires_approval', '0');
        Setting::set('billing.cancellation_allow_immediate', '1');

        $this->actingAs($user, 'web')->post(route('dashboard.subscriptions.cancel', $sub), ['type' => 'immediate', 'confirm' => 1])->assertSessionHas('success');
        $this->assertSame(S::CANCELLED, $sub->fresh()->status);
        $this->assertSame('completed', CancellationRequest::first()->status);

        [$user2, $sub2] = $this->subscribed();
        Setting::set('billing.cancellation_requires_approval', '1');
        $this->actingAs($user2, 'web')->post(route('dashboard.subscriptions.cancel', $sub2), ['type' => 'end_of_period', 'confirm' => 1]);
        $req = CancellationRequest::where('subscription_id', $sub2->id)->firstOrFail();
        $this->actingAs($this->staff())->post(route('admin.billing.cancellations.review', $req), ['decision' => 'reject'])->assertStatus(422);
        $this->post(route('admin.billing.cancellations.review', $req), ['decision' => 'reject', 'note' => 'Contract has a minimum term'])->assertSessionHas('success');
        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertFalse($sub2->fresh()->cancel_at_period_end);

        Setting::set('billing.cancellation_enabled', '0');
        $this->actingAs($user2, 'web')->post(route('dashboard.subscriptions.cancel', $sub2), ['type' => 'end_of_period', 'confirm' => 1])->assertSessionHas('error');
    }

    // ---------------------------------------------------------------- expiry & failures

    public function test_automatic_expiry_is_off_by_default_and_never_deletes_when_enabled(): void
    {
        [, $sub] = $this->subscribed();
        $due = $sub->next_due_at->copy();
        Carbon::setTestNow($due->copy()->addDays(40));
        app(RenewalService::class)->run();
        $this->assertNotSame(S::EXPIRED, $sub->fresh()->status);

        Setting::set('billing.expire_after_days', '30');
        $stats = app(RenewalService::class)->run();
        $this->assertSame(1, $stats['expired']);
        $fresh = $sub->fresh();
        $this->assertSame(S::EXPIRED, $fresh->status);
        $this->assertNotNull($fresh->expired_at);
        $this->assertTrue(BillingAction::where('subscription_id', $sub->id)->where('type', 'retention_review')->exists());
        $this->assertNotNull($fresh->service);
        $this->assertSame(0, app(RenewalService::class)->run()['expired']); // idempotent
    }

    public function test_failed_renewal_processing_is_recorded_alerted_and_retryable(): void
    {
        [, $sub] = $this->subscribed();
        Setting::set('billing.renewal_failure_alert_after', '2');
        $lifecycle = app(LifecycleService::class);

        $lifecycle->recordFailure($sub, new \RuntimeException('Gateway timeout'));
        $lifecycle->recordFailure($sub->fresh(), new \RuntimeException('Gateway timeout'));
        $fresh = $sub->fresh();
        $this->assertSame(2, (int) $fresh->renewal_failures);
        $this->assertStringContainsString('Gateway timeout', $fresh->last_renewal_error);

        $this->actingAs($this->staff())->get(route('admin.billing.index', ['tab' => 'failed']))->assertOk()->assertSee('2 failure(s)');
        Carbon::setTestNow($sub->next_due_at->copy()->subDays(7));
        $this->post(route('admin.billing.retry', $sub))->assertSessionHas('success');
        $this->assertSame(0, (int) $sub->fresh()->renewal_failures);
        $this->assertSame(1, Invoice::where('subscription_id', $sub->id)->whereNotNull('period_start')->count());
        $this->actingAs($this->staff('technical-support'))->post(route('admin.billing.retry', $sub))->assertForbidden();
    }
}
