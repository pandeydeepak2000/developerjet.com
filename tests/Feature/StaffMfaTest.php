<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Totp;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class StaffMfaTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function enrol(User $user): string
    {
        $totp = app(Totp::class);
        $this->actingAs($user)->post('/admin/security/start')->assertRedirect();
        $secret = $user->fresh()->two_factor_secret;
        $this->post('/admin/security/confirm', ['code' => $totp->code($secret, intdiv(time(), 30))])->assertRedirect()->assertSessionHasNoErrors();
        auth()->guard('web')->logout();
        auth()->guard('staff')->logout();

        return $secret;
    }

    public function test_totp_matches_the_rfc_6238_reference_vector(): void
    {
        // RFC 6238 appendix B, SHA-1 secret "12345678901234567890", T=59 => 94287082 (last 6 digits 287082).
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', app(Totp::class)->code($secret, intdiv(59, 30)));
        $this->assertNotNull(app(Totp::class)->verify($secret, '287082', 59));
        $this->assertNull(app(Totp::class)->verify($secret, '000000', 59));
        $this->assertNull(app(Totp::class)->verify($secret, 'abcdef', 59));
    }

    public function test_totp_rejects_replay_of_the_same_step(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $step = intdiv(1000, 30);
        $code = $totp->code($secret, $step);
        $this->assertSame($step, $totp->verify($secret, $code, 1000));
        $this->assertNull($totp->verify($secret, $code, 1000, $step));
    }

    public function test_enrolled_staff_must_pass_the_second_step_and_are_not_logged_in_before_it(): void
    {
        $staff = $this->staff('super-admin', ['password' => bcrypt('pw-123456789')]);
        $secret = $this->enrol($staff);
        $totp = app(Totp::class);

        $this->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'pw-123456789'])->assertRedirect('/staff/developerjet/two-factor');
        $this->assertGuest('staff');
        $this->get('/admin')->assertNotFound();

        $this->post('/staff/developerjet/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest('staff');

        // The step used while enrolling is spent; use the next one.
        $this->post('/staff/developerjet/two-factor', ['code' => $totp->code($secret, intdiv(time(), 30) + 1)])->assertRedirect();
        $this->assertAuthenticatedAs($staff, 'staff');
        $this->get('/admin')->assertOk();
    }

    public function test_wrong_password_never_reaches_the_challenge(): void
    {
        $staff = $this->staff('super-admin', ['password' => bcrypt('pw-123456789')]);
        $this->enrol($staff);

        $this->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->get('/staff/developerjet/two-factor')->assertRedirect('/staff/developerjet/login');
    }

    public function test_challenge_is_rate_limited(): void
    {
        $staff = $this->staff('super-admin', ['password' => bcrypt('pw-123456789')]);
        $this->enrol($staff);
        $this->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'pw-123456789']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/staff/developerjet/two-factor', ['code' => '111111'])->assertSessionHasErrors('code');
        }
        $this->post('/staff/developerjet/two-factor', ['code' => '111111'])->assertSessionHasErrors('code');
        $this->assertGuest('staff');
    }

    public function test_secret_is_encrypted_at_rest_and_never_serialised(): void
    {
        $staff = $this->staff();
        $this->enrol($staff);
        $fresh = $staff->fresh();

        $this->assertStringNotContainsString($fresh->two_factor_secret, $fresh->getRawOriginal('two_factor_secret'));
        $this->assertArrayNotHasKey('two_factor_secret', $fresh->toArray());
    }

    public function test_enforcement_redirects_unenrolled_staff_to_security_page(): void
    {
        Setting::set('security.require_staff_mfa', '1');
        $staff = $this->staff();

        $this->actingAs($staff)->get('/admin')->assertRedirect(route('admin.security'));
        $this->get('/admin/security')->assertOk()->assertSee('requires two-factor');

        Setting::set('security.require_staff_mfa', '0');
        $this->get('/admin')->assertOk();
    }

    public function test_reset_requires_permission_blocks_self_and_is_audited(): void
    {
        $victim = $this->staff('technical-support');
        $this->enrol($victim);
        $this->assertTrue($victim->fresh()->hasTwoFactor());

        $this->actingAs($this->staff('sales-staff'))->post(route('admin.staff.reset-mfa', $victim))->assertForbidden();
        $this->actingAs($victim)->post(route('admin.staff.reset-mfa', $victim))->assertForbidden();

        $this->actingAs($this->staff())->withSession(['staff_reauth_at' => time()])->post(route('admin.staff.reset-mfa', $victim))->assertSessionHas('success');
        $this->assertFalse($victim->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.mfa_reset']);
    }

    public function test_customers_cannot_reach_security_pages(): void
    {
        $this->actingAs($this->customer())->get('/admin/security')->assertNotFound();
        $this->post('/admin/security/start')->assertNotFound();
    }
}
