<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use App\Services\Totp;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * End-to-end Super Admin onboarding on the disposable test database, using isolated test accounts only.
 * No real owner account, password or MFA secret is used.
 */
class StaffOnboardingTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const PW = 'Isolated-Test-Pass-7!';

    private const NEW_PW = 'Isolated-Owner-Choice-8?';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_status_command_is_read_only_and_explains_the_login_error(): void
    {
        $before = User::count();
        $this->artisan('staff:status', ['email' => 'nobody@example.test'])
            ->expectsOutputToContain('No account exists for nobody@example.test')
            ->expectsOutputToContain('php artisan staff:create-super nobody@example.test')
            ->assertSuccessful();

        $customer = $this->customer(['email' => 'cust@example.test']);
        $this->artisan('staff:status', ['email' => 'cust@example.test'])
            ->expectsOutputToContain('This is a customer account, so the staff login rejects it. It is never promoted automatically.')
            ->expectsOutputToContain('--promote')
            ->doesntExpectOutputToContain((string) $customer->password)
            ->assertSuccessful();

        $this->assertSame($before + 1, User::count());
        $this->assertFalse($customer->fresh()->isStaffAccount());
    }

    public function test_full_super_admin_onboarding_create_verify_change_password_enrol_mfa_and_mfa_is_then_required(): void
    {
        Mail::fake();
        config(['staff.enforce_privileged_mfa' => true]);

        $this->artisan('staff:create-super', ['email' => 'owner.test@example.test', 'name' => 'Test Owner'])
            ->expectsQuestion('Password (12+ characters, upper and lower case, number, symbol)', self::PW)
            ->expectsQuestion('Repeat the password', self::PW)
            ->doesntExpectOutputToContain(self::PW)
            ->assertSuccessful();

        $owner = User::where('email', 'owner.test@example.test')->firstOrFail();
        $this->assertTrue($owner->isSuper());
        $this->assertNotSame(self::PW, $owner->getRawOriginal('password'));

        // Wrong password: generic message, nothing revealed
        $this->post('/staff/developerjet/login', ['email' => $owner->email, 'password' => 'Wrong-Pass-1!'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match an active staff account.']);

        // 1. Password → 2. email code
        $this->post('/staff/developerjet/login', ['email' => $owner->email, 'password' => self::PW])->assertRedirect('/staff/developerjet/verify-email');
        $code = null;
        Mail::assertSent(OtpMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });
        $this->post('/staff/developerjet/verify-email', ['code' => $code]);
        $this->assertAuthenticatedAs($owner->fresh(), 'staff');

        // 3. Forced password change, then 4. mandatory MFA enrolment before the dashboard opens
        $this->get('/admin')->assertRedirect(route('admin.profile'));
        $this->put('/admin/profile/password', ['current_password' => self::PW, 'password' => self::NEW_PW, 'password_confirmation' => self::NEW_PW])->assertSessionHasNoErrors();
        $this->get('/admin')->assertRedirect(route('admin.security'));

        $this->post('/admin/security/start');
        $secret = $owner->fresh()->two_factor_secret;
        $totp = app(Totp::class);
        $this->post('/admin/security/confirm', ['code' => $totp->code($secret, intdiv(time(), 30))])->assertSessionHasNoErrors();
        $this->get('/admin')->assertOk();

        // Sign out; the next sign-in must pass the authenticator step (MFA preserved, not bypassable)
        $this->post(route('admin.logout'));
        auth()->guard('staff')->logout();
        auth()->guard('web')->logout();
        $this->post('/staff/developerjet/login', ['email' => $owner->email, 'password' => self::NEW_PW])->assertRedirect('/staff/developerjet/two-factor');
        $this->assertGuest('staff');
        $this->get('/admin')->assertNotFound();
        $this->post('/staff/developerjet/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/staff/developerjet/two-factor', ['code' => $totp->code($secret, intdiv(time(), 30) + 1)]);
        $this->assertAuthenticatedAs($owner->fresh(), 'staff');
    }

    public function test_create_super_never_overwrites_an_existing_staff_account(): void
    {
        $existing = $this->staff('admin', ['email' => 'existing@example.test', 'password' => self::PW]);
        $hash = $existing->fresh()->getRawOriginal('password');

        $this->artisan('staff:create-super', ['email' => 'existing@example.test'])->assertFailed();
        $this->assertSame($hash, $existing->fresh()->getRawOriginal('password'));
        $this->assertFalse($existing->fresh()->isSuper());
    }

    public function test_every_role_uses_the_same_configurable_login_url(): void
    {
        foreach (['super-admin', 'admin', 'billing-staff', 'technical-support', 'sales-staff', 'developer', 'seo-manager'] as $i => $role) {
            $user = $this->staff($role, ['email' => "role{$i}@example.test", 'password' => self::PW]);
            $this->post('/staff/developerjet/login', ['email' => $user->email, 'password' => self::PW])->assertRedirect();
            $this->assertAuthenticatedAs($user, 'staff');
            auth()->guard('staff')->logout();
            auth()->guard('web')->logout();
        }

        Setting::set('staff_portal.slug', 'team-entry');
        $this->get('/staff/developerjet/login')->assertNotFound();
        $this->get('/staff/team-entry/login')->assertOk();
    }
}
