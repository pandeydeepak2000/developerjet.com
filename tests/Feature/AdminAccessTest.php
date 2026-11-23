<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_guests_and_customers_get_404_on_admin(): void
    {
        $this->get('/admin')->assertNotFound();
        $this->actingAs($this->customer())->get('/admin')->assertNotFound();
        $this->get('/admin/r/customers')->assertNotFound();
    }

    public function test_customer_cannot_sign_in_through_admin_login(): void
    {
        $user = $this->customer(['password' => 'Secret-pass-1234']);

        $this->post('/staff/developerjet/login', ['email' => $user->email, 'password' => 'Secret-pass-1234'])->assertSessionHasErrors('email');
        $this->assertGuest('staff');
    }

    public function test_staff_can_sign_in_and_login_is_audited(): void
    {
        $staff = $this->staff('admin', ['password' => 'Secret-pass-1234']);

        $this->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'Secret-pass-1234'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff, 'staff');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login', 'user_id' => $staff->id]);
    }

    public function test_admin_login_is_throttled_after_repeated_failures(): void
    {
        $staff = $this->staff('admin', ['password' => 'Secret-pass-1234']);

        foreach (range(1, 5) as $i) {
            $this->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'wrong']);
        }

        $this->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'Secret-pass-1234'])->assertSessionHasErrors('email');
        $this->assertGuest('staff');
    }

    public function test_inactive_staff_cannot_use_admin(): void
    {
        $staff = $this->staff('admin');
        $staff->forceFill(['is_active' => false])->save();

        $this->actingAs($staff)->get('/admin')->assertNotFound();
    }

    public function test_permissions_are_enforced_on_backend_routes_not_just_sidebar(): void
    {
        $billing = $this->staff('billing-staff');

        $this->actingAs($billing)->get('/admin')->assertOk()->assertSee('Invoices')->assertDontSee('Staff &amp; roles', false);
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/plans')->assertForbidden();
        $this->get('/admin/r/servers')->assertForbidden();
        $this->get('/admin/staff')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->post('/admin/staff', ['name' => 'x'])->assertForbidden();
        $this->get('/admin/r/security_logs')->assertForbidden();
    }

    public function test_technical_support_cannot_edit_catalogue_but_developer_can_deploy_view(): void
    {
        $this->actingAs($this->staff('technical-support'))->get('/admin/plans/create')->assertForbidden();
        $this->actingAs($this->staff('developer'))->get('/admin/r/deployments')->assertOk();
        $this->actingAs($this->staff('seo-manager'))->get('/admin/seo')->assertOk();
        $this->actingAs($this->staff('sales-staff'))->get('/admin/r/payments')->assertForbidden();
    }

    public function test_generic_resource_actions_check_action_permissions(): void
    {
        // billing staff may view customers but customers have no create action at all.
        $this->actingAs($this->staff('billing-staff'))->get('/admin/r/customers')->assertOk();
        $this->get('/admin/r/customers/create')->assertForbidden();
        $this->get('/admin/r/categories')->assertForbidden();
    }

    public function test_only_super_admin_can_configure_roles(): void
    {
        $role = Role::create(['slug' => 'custom', 'name' => 'Custom']);

        $this->actingAs($this->staff('admin'))->post('/admin/roles/'.$role->id, ['name' => 'Custom', 'permissions' => ['orders.view']])->assertForbidden();

        $this->actingAs($this->staff('super-admin'))->post('/admin/roles/'.$role->id, ['name' => 'Custom', 'permissions' => ['orders.view', 'bogus.perm']])->assertRedirect();
        $this->assertSame(['orders.view'], $role->permissions()->pluck('permission')->all());
    }

    public function test_custom_role_permissions_take_effect(): void
    {
        $role = Role::create(['slug' => 'viewer', 'name' => 'Viewer']);
        $role->permissions()->createMany([['permission' => 'dashboard.view'], ['permission' => 'orders.view']]);
        $user = User::factory()->create();
        $user->forceFill(['role' => 'staff', 'role_id' => $role->id])->save();

        $this->actingAs($user)->get('/admin/orders')->assertOk();
        $this->get('/admin/wallet')->assertForbidden();
    }

    public function test_staff_cannot_escalate_privileges_or_remove_last_super_admin(): void
    {
        $super = $this->staff('super-admin');
        $manager = $this->staff('admin');
        Role::where('slug', 'admin')->first()->permissions()->createMany([['permission' => 'staff.edit'], ['permission' => 'staff.create'], ['permission' => 'staff.view']]);
        $superRoleId = Role::where('slug', 'super-admin')->value('id');
        $adminRoleId = Role::where('slug', 'admin')->value('id');

        // Non-super cannot assign Super Admin or modify one.
        $this->actingAs($manager)->post('/admin/staff', ['name' => 'N', 'email' => 'n@example.test', 'password' => 'Longpassword123', 'role_id' => $superRoleId])->assertForbidden();
        $this->withSession(['staff_reauth_at' => time()])->put('/admin/staff/'.$super->id, ['role_id' => $adminRoleId, 'is_active' => 1])->assertForbidden();

        // The sole super admin cannot demote themselves.
        $this->actingAs($super)->put('/admin/staff/'.$super->id, ['role_id' => $adminRoleId, 'is_active' => 1])->assertSessionHas('error');
        $this->assertTrue($super->fresh()->isSuper());
    }
}
