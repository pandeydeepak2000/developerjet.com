<?php

namespace Tests\Feature;

use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Billing details live on the profile; checkout shows a "Billed to" summary instead of an address form. */
class BillingProfileTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function billing(array $over = []): array
    {
        return $over + [
            'billing_company' => 'Acme Labs Pvt Ltd', 'gstin' => '27abcde1234f1z5', 'billing_address' => '12 MG Road, Andheri',
            'billing_city' => 'Mumbai', 'billing_state' => 'Maharashtra', 'billing_postcode' => '400053', 'billing_country' => 'IN',
        ];
    }

    public function test_customer_saves_billing_details_on_profile(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web')->get(route('dashboard.profile'))->assertOk()->assertSee('Billing details')->assertSee('GSTIN');
        $this->put(route('dashboard.profile.billing'), $this->billing())->assertRedirect(route('dashboard.profile').'#billing');

        $user->refresh();
        $this->assertSame('27ABCDE1234F1Z5', $user->gstin);
        $this->assertSame('Mumbai', $user->billing_city);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.billing.updated']);

        $this->put(route('dashboard.profile.billing'), $this->billing(['gstin' => 'BAD']))->assertSessionHasErrors('gstin', null, 'billing');
        $this->put(route('dashboard.profile.billing'), $this->billing(['billing_address' => '']))->assertSessionHasErrors('billing_address', null, 'billing');
    }

    public function test_checkout_shows_billed_to_card_without_an_address_form(): void
    {
        $user = $this->customer(['name' => 'Asha Rao']);
        $this->approve('hermes-cli-launch');
        $this->actingAs($user, 'web')->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);

        $this->get('/checkout')->assertOk()->assertSee('Billed to')->assertSee('Add billing address')
            ->assertDontSee('name="billing_address"', false)->assertDontSee('name="billing_name"', false);

        $this->put(route('dashboard.profile.billing'), $this->billing() + ['return' => 'checkout'])->assertRedirect(route('checkout'));
        $this->get('/checkout')->assertOk()->assertSee('Acme Labs Pvt Ltd')->assertSee('Mumbai, Maharashtra, 400053')->assertSee('27ABCDE1234F1Z5');
    }

    public function test_order_and_invoice_use_the_saved_billing_details(): void
    {
        $user = $this->customer(['name' => 'Asha Rao']);
        $user->forceFill($this->billing(['gstin' => '27ABCDE1234F1Z5']))->save();
        app(WalletService::class)->credit($user, 500000, 'admin_adjustment', 'adjust:b1', 'Funds');
        $this->approve('hermes-cli-launch');
        $order = $this->placeOrder($user, 'hermes-cli-launch', 'tok-bill', ['use_wallet' => 1]);

        $snap = $order->customer_snapshot;
        $this->assertSame('Acme Labs Pvt Ltd', $snap['billing_name']);
        $this->assertSame('Asha Rao', $snap['billing_contact']);
        $this->assertStringContainsString('Mumbai, Maharashtra, 400053', $snap['billing_address']);
        $this->assertStringContainsString('India', $snap['billing_address']);
        $this->assertSame('27ABCDE1234F1Z5', $snap['gstin']);
    }
}
