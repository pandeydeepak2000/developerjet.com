<?php

namespace Tests\Feature;

use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class WalletCustomerPickerTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_finds_customers_by_name_email_and_phone_with_balance(): void
    {
        $rahul = $this->customer(['name' => 'Rahul Sharma', 'email' => 'rahul.sharma@example.com', 'phone' => '+919876543210']);
        $this->customer(['name' => 'Priya Verma', 'email' => 'priya@example.com', 'phone' => '+919111111111']);
        app(WalletService::class)->credit($rahul, 150000, 'admin_adjustment', 'adjust:T1', 'Test credit');

        $this->actingAs($this->staff('billing-staff'));

        $byName = $this->getJson(route('admin.wallet.customers', ['q' => 'rahul']))->assertOk()->json('data');
        $this->assertCount(1, $byName);
        $this->assertSame('rahul.sharma@example.com', $byName[0]['email']);
        $this->assertSame(Money::inr(150000), $byName[0]['balance']);

        $this->assertSame('priya@example.com', $this->getJson(route('admin.wallet.customers', ['q' => 'priya@ex']))->json('data.0.email'));
        $this->assertSame('rahul.sharma@example.com', $this->getJson(route('admin.wallet.customers', ['q' => '98765 43']))->json('data.0.email'));
    }

    public function test_never_returns_staff_and_ignores_too_short_terms(): void
    {
        $this->customer(['name' => 'Asha Customer', 'email' => 'asha-customer@example.com']);
        $staff = $this->staff('billing-staff', ['name' => 'Asha Staff', 'email' => 'asha-staff@example.com']);
        $this->actingAs($staff);

        $emails = collect($this->getJson(route('admin.wallet.customers', ['q' => 'asha']))->json('data'))->pluck('email');
        $this->assertSame(['asha-customer@example.com'], $emails->all());
        $this->assertSame([], $this->getJson(route('admin.wallet.customers', ['q' => 'a']))->json('data'));
    }

    public function test_results_are_capped_and_wildcards_are_literal(): void
    {
        foreach (range(1, 12) as $i) {
            $this->customer(['name' => "Bulk User $i", 'email' => "bulk$i@example.com"]);
        }
        $this->actingAs($this->staff('billing-staff'));

        $this->assertCount(8, $this->getJson(route('admin.wallet.customers', ['q' => 'bulk']))->json('data'));
        $this->assertSame([], $this->getJson(route('admin.wallet.customers', ['q' => '%%']))->json('data'));
    }

    public function test_requires_wallet_create_permission(): void
    {
        $this->customer(['name' => 'Hidden Person']);
        $this->actingAs($this->staff('technical-support'));
        $this->getJson(route('admin.wallet.customers', ['q' => 'hidden']))->assertForbidden();

        auth('staff')->logout();
        auth('web')->logout();
        $this->actingAs($this->customer(), 'web');
        $this->get(route('admin.wallet.customers', ['q' => 'hidden']))->assertNotFound();
    }

    public function test_wallet_page_renders_the_picker(): void
    {
        $this->actingAs($this->staff('billing-staff'));
        $this->get(route('admin.wallet.index'))->assertOk()
            ->assertSee('data-customer-picker', false)
            ->assertSee(route('admin.wallet.customers'), false);
    }
}
