<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_pages_require_authentication(): void
    {
        foreach (['', '/orders', '/services', '/wallet', '/invoices'] as $path) {
            $this->get('/dashboard'.$path)->assertRedirect(route('login'));
        }
    }

    public function test_dashboard_pages_render_honest_empty_states(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Asha Dev']));

        $this->get('/dashboard')->assertOk()->assertSee('Welcome, Asha')->assertSee('No orders yet');
        $this->get('/dashboard/orders')->assertOk()->assertSee('No orders yet');
        $this->get('/dashboard/services')->assertOk()->assertSee('No active services');
        $this->get('/dashboard/wallet')->assertOk()->assertSee('₹0')->assertSee('No transactions')->assertSee('Contact our Sales Team');
        $this->get('/dashboard/invoices')->assertOk()->assertSee('No invoices yet');
    }

    public function test_users_only_see_their_own_records(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Order::create(['user_id' => $owner->id, 'number' => 'DJ-OWN-1', 'total_minor' => 100000]);
        Order::create(['user_id' => $other->id, 'number' => 'DJ-OTHER-1', 'total_minor' => 100000]);

        $this->actingAs($owner)->get('/dashboard/orders')->assertSee('DJ-OWN-1')->assertDontSee('DJ-OTHER-1');
    }

    public function test_customer_area_policies_restrict_records_to_the_owner_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $order = Order::create(['user_id' => $owner->id, 'number' => 'DJ-1']);
        $service = Service::create(['user_id' => $owner->id, 'label' => 'x']);
        $invoice = Invoice::create(['user_id' => $owner->id]);

        foreach ([$order, $service, $invoice] as $record) {
            $this->assertTrue(Gate::forUser($owner)->allows('view', $record));
            $this->assertFalse(Gate::forUser($other)->allows('view', $record));
            // Staff (even the legacy owner) use the staff portal, never a customer session, to see others' records.
            $this->assertFalse(Gate::forUser($admin)->allows('view', $record));
        }
    }

    public function test_dashboard_is_not_cached(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertHeader('Cache-Control', 'no-store, private');
    }
}
