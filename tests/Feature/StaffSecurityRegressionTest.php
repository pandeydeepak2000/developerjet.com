<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Totp;
use App\Support\Setting;
use App\Support\StaffSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Regression tests for the independent security review of the completion sprint (findings 1–6, 8). */
class StaffSecurityRegressionTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
    }

    public function test_legacy_owner_with_only_a_customer_session_cannot_read_other_customers_records(): void
    {
        $this->approve('hermes-cli-launch');
        $order = $this->placeOrder($this->customer(), 'hermes-cli-launch', 'tok-x');
        $owner = User::factory()->create();
        $owner->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($owner, 'web')->get('/dashboard/orders/'.$order->id)->assertForbidden();
        $this->get('/admin')->assertNotFound();
    }

    public function test_non_super_manager_cannot_touch_a_suspended_super_admin(): void
    {
        $super = $this->staff('super-admin');
        $super->forceFill(['is_active' => false])->save();
        $this->staff('super-admin'); // keep one active super
        $manager = $this->staff('admin');
        Role::where('slug', 'admin')->first()->permissions()->createMany([['permission' => 'staff.view'], ['permission' => 'staff.edit']]);
        $adminRole = Role::where('slug', 'admin')->value('id');

        $this->actingAs($manager)->withSession(['staff_reauth_at' => time()])
            ->put('/admin/staff/'.$super->id, ['role_id' => $adminRole, 'is_active' => 1])->assertForbidden();
        $this->post(route('admin.staff.reset-mfa', $super))->assertForbidden();
        $this->assertFalse($super->fresh()->is_active);
        $this->assertTrue($super->fresh()->isSuperAccount());
    }

    public function test_staff_update_requires_reauthentication(): void
    {
        $super = $this->staff();
        $member = $this->staff('developer');
        $this->actingAs($super)->put('/admin/staff/'.$member->id, ['role_id' => $member->role_id, 'is_active' => 0])->assertRedirect(route('admin.confirm-access'));
        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_mail_and_staff_security_settings_are_super_admin_only_with_reauth(): void
    {
        $role = Role::create(['slug' => 'settings-editor', 'name' => 'Settings editor']);
        $role->permissions()->createMany([['permission' => 'settings.view'], ['permission' => 'settings.edit']]);
        $editor = $this->customer();
        $editor->forceFill(['role' => 'staff', 'role_id' => $role->id])->save();

        $this->actingAs($editor)->withSession(['staff_reauth_at' => time()])
            ->put('/admin/settings', ['section' => 'smtp', 'smtp__host' => 'evil.example.test'])->assertForbidden();
        $this->put('/admin/settings', ['section' => 'security'])->assertForbidden();
        $this->assertNull(Setting::get('smtp.host'));

        $super = $this->staff();
        session()->forget('staff_reauth_at');
        $this->actingAs($super)->put('/admin/settings', ['section' => 'security', 'security__require_staff_mfa' => 1])->assertRedirect();
        // Other sections are unaffected.
        $this->actingAs($editor)->put('/admin/settings', ['section' => 'company', 'company__name' => 'AmbitionHost Pvt. Ltd.'])->assertRedirect();
    }

    public function test_recovery_code_and_totp_step_cannot_be_used_twice_by_parallel_requests(): void
    {
        $staff = $this->staff('admin');
        $codes = StaffSecurity::generateRecoveryCodes($staff);
        $staleA = User::find($staff->id);
        $staleB = User::find($staff->id); // both loaded before either request consumes the code

        $this->assertTrue(StaffSecurity::consumeRecoveryCode($staleA, $codes[0]));
        $this->assertFalse(StaffSecurity::consumeRecoveryCode($staleB, $codes[0]));

        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $staff->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => null])->save();
        $code = $totp->code($secret, intdiv(time(), 30));
        $a = User::find($staff->id);
        $b = User::find($staff->id);
        $this->assertSame('totp', StaffSecurity::verifySecondFactor($a, $code));
        $this->assertNull(StaffSecurity::verifySecondFactor($b, $code));
    }

    public function test_mfa_attempts_are_limited_per_account_across_ips(): void
    {
        $staff = $this->staff('admin', ['password' => bcrypt('Correct-Horse-9!')]);
        $secret = app(Totp::class)->generateSecret();
        $staff->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        foreach (range(1, 10) as $i) {
            $ip = '198.51.100.'.$i;
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'Correct-Horse-9!']);
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/staff/developerjet/two-factor', ['code' => '000000']);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'Correct-Horse-9!']);
        $valid = app(Totp::class)->code($secret, intdiv(time(), 30));
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])->post('/staff/developerjet/two-factor', ['code' => $valid])
            ->assertSessionHasErrors('code');
        $this->assertGuest('staff');
    }

    public function test_failed_staff_logins_are_not_attributed_to_a_signed_in_customer(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'web')->post('/staff/developerjet/login', ['email' => 'nobody@example.test', 'password' => 'x']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login_failed', 'user_id' => null]);
    }
}
