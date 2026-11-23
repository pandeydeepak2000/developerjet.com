<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Mail\StaffPasswordResetMail;
use App\Models\Role;
use App\Models\User;
use App\Services\Totp;
use App\Support\Setting;
use App\Support\StaffPortal;
use App\Support\StaffSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestones 1–3 and 6: staff portal, configurable login URL, Super Admin initialisation, staff MFA. */
class StaffPortalTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const PW = 'Correct-Horse-9!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function signIn(User $user, string $slug = 'developerjet'): TestResponse
    {
        return $this->post("/staff/{$slug}/login", ['email' => $user->email, 'password' => self::PW]);
    }

    private function enrol(User $user): string
    {
        $totp = app(Totp::class);
        $this->actingAs($user)->post('/admin/security/start');
        $secret = $user->fresh()->two_factor_secret;
        $this->post('/admin/security/confirm', ['code' => $totp->code($secret, intdiv(time(), 30))])->assertSessionHasNoErrors();
        auth()->guard('staff')->logout();
        auth()->guard('web')->logout();

        return $secret;
    }

    // ---------------------------------------------------------------- Milestone 1: portal

    public function test_single_staff_login_url_and_old_admin_login_is_gone(): void
    {
        $this->assertSame(url('/staff/developerjet/login'), StaffPortal::url('login'));
        $this->get('/staff/developerjet/login')->assertOk()->assertSee('Staff sign in')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/admin/login')->assertNotFound();
        $this->get('/staff/other-slug/login')->assertNotFound();
    }

    public function test_every_role_signs_in_at_the_same_url_and_lands_on_a_permitted_page(): void
    {
        foreach (['super-admin', 'admin', 'billing-staff', 'technical-support', 'sales-staff', 'developer', 'seo-manager'] as $slug) {
            $user = $this->staff($slug, ['password' => self::PW]);
            $this->signIn($user)->assertRedirect(route('admin.dashboard'));
            $this->assertAuthenticatedAs($user, 'staff');
            $this->get('/admin')->assertOk();
            $this->post('/admin/logout')->assertRedirect(StaffPortal::url('login'));
            $this->assertGuest('staff');
        }

        // A custom role without the dashboard lands on its first permitted module.
        $role = Role::create(['slug' => 'queue-only', 'name' => 'Queue only']);
        $role->permissions()->create(['permission' => 'tickets.view']);
        $agent = $this->customer(['password' => self::PW]);
        $agent->forceFill(['role' => 'staff', 'role_id' => $role->id])->save();
        $this->signIn($agent)->assertRedirect(route('admin.tickets.index'));
    }

    public function test_customer_and_staff_sessions_are_isolated(): void
    {
        $staff = $this->staff('super-admin', ['password' => self::PW]);

        // Customer-area sign-in with a staff account does not open the admin area.
        $this->post('/login', ['email' => $staff->email, 'password' => self::PW])->assertRedirect();
        $this->assertAuthenticatedAs($staff, 'web');
        $this->get('/admin')->assertNotFound();
        $this->assertGuest('staff');
        $this->post('/logout');

        // A staff sign-in does not sign in the customer guard.
        $this->signIn($staff)->assertRedirect();
        $this->assertGuest('web');
        $this->get('/dashboard')->assertRedirect(route('login'));

        // A customer session never reaches staff routes.
        auth()->guard('staff')->logout();
        $this->actingAs($this->customer(), 'web')->get('/admin/profile')->assertNotFound();
    }

    public function test_known_staff_browser_is_sent_to_the_current_login_url_others_get_404(): void
    {
        $this->get('/admin/orders')->assertNotFound();
        $this->withCookie('dj_staff_portal', '1')->get('/admin/orders')->assertRedirect(StaffPortal::url('login'));
    }

    public function test_session_revocation_ends_existing_staff_sessions(): void
    {
        $staff = $this->staff('admin', ['password' => self::PW]);
        $this->signIn($staff);
        $this->get('/admin')->assertOk();

        StaffSecurity::revokeSessions($staff->fresh());
        auth()->guard('staff')->forgetUser(); // the next real request re-reads the account from the database
        $this->get('/admin')->assertNotFound();
        $this->assertGuest('staff');
    }

    public function test_unverified_staff_email_must_be_proven_with_a_code_at_first_sign_in(): void
    {
        Mail::fake();
        $staff = $this->staff('admin', ['password' => self::PW, 'email_verified_at' => null]);

        $this->signIn($staff)->assertRedirect('/staff/developerjet/verify-email');
        $this->assertGuest('staff');
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $this->post('/staff/developerjet/verify-email', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/staff/developerjet/verify-email', ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff, 'staff');
        $this->assertNotNull($staff->fresh()->email_verified_at);
    }

    public function test_staff_password_reset_is_separate_from_customer_reset(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // many guest POSTs from one IP in one test
        Mail::fake();
        Notification::fake();
        $staff = $this->staff('technical-support', ['password' => self::PW]);
        $customer = $this->customer();

        // The customer form never sends anything for a staff account.
        $this->post('/forgot-password', ['email' => $staff->email])->assertSessionHas('status');
        Notification::assertNothingSentTo($staff);

        // The staff form never sends anything for a customer, with the same generic response.
        $this->post('/staff/developerjet/forgot-password', ['email' => $customer->email])->assertSessionHas('status');
        Mail::assertNothingSent();

        $this->post('/staff/developerjet/forgot-password', ['email' => $staff->email])->assertSessionHas('status');
        $url = null;
        Mail::assertQueued(StaffPasswordResetMail::class, function (StaffPasswordResetMail $m) use (&$url) {
            $url = $m->url;

            return true;
        });
        $this->assertStringContainsString('/staff/developerjet/reset-password/', $url);
        $token = explode('?', basename(parse_url($url, PHP_URL_PATH)))[0];

        // A staff token cannot be redeemed through the customer form.
        $this->post('/reset-password', ['token' => $token, 'email' => $staff->email, 'password' => 'Another-pass-123', 'password_confirmation' => 'Another-pass-123'])
            ->assertSessionHasErrors('email');

        // Weak passwords are refused; a strong one works and revokes sessions.
        $this->post('/staff/developerjet/reset-password', ['token' => $token, 'email' => $staff->email, 'password' => 'weakpassword', 'password_confirmation' => 'weakpassword'])
            ->assertSessionHasErrors('password');
        $epoch = (int) $staff->fresh()->staff_session_epoch;
        $this->post('/staff/developerjet/reset-password', ['token' => $token, 'email' => $staff->email, 'password' => 'Brand-New-Pass-77', 'password_confirmation' => 'Brand-New-Pass-77'])
            ->assertRedirect(StaffPortal::url('login'));
        $this->assertTrue(Hash::check('Brand-New-Pass-77', $staff->fresh()->password));
        $this->assertSame($epoch + 1, (int) $staff->fresh()->staff_session_epoch);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.password_reset']);
    }

    public function test_required_password_change_blocks_admin_until_done(): void
    {
        $staff = $this->staff('admin', ['password' => self::PW]);
        $staff->forceFill(['password_change_required' => true])->save();

        $this->actingAs($staff)->get('/admin')->assertRedirect(route('admin.profile'));
        $this->get('/admin/profile')->assertOk()->assertSee('must choose a new password');

        $this->put('/admin/profile/password', ['current_password' => 'nope', 'password' => 'New-Strong-Pass-1', 'password_confirmation' => 'New-Strong-Pass-1'])
            ->assertSessionHasErrors('current_password');
        $this->put('/admin/profile/password', ['current_password' => self::PW, 'password' => 'New-Strong-Pass-1', 'password_confirmation' => 'New-Strong-Pass-1'])
            ->assertRedirect(route('admin.profile'));
        $this->assertFalse($staff->fresh()->password_change_required);
        $this->get('/admin')->assertOk(); // own session survives, others are revoked
    }

    // ---------------------------------------------------------------- Milestone 2: dynamic URL

    public function test_only_super_admin_can_open_staff_portal_settings(): void
    {
        $this->actingAs($this->staff('admin'))->get('/admin/settings/staff-portal')->assertForbidden();
        $this->put('/admin/settings/staff-portal', ['slug' => 'new-door', 'password' => 'x'])->assertForbidden();

        $this->actingAs($this->staff('super-admin'))->get('/admin/settings/staff-portal')->assertOk()
            ->assertSee('/staff/developerjet/login')->assertSee('Change history');
    }

    public function test_super_admin_changes_slug_with_reauthentication_and_old_url_retires(): void
    {
        $super = $this->staff('super-admin', ['password' => self::PW]);
        $other = $this->staff('billing-staff', ['password' => self::PW]);
        $this->actingAs($super);

        $this->put('/admin/settings/staff-portal', ['slug' => 'ambition-control', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertSame('developerjet', StaffPortal::slug());

        $this->put('/admin/settings/staff-portal', ['slug' => 'ambition-control', 'password' => self::PW])
            ->assertRedirect(route('admin.settings.staff-portal'))->assertSessionHas('success');
        $this->assertSame('ambition-control', StaffPortal::slug());
        $this->assertDatabaseHas('settings', ['key' => 'staff_portal.slug', 'value' => 'ambition-control']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_portal.slug_changed', 'user_id' => $super->id]);

        // The current session is unaffected; history shows the change.
        $this->get('/admin/settings/staff-portal')->assertOk()->assertSee('/staff/ambition-control/login')->assertSee('/staff/developerjet/login');

        // Old URL retired (plain 404 for every portal page — no redirect, no loop); new URL works for every role.
        auth()->guard('staff')->logout();
        $this->get('/staff/developerjet/login')->assertNotFound();
        $this->post('/staff/developerjet/login', ['email' => $other->email, 'password' => self::PW])->assertNotFound();
        $this->get('/staff/developerjet/forgot-password')->assertNotFound();
        $this->get('/staff/ambition-control/login')->assertOk();
        $this->signIn($other, 'ambition-control')->assertRedirect(route('admin.dashboard'));
        $this->post('/admin/logout')->assertRedirect(url('/staff/ambition-control/login'));
    }

    public function test_slug_validation_rejects_bad_reserved_and_current_values(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $super = $this->staff('super-admin', ['password' => self::PW]);
        $this->actingAs($super);

        foreach (['admin', 'login', 'ab', 'double--hyphen', '-lead', 'trail-', 'sp ace', 'a/b', '1234', 'developerjet', str_repeat('a', 41), 'x.y'] as $bad) {
            $res = $this->put('/admin/settings/staff-portal', ['slug' => $bad, 'password' => self::PW]);
            $res->assertSessionHasErrors('slug');
        }
        $this->assertSame('developerjet', StaffPortal::slug());

        $this->getJson('/admin/settings/staff-portal/check?slug=admin')->assertJson(['ok' => false]);
        $this->getJson('/admin/settings/staff-portal/check?slug=team-door-7')->assertJson(['ok' => true, 'url' => url('/staff/team-door-7/login')]);
    }

    public function test_slug_change_requires_authenticator_code_when_enrolled(): void
    {
        $super = $this->staff('super-admin', ['password' => self::PW]);
        $secret = $this->enrol($super);

        $this->actingAs($super->fresh())->put('/admin/settings/staff-portal', ['slug' => 'secure-door', 'password' => self::PW])->assertSessionHasErrors('code');
        $this->put('/admin/settings/staff-portal', ['slug' => 'secure-door', 'password' => self::PW, 'code' => '000000'])->assertSessionHasErrors('password');
        $code = app(Totp::class)->code($secret, intdiv(time(), 30) + 1);
        $this->put('/admin/settings/staff-portal', ['slug' => 'secure-door', 'password' => self::PW, 'code' => $code])->assertSessionHas('success');
        $this->assertSame('secure-door', StaffPortal::slug());
    }

    public function test_cli_recovery_command_shows_sets_and_resets_the_url(): void
    {
        $this->artisan('staff:portal')->expectsOutputToContain('/staff/developerjet/login')->assertSuccessful();
        $this->artisan('staff:portal', ['--set' => 'admin', '--force' => true])->assertFailed();
        $this->artisan('staff:portal', ['--set' => 'night-door', '--force' => true])->expectsOutputToContain('/staff/night-door/login')->assertSuccessful();
        $this->assertSame('night-door', StaffPortal::slug());
        $this->artisan('staff:portal', ['--reset' => true, '--force' => true])->expectsOutputToContain('/staff/developerjet/login')->assertSuccessful();
        $this->assertSame('developerjet', StaffPortal::slug());
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_portal.slug_changed', 'user_id' => null]);
    }

    public function test_invalid_stored_slug_falls_back_to_default_so_staff_are_never_locked_out(): void
    {
        Setting::set('staff_portal.slug', 'Bad Value!');
        $this->assertSame('developerjet', StaffPortal::slug());
        $this->get('/staff/developerjet/login')->assertOk();
    }

    // ---------------------------------------------------------------- Milestone 3: Super Admin initialisation

    public function test_create_super_prompts_for_a_strong_password_and_requires_first_login_steps(): void
    {
        $this->artisan('staff:create-super', ['email' => 'owner@example.test', 'name' => 'Owner'])
            ->expectsQuestion('Password (12+ characters, upper and lower case, number, symbol)', 'weak')
            ->expectsQuestion('Repeat the password', 'weak')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);

        $this->artisan('staff:create-super', ['email' => 'owner@example.test', 'name' => 'Owner'])
            ->expectsQuestion('Password (12+ characters, upper and lower case, number, symbol)', self::PW)
            ->expectsQuestion('Repeat the password', self::PW)
            ->doesntExpectOutputToContain(self::PW)
            ->expectsOutputToContain('/staff/developerjet/login')
            ->assertSuccessful();

        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($owner->isSuper());
        $this->assertTrue($owner->password_change_required);
        $this->assertNull($owner->email_verified_at);
        $this->assertNotSame(self::PW, $owner->getRawOriginal('password'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.super_created']);

        // First sign-in: email code, then password change, then (privileged) MFA enrolment.
        Mail::fake();
        config(['staff.enforce_privileged_mfa' => true]);
        $this->signIn($owner)->assertRedirect('/staff/developerjet/verify-email');
        $code = null;
        Mail::assertSent(OtpMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });
        $this->post('/staff/developerjet/verify-email', ['code' => $code]);
        $this->get('/admin')->assertRedirect(route('admin.profile'));
        $this->put('/admin/profile/password', ['current_password' => self::PW, 'password' => 'Owner-Chosen-Pass-2', 'password_confirmation' => 'Owner-Chosen-Pass-2']);
        $this->get('/admin')->assertRedirect(route('admin.security'));
    }

    public function test_existing_account_is_never_promoted_without_explicit_flag_and_proof_of_control(): void
    {
        $customer = $this->customer(['email' => 'owner@example.test', 'password' => self::PW]);

        $this->artisan('staff:create-super', ['email' => 'owner@example.test'])->assertFailed();
        $this->assertFalse($customer->fresh()->isStaffAccount());

        $this->artisan('staff:create-super', ['email' => 'owner@example.test', '--promote' => true])
            ->expectsQuestion('Type the email address again to confirm promotion to Super Admin', 'owner@example.test')
            ->expectsQuestion('Current password of this account (proof of control)', 'wrong-1')
            ->expectsQuestion('Current password of this account (proof of control)', 'wrong-2')
            ->expectsQuestion('Current password of this account (proof of control)', 'wrong-3')
            ->assertFailed();
        $this->assertFalse($customer->fresh()->isStaffAccount());

        $this->artisan('staff:create-super', ['email' => 'owner@example.test', '--promote' => true])
            ->expectsQuestion('Type the email address again to confirm promotion to Super Admin', 'owner@example.test')
            ->expectsQuestion('Current password of this account (proof of control)', self::PW)
            ->assertSuccessful();
        $fresh = $customer->fresh();
        $this->assertTrue($fresh->isSuper());
        $this->assertTrue($fresh->password_change_required);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.super_promoted']);
    }

    // ---------------------------------------------------------------- Milestone 6: MFA

    public function test_privileged_accounts_must_enrol_mfa_and_cannot_disable_it(): void
    {
        config(['staff.enforce_privileged_mfa' => true]);
        $super = $this->staff('super-admin', ['password' => self::PW]);

        $this->actingAs($super)->get('/admin')->assertRedirect(route('admin.security'));
        $this->actingAs($this->staff('billing-staff'))->get('/admin')->assertOk();

        $secret = $this->enrol($super);
        $this->actingAs($super->fresh())->get('/admin')->assertOk();
        $code = app(Totp::class)->code($secret, intdiv(time(), 30) + 1);
        $this->post('/admin/security/disable', ['password' => self::PW, 'code' => $code])->assertSessionHas('error');
        $this->assertTrue($super->fresh()->hasTwoFactor());
    }

    public function test_recovery_codes_are_hashed_single_use_and_rotatable(): void
    {
        $staff = $this->staff('admin', ['password' => self::PW]);
        $totp = app(Totp::class);
        $this->actingAs($staff)->post('/admin/security/start');
        $secret = $staff->fresh()->two_factor_secret;
        $response = $this->post('/admin/security/confirm', ['code' => $totp->code($secret, intdiv(time(), 30))]);
        $response->assertRedirect();
        $codes = session('recovery_codes');
        $this->assertCount(10, $codes);

        $raw = (string) $staff->fresh()->getRawOriginal('two_factor_recovery_codes');
        foreach ($codes as $c) {
            $this->assertStringNotContainsString($c, $raw);
        }
        $this->get('/admin/security')->assertOk()->assertSee($codes[0]); // shown once (flash)
        $this->get('/admin/security')->assertOk()->assertDontSee($codes[0])->assertDontSee($secret);

        auth()->guard('staff')->logout();
        $this->signIn($staff)->assertRedirect('/staff/developerjet/two-factor');
        $this->post('/staff/developerjet/two-factor', ['code' => strtolower($codes[0])])->assertRedirect();
        $this->assertAuthenticatedAs($staff, 'staff');
        $this->assertSame(9, StaffSecurity::remainingRecoveryCodes($staff->fresh()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.mfa_recovery_code_used']);

        $this->post('/admin/logout');
        $this->signIn($staff);
        $this->post('/staff/developerjet/two-factor', ['code' => $codes[0]])->assertSessionHasErrors('code');

        // Rotation invalidates the old set.
        $this->post('/staff/developerjet/two-factor', ['code' => $codes[1]]);
        $res = $this->post('/admin/security/recovery-codes', ['password' => self::PW, 'code' => $codes[2]]);
        $res->assertSessionHas('recovery_codes');
        $new = session('recovery_codes');
        $this->assertCount(10, $new);
        $this->assertFalse(StaffSecurity::consumeRecoveryCode($staff->fresh(), $codes[3]));
        $this->assertTrue(StaffSecurity::consumeRecoveryCode($staff->fresh(), $new[0]));
    }

    public function test_sensitive_actions_require_recent_reauthentication(): void
    {
        $super = $this->staff('super-admin', ['password' => self::PW]);
        $victim = $this->staff('technical-support');

        $this->actingAs($super)->from('/admin/staff/'.$victim->id)->post(route('admin.staff.revoke-sessions', $victim))->assertRedirect(route('admin.confirm-access'));
        $this->post('/admin/confirm-access', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/admin/confirm-access', ['password' => self::PW])->assertRedirect(url('/admin/staff/'.$victim->id));
        $this->post(route('admin.staff.revoke-sessions', $victim))->assertSessionHas('success');
        $this->assertSame(1, (int) $victim->fresh()->staff_session_epoch);
    }
}
