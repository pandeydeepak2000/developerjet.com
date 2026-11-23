<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestone 8: billing admin — overdue invoices, reconciliation, billing audit, add-on price approval. */
class BillingAdminTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
        Http::fake();
    }

    private function overdueInvoice(User $customer): Invoice
    {
        $order = $this->placeOrder($customer, 'hermes-cli-launch', 'tok-'.uniqid());

        return Invoice::create([
            'number' => 'TST'.random_int(1000, 9999), 'type' => 'invoice', 'order_id' => $order->id, 'user_id' => $customer->id,
            'status' => 'unpaid', 'subtotal_minor' => 100000, 'tax_minor' => 0, 'total_minor' => 100000,
            'issued_at' => now()->subDays(20), 'due_at' => now()->subDays(5),
        ]);
    }

    public function test_overdue_invoices_are_listed_for_billing_roles_only(): void
    {
        $this->approve('hermes-cli-launch');
        $invoice = $this->overdueInvoice($this->customer(['email' => 'late@example.test']));

        $this->actingAs($this->staff('billing-staff'))->get('/admin/billing-ops')->assertOk()->assertSee($invoice->number)->assertSee('late@example.test');
        $this->actingAs($this->staff('technical-support'))->get('/admin/billing-ops')->assertForbidden();
        $this->actingAs($this->staff('billing-staff'))->get('/admin/r/invoices?status=unpaid')->assertOk()->assertSee($invoice->number);
    }

    public function test_reconciliation_view_and_run_are_permissioned_and_contact_nothing_without_a_gateway(): void
    {
        $this->approve('hermes-cli-launch');
        $order = $this->placeOrder($this->customer(), 'hermes-cli-launch', 'tok-r');
        PaymentAttempt::create(['order_id' => $order->id, 'gateway' => 'razorpay', 'mode' => 'test', 'gateway_order_id' => 'order_X', 'amount_minor' => 5, 'status' => 'amount_mismatch']);

        $this->actingAs($this->staff('billing-staff'))->get('/admin/billing-ops?tab=reconciliation')->assertOk()->assertSee('Amount mismatches')->assertSee($order->number);
        $this->post('/admin/billing-ops/reconcile')->assertForbidden(); // billing staff lack orders.edit

        $this->actingAs($this->staff())->post('/admin/billing-ops/reconcile')->assertSessionHas('success');
        Http::assertNothingSent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.reconcile_run']);
    }

    public function test_billing_audit_log_shows_billing_events_only_to_authorised_staff(): void
    {
        $this->approve('hermes-cli-launch');
        $this->placeOrder($this->customer(), 'hermes-cli-launch', 'tok-a');

        $this->actingAs($this->staff())->get('/admin/billing-ops?tab=audit')->assertOk()->assertSee('order.placed');
        $this->actingAs($this->staff('sales-staff'))->get('/admin/billing-ops?tab=audit')->assertForbidden();
    }

    public function test_addon_prices_need_pricing_approval_rights(): void
    {
        $addon = Addon::create(['slug' => 'priority-support', 'name' => 'Priority support', 'billing_type' => 'recurring', 'is_active' => true]);
        $admin = $this->staff('admin'); // holds addons.* but not plans.approve
        $payload = ['name' => 'Priority support', 'slug' => 'priority-support', 'billing_type' => 'recurring', 'is_active' => 1];

        $this->actingAs($admin)->put('/admin/r/addons/'.$addon->id, $payload + ['price_minor' => '499'])->assertForbidden();
        $this->assertNull($addon->fresh()->price_minor);
        $this->put('/admin/r/addons/'.$addon->id, array_merge($payload, ['name' => 'Priority support desk']))->assertRedirect();
        $this->assertSame('Priority support desk', $addon->fresh()->name);

        $this->actingAs($this->staff())->put('/admin/r/addons/'.$addon->id, array_merge($payload, ['name' => 'Priority support desk', 'price_minor' => '499']))->assertRedirect();
        $this->assertSame(49900, (int) $addon->fresh()->price_minor);
    }

    public function test_invoice_numbers_stay_sequential_and_unique(): void
    {
        $this->approve('hermes-cli-launch');
        $numbers = [];
        foreach (range(1, 5) as $i) {
            $order = $this->placeOrder($this->customer(), 'hermes-cli-launch', 'tok-n'.$i);
            $order->forceFill(['paid_at' => now(), 'status' => OrderStatus::PAID])->save();
            $numbers[] = app(InvoiceService::class)->issueForOrder($order->fresh())->number;
            // Issuing twice never creates a duplicate.
            $this->assertSame(end($numbers), app(InvoiceService::class)->issueForOrder($order->fresh())->number);
        }
        $this->assertSame($numbers, array_values(array_unique($numbers)));
        $digits = array_map(fn ($n) => (int) preg_replace('/\D/', '', $n), $numbers);
        $this->assertSame(range($digits[0], $digits[0] + 4), $digits);
    }
}
