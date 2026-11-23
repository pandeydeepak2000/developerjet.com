<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\TopupRequest;
use App\Models\WalletTransaction;
use App\Services\InsufficientFundsException;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\RenewalService;
use App\Services\WalletService;
use App\Support\OrderStatus;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class WalletBillingTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_wallet_credit_is_idempotent_per_reference(): void
    {
        $user = $this->customer();
        $wallets = app(WalletService::class);

        $wallets->credit($user, 5000, 'topup', 'topup:1');
        $wallets->credit($user, 5000, 'topup', 'topup:1'); // replay / retry

        $this->assertSame(5000, $user->wallet->fresh()->balance_minor);
        $this->assertSame(1, WalletTransaction::count());
    }

    public function test_wallet_never_goes_negative_and_failed_debit_leaves_no_trace(): void
    {
        $user = $this->customer();
        $wallets = app(WalletService::class);
        $wallets->credit($user, 1000, 'topup', 'topup:a');

        $this->expectException(InsufficientFundsException::class);
        try {
            $wallets->debit($user, 1001, 'order', 'order:x:wallet');
        } finally {
            $this->assertSame(1000, $user->wallet->fresh()->balance_minor);
            $this->assertSame(1, WalletTransaction::count());
        }
    }

    public function test_ledger_rows_record_running_balance_and_are_immutable(): void
    {
        $user = $this->customer();
        $wallets = app(WalletService::class);
        $wallets->credit($user, 3000, 'topup', 'r1');
        $tx = $wallets->debit($user, 1200, 'order', 'r2');

        $this->assertSame(1800, $tx->balance_after_minor);
        $this->expectException(LogicException::class);
        $tx->update(['amount_minor' => 1]);
    }

    public function test_ledger_rows_cannot_be_deleted(): void
    {
        $user = $this->customer();
        $tx = app(WalletService::class)->credit($user, 100, 'topup', 'r1');

        $this->expectException(LogicException::class);
        $tx->delete();
    }

    public function test_topup_is_credited_once_after_admin_verification(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->post('/dashboard/wallet/topup', ['amount' => 500, 'payment_reference' => 'UTR123'])->assertSessionHas('success');
        $topup = TopupRequest::firstOrFail();
        $this->assertSame(0, $customer->fresh()->wallet?->balance_minor ?? 0, 'Request alone must not credit funds');

        $billing = $this->staff('billing-staff');
        $this->actingAs($billing)->post(route('admin.wallet.approve', $topup))->assertSessionHas('success');
        $this->post(route('admin.wallet.approve', $topup))->assertSessionHas('error'); // double click / replay

        $this->assertSame(50000, $customer->fresh()->wallet->balance_minor);
        $this->assertSame('credited', $topup->fresh()->status);
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame($billing->id, $topup->fresh()->reviewed_by);
    }

    public function test_topup_requires_permission_and_is_not_approvable_by_customer_or_sales(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->post('/dashboard/wallet/topup', ['amount' => 100]);
        $topup = TopupRequest::firstOrFail();

        $this->actingAs($customer)->post(route('admin.wallet.approve', $topup))->assertNotFound();
        $this->actingAs($this->staff('sales-staff'))->post(route('admin.wallet.approve', $topup))->assertForbidden();
        $this->assertSame('pending', $topup->fresh()->status);
    }

    public function test_topup_tax_is_configurable_not_hardcoded_zero(): void
    {
        Setting::set('tax.rate.wallet_topup', '18');
        $customer = $this->customer();
        $this->actingAs($customer)->post('/dashboard/wallet/topup', ['amount' => 1000]);
        $this->actingAs($this->staff('billing-staff'))->post(route('admin.wallet.approve', TopupRequest::firstOrFail()));

        $meta = WalletTransaction::firstOrFail()->meta;
        $this->assertSame('wallet_topup', $meta['tax_class']);
        $this->assertTrue($meta['tax_configured']);
        $this->assertSame(18000, $meta['tax_minor']);
    }

    public function test_admin_adjustment_requires_reason_is_audited_and_cannot_overdraw(): void
    {
        $customer = $this->customer();
        $billing = $this->staff('billing-staff');
        $this->actingAs($billing);

        $this->post(route('admin.wallet.adjust'), ['email' => $customer->email, 'type' => 'credit', 'amount' => 10, 'reason' => 'Goodwill credit'])->assertSessionHasErrors('transaction_id');
        $this->post(route('admin.wallet.adjust'), ['email' => $customer->email, 'type' => 'credit', 'amount' => 10, 'reason' => 'Goodwill credit', 'transaction_id' => 'UTR123456'])->assertSessionHas('success');
        $this->post(route('admin.wallet.adjust'), ['email' => $customer->email, 'type' => 'credit', 'amount' => 10, 'reason' => 'Duplicate test', 'transaction_id' => 'UTR123456'])->assertSessionHasErrors('transaction_id');
        $this->get(route('admin.wallet.index', ['tx_q' => 'UTR123456']))->assertSee('UTR123456');
        $this->post(route('admin.wallet.adjust'), ['email' => $customer->email, 'type' => 'debit', 'amount' => 99, 'reason' => 'Correction', 'transaction_id' => 'UTR999'])->assertSessionHas('error');

        $this->assertSame(1000, $customer->fresh()->wallet->balance_minor);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.adjustment', 'user_id' => $billing->id]);
    }

    public function test_manual_add_funds_card_shows_configured_sales_number(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->get('/dashboard/wallet')->assertSee('Contact our Sales Team', false)->assertSee('has not been configured');

        Setting::set('sales.phone', '+91 98765 43210');
        $this->get('/dashboard/wallet')->assertSee('+91 98765 43210');
    }

    public function test_wallet_fully_pays_order_and_cancel_releases_reserved_funds(): void
    {
        $this->approve('hermes-cli-launch', 1000);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 100000, 'topup', 'seed');

        $order = $this->placeOrder($user, 'hermes-cli-launch', 't1', ['use_wallet' => 1]);
        $this->assertSame(OrderStatus::AWAITING_PROVISIONING, $order->status);
        $this->assertSame('wallet', $order->payment_method);
        $this->assertSame(0, $user->wallet->fresh()->balance_minor);
        $this->assertSame(1, Invoice::count());

        // Partial wallet: reserved on the order, returned on cancellation.
        $this->approve('hermes-cli-grow', 2000);
        app(WalletService::class)->credit($user, 50000, 'topup', 'seed2');
        $second = $this->placeOrder($user, 'hermes-cli-grow', 't2', ['use_wallet' => 1]);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $second->status);
        $this->assertSame(50000, $second->wallet_applied_minor);
        $this->assertSame(0, $user->wallet->fresh()->balance_minor);

        app(OrderService::class)->cancel($second, $user, 'changed mind');
        $this->assertSame(50000, $user->wallet->fresh()->balance_minor);
    }

    public function test_invoice_numbers_are_sequential_configurable_and_unique(): void
    {
        Setting::set('invoice.prefix', 'AUN');
        Setting::set('invoice.start_number', '58');
        $this->approve('hermes-cli-launch', 1000);
        $this->approve('hermes-cli-grow', 1000);
        $wallets = app(WalletService::class);

        $a = $this->customer();
        $wallets->credit($a, 500000, 'topup', 'a');
        $this->placeOrder($a, 'hermes-cli-launch', 'ta', ['use_wallet' => 1]);
        $b = $this->customer();
        $wallets->credit($b, 500000, 'topup', 'b');
        $this->placeOrder($b, 'hermes-cli-grow', 'tb', ['use_wallet' => 1]);

        $this->assertSame(['AUN00058', 'AUN00059'], Invoice::orderBy('id')->pluck('number')->all());
        $this->assertSame(2, Invoice::distinct('number')->count('number'));
    }

    public function test_invoice_cannot_be_issued_for_unpaid_order_and_is_not_duplicated(): void
    {
        $this->approve('hermes-cli-launch', 1000);
        $order = $this->placeOrder($this->customer());

        try {
            app(InvoiceService::class)->issueForOrder($order);
            $this->fail('Unpaid order must not get an invoice');
        } catch (LogicException) {
            $this->assertDatabaseCount('invoices', 0);
        }

        $wallets = app(WalletService::class);
        $paid = $this->customer();
        $wallets->credit($paid, 500000, 'topup', 'p');
        $paidOrder = $this->placeOrder($paid, 'hermes-cli-launch', 'tp', ['use_wallet' => 1]);
        app(InvoiceService::class)->issueForOrder($paidOrder);
        app(InvoiceService::class)->issueForOrder($paidOrder);
        $this->assertSame(1, Invoice::where('order_id', $paidOrder->id)->count());
    }

    public function test_invoice_pdf_is_valid_includes_gstin_only_when_configured_and_is_emailed(): void
    {
        Mail::fake();
        Setting::set('company.gstin', '29ABCDE1234F1Z5');
        $this->approve('hermes-cli-launch', 1000);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 500000, 'topup', 'x');
        $order = $this->placeOrder($user, 'hermes-cli-launch', 'tx', ['use_wallet' => 1, 'gstin' => '27AAAAA0000A1Z5']);
        $invoice = Invoice::firstOrFail();

        $pdf = app(InvoiceService::class)->pdf($invoice)->output();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertNotNull($invoice->fresh()->emailed_at);
        Mail::assertQueued(TemplatedMail::class, fn ($m) => $m->template === 'invoice');
        $this->assertSame('29ABCDE1234F1Z5', $invoice->billing_snapshot['company']['gstin']);
        $this->assertSame('27AAAAA0000A1Z5', $invoice->billing_snapshot['customer']['gstin']);
    }

    public function test_refund_issues_credit_note_and_cancels_services(): void
    {
        $this->approve('hermes-cli-launch', 1000);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 500000, 'topup', 'x');
        $order = $this->placeOrder($user, 'hermes-cli-launch', 'tr', ['use_wallet' => 1]);
        $admin = $this->staff('super-admin');

        $this->actingAs($admin)->post(route('admin.orders.refund-request', $order), ['reason' => 'customer request'])->assertSessionHas('success');
        $this->post(route('admin.orders.refund-complete', $order), ['destination' => 'wallet'])->assertSessionHas('success');

        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame(1, Invoice::where('type', 'credit_note')->count());
        $this->assertStringStartsWith('CN', Invoice::where('type', 'credit_note')->value('number'));
        $this->assertSame('cancelled', $order->services()->first()->status);
        $this->assertSame(500000, $user->wallet->fresh()->balance_minor); // paid 100000 -> refunded back
    }

    public function test_renewals_remind_overdue_and_create_one_renewal_order_without_auto_charging(): void
    {
        Mail::fake();
        $this->approve('hermes-cli-launch', 1000);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 500000, 'topup', 'x');
        $order = $this->placeOrder($user, 'hermes-cli-launch', 'tren', ['use_wallet' => 1]);
        $sub = Subscription::firstOrFail();
        $sub->update(['next_due_at' => now()->addDays(3)]);
        $walletBefore = $user->wallet->fresh()->balance_minor;

        $stats = app(RenewalService::class)->run();
        app(RenewalService::class)->run(); // scheduler re-run: no duplicates

        $this->assertSame(1, $stats['reminded']);
        $this->assertSame(2, Order::count());
        $renewal = Order::latest('id')->first();
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $renewal->status);
        $this->assertSame($walletBefore, $user->wallet->fresh()->balance_minor, 'Renewal must never auto-charge stored funds');
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->template === 'renewal_reminder'); // queued job → sendNow

        // Overdue notice
        $sub->forceFill(['next_due_at' => now()->subDay(), 'overdue_notified_at' => null, 'status' => 'active'])->save();
        $this->assertSame(1, app(RenewalService::class)->run()['overdue']);
        $this->assertSame('grace_period', $sub->fresh()->status); // v0.3 engine: overdue within the 3-day default grace period
    }

    public function test_paying_a_renewal_order_extends_the_subscription_without_new_service(): void
    {
        $this->approve('hermes-cli-launch', 1000);
        $user = $this->customer();
        $wallets = app(WalletService::class);
        $wallets->credit($user, 500000, 'topup', 'x');
        $this->placeOrder($user, 'hermes-cli-launch', 'tren2', ['use_wallet' => 1]);
        $sub = Subscription::firstOrFail();
        $end = $sub->period_end;
        $sub->update(['next_due_at' => now()->addDay()]);
        app(RenewalService::class)->run();
        $renewal = Order::latest('id')->first();

        $this->actingAs($user);
        app(OrderService::class)->applyWallet($renewal);

        $this->assertSame(1, Service::count());
        $this->assertTrue($sub->fresh()->period_end->gt($end));
        $this->assertSame(OrderStatus::ACTIVE, $renewal->fresh()->status);
    }
}
