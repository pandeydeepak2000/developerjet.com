<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** User Manager: customers + staff in one place, email change, impersonation (read-only). */
class UserManagerTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function confirmed(): array
    {
        return ['staff_reauth_at' => time()];
    }

    public function test_user_manager_lists_customers_and_staff_with_search_and_pagination(): void
    {
        $admin = $this->staff();
        $this->customer(['name' => 'Priya Client', 'email' => 'priya@example.test']);
        $this->staff('technical-support', ['name' => 'Support Sam', 'email' => 'sam@example.test']);

        $this->actingAs($admin)->get(route('admin.users.index', ['type' => 'customers', 'q' => 'Priya']))
            ->assertOk()->assertSee('Priya Client')->assertDontSee('Support Sam');

        $this->get(route('admin.users.index', ['type' => 'staff', 'q' => 'Support Sam']))
            ->assertOk()->assertSee('Support Sam')->assertDontSee('Priya Client');

        $this->get(route('admin.users.index', ['type' => 'customers', 'per_page' => 100]))->assertOk();
    }

    public function test_admin_can_change_customer_email_with_reauth_and_audit(): void
    {
        $admin = $this->staff();
        $customer = $this->customer(['email' => 'old@example.test']);
        $customer->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->withSession($this->confirmed())
            ->post(route('admin.users.email', $customer), ['email' => 'new@example.test'])
            ->assertSessionHas('success');

        $fresh = $customer->fresh();
        $this->assertSame('new@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.email_changed']);
    }

    public function test_email_change_without_reauth_redirects_to_confirm(): void
    {
        $admin = $this->staff();
        $customer = $this->customer(['email' => 'old2@example.test']);

        $this->actingAs($admin)->post(route('admin.users.email', $customer), ['email' => 'new2@example.test'])
            ->assertRedirect(route('admin.confirm-access'));
        $this->assertSame('old2@example.test', $customer->fresh()->email);
    }

    public function test_admin_can_impersonate_customer_read_only_and_exit(): void
    {
        $admin = $this->staff();
        $customer = $this->customer(['name' => 'Impersonate Me', 'email' => 'imp@example.test']);

        $this->actingAs($admin)->withSession($this->confirmed())
            ->post(route('admin.users.impersonate', $customer))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk()->assertSee('Viewing as Impersonate Me');

        // Any write while impersonating is blocked: POST /dashboard/tickets is inside impersonation.readonly.
        $this->post(route('dashboard.tickets.store'), ['subject' => 'x', 'body' => 'y'])->assertForbidden();

        $this->post(route('admin.impersonation.exit'))->assertRedirect(route('admin.users.show', $customer));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.impersonation_started']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.impersonation_ended']);
    }

    public function test_view_as_client_skips_otp_for_unverified_customer_and_never_notifies_them(): void
    {
        Mail::fake();
        Notification::fake();
        $admin = $this->staff();
        $customer = $this->customer(['name' => 'Unverified Client', 'email' => 'unverified@example.test', 'email_verified_at' => null]);

        $this->actingAs($admin)->withSession($this->confirmed())
            ->post(route('admin.users.impersonate', $customer))
            ->assertRedirect(route('dashboard'));

        // No OTP wall: staff sees the customer's dashboard straight away.
        $this->get(route('dashboard'))->assertOk()->assertSee('Viewing as Unverified Client');
        $this->get(route('dashboard.services'))->assertOk();

        // The customer is never told: no OTP issued, no mail, no notification, still unverified.
        $this->assertSame(0, $customer->otps()->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_email_verification_still_applies_outside_a_real_staff_impersonation(): void
    {
        $customer = $this->customer(['email_verified_at' => null]);

        // The customer's own session is still sent to the OTP page.
        $this->actingAs($customer, 'web')->get(route('dashboard'))->assertRedirect(route('verification.notice'));

        // Forged session keys without a signed-in staff member with the permission do not bypass it.
        $this->actingAs($customer, 'web')
            ->withSession(['impersonator_id' => 999, 'impersonated_user_id' => $customer->id])
            ->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_impersonation_forbidden_without_permission_and_for_staff_or_inactive(): void
    {
        $customer = $this->customer();
        $billing = $this->staff('billing-staff');
        $this->actingAs($billing)->withSession($this->confirmed())
            ->post(route('admin.users.impersonate', $customer))->assertForbidden();

        $admin = $this->staff();
        $staffUser = $this->staff('technical-support');
        $this->actingAs($admin)->withSession($this->confirmed())
            ->post(route('admin.users.impersonate', $staffUser))->assertNotFound();

        $inactive = $this->customer();
        $inactive->forceFill(['is_active' => false])->save();
        $this->actingAs($admin)->withSession($this->confirmed())
            ->post(route('admin.users.impersonate', $inactive))->assertNotFound();
    }

    public function test_staff_list_is_paginated_and_dashboard_loads(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertOk();
    }
}
