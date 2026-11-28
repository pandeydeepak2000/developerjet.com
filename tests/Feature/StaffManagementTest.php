<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Mail\StaffPasswordResetMail;
use App\Models\Department;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestone 4: staff management (invite, suspend, reactivate, reset access, sessions, history, escalation guards). */
class StaffManagementTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const PW = 'Correct-Horse-9!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function confirmed(): array
    {
        return ['staff_reauth_at' => time()];
    }

    public function test_invitation_lets_the_invitee_choose_their_own_password_once(): void
    {
        Mail::fake();
        $super = $this->staff();
        $roleId = Role::where('slug', 'technical-support')->value('id');
        $dept = Department::where('slug', 'technical-support')->value('id');

        $this->actingAs($super)->post('/admin/staff/invitations', ['name' => 'Tara', 'email' => 'Tara@Example.test', 'role_id' => $roleId, 'departments' => [$dept]])
            ->assertSessionHas('success');
        $url = null;
        Mail::assertSent(StaffInvitationMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });
        $invitation = StaffInvitation::firstOrFail();
        $this->assertSame('tara@example.test', $invitation->email);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertNotSame($token, $invitation->token_hash); // only the hash is stored
        $this->assertStringContainsString('/staff/developerjet/invitation/', $url);

        auth()->guard('staff')->logout();
        $this->get('/staff/developerjet/invitation/not-a-token')->assertNotFound();
        $this->get('/staff/developerjet/invitation/'.$token)->assertOk()->assertSee('tara@example.test');
        $this->post('/staff/developerjet/invitation/'.$token, ['name' => 'Tara', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/staff/developerjet/invitation/'.$token, ['name' => 'Tara T', 'password' => self::PW, 'password_confirmation' => self::PW])
            ->assertRedirect(url('/staff/developerjet/login'));

        $tara = User::where('email', 'tara@example.test')->firstOrFail();
        $this->assertTrue($tara->isStaff());
        $this->assertSame('technical-support', $tara->staffRole->slug);
        $this->assertSame([$dept], $tara->departments->pluck('id')->all());
        $this->assertNotNull($tara->email_verified_at);
        $this->get('/staff/developerjet/invitation/'.$token)->assertNotFound(); // single use

        $this->post('/staff/developerjet/login', ['email' => 'tara@example.test', 'password' => self::PW])->assertRedirect(route('admin.dashboard'));
    }

    public function test_expired_or_revoked_invitations_do_not_work_and_non_super_cannot_invite_super(): void
    {
        Mail::fake();
        $manager = $this->staff('admin');
        Role::where('slug', 'admin')->first()->permissions()->createMany([['permission' => 'staff.view'], ['permission' => 'staff.create']]);
        $superRole = Role::where('slug', 'super-admin')->value('id');
        $this->actingAs($manager)->post('/admin/staff/invitations', ['name' => 'X', 'email' => 'x@example.test', 'role_id' => $superRole])->assertForbidden();

        $inv = StaffInvitation::create(['email' => 'y@example.test', 'name' => 'Y', 'role_id' => Role::where('slug', 'developer')->value('id'),
            'token_hash' => hash('sha256', 'tok-expired'), 'expires_at' => now()->subMinute()]);
        $this->get('/staff/developerjet/invitation/tok-expired')->assertNotFound();
        $inv->update(['expires_at' => now()->addDay()]);
        $this->post(route('admin.staff.invitations.revoke', $inv))->assertSessionHas('success');
        $this->get('/staff/developerjet/invitation/tok-expired')->assertNotFound();

        // Existing accounts cannot be invited (no silent takeover of a customer account).
        $this->customer(['email' => 'cust@example.test']);
        $this->post('/admin/staff/invitations', ['name' => 'C', 'email' => 'cust@example.test', 'role_id' => Role::where('slug', 'developer')->value('id')])
            ->assertSessionHasErrors('email');
    }

    public function test_directly_created_staff_must_change_password_and_verify_email(): void
    {
        $super = $this->staff();
        $this->actingAs($super)->post('/admin/staff', ['name' => 'D', 'email' => 'd@example.test', 'password' => 'Temp-Password-1!', 'role_id' => Role::where('slug', 'developer')->value('id')])
            ->assertSessionHas('success');
        $d = User::where('email', 'd@example.test')->first();
        $this->assertTrue($d->password_change_required);
        $this->assertNull($d->email_verified_at);
    }

    public function test_suspend_and_reactivate_with_reauth_guards_and_session_revocation(): void
    {
        $super = $this->staff('super-admin', ['password' => self::PW]);
        $member = $this->staff('billing-staff', ['password' => self::PW]);

        $this->actingAs($super)->post(route('admin.staff.suspend', $member), ['reason' => 'Left'])->assertRedirect(route('admin.confirm-access'));
        $this->assertTrue($member->fresh()->is_active);

        $this->withSession($this->confirmed())->post(route('admin.staff.suspend', $member), ['reason' => 'Left the company'])->assertSessionHas('success');
        $fresh = $member->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertSame('Left the company', $fresh->suspension_reason);
        $this->assertSame(1, (int) $fresh->staff_session_epoch);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.suspended']);

        $this->post(route('admin.staff.suspend', $super), ['reason' => 'self'])->assertSessionHas('error');

        auth()->guard('staff')->logout();
        $this->post('/staff/developerjet/login', ['email' => $member->email, 'password' => self::PW])->assertSessionHasErrors('email');

        $this->actingAs($super)->withSession($this->confirmed())->post(route('admin.staff.reactivate', $member))->assertSessionHas('success');
        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_non_super_cannot_manage_a_super_admin_and_last_super_cannot_be_suspended(): void
    {
        $super = $this->staff('super-admin', ['password' => self::PW]);
        $manager = $this->staff('admin');
        Role::where('slug', 'admin')->first()->permissions()->createMany([['permission' => 'staff.view'], ['permission' => 'staff.edit']]);

        foreach (['suspend', 'reset-access', 'revoke-sessions', 'reactivate'] as $action) {
            $this->actingAs($manager)->withSession($this->confirmed())->post(route('admin.staff.'.$action, $super), ['reason' => 'x'])->assertForbidden();
        }

        $second = $this->staff('super-admin');
        $this->actingAs($super)->withSession($this->confirmed())->post(route('admin.staff.suspend', $second), ['reason' => 'test'])->assertSessionHas('success');
        // $super is now the only active Super Admin and cannot suspend (or demote) themselves.
        $this->post(route('admin.staff.suspend', $super), ['reason' => 'self'])->assertSessionHas('error');
        $this->assertTrue($super->fresh()->is_active);
        $this->assertSame(1, User::where('is_active', true)->whereHas('staffRole', fn ($q) => $q->where('is_super', true))->count());
    }

    public function test_reset_access_forces_new_password_signs_out_and_can_email_link(): void
    {
        Mail::fake();
        $super = $this->staff();
        $member = $this->staff('developer');

        $this->actingAs($super)->withSession($this->confirmed())->post(route('admin.staff.reset-access', $member), ['send_reset_link' => 1, 'reset_mfa' => 1])
            ->assertSessionHas('success');
        $fresh = $member->fresh();
        $this->assertTrue($fresh->password_change_required);
        $this->assertSame(1, (int) $fresh->staff_session_epoch);
        Mail::assertQueued(StaffPasswordResetMail::class, fn ($m) => $m->hasTo($member->email));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.access_reset']);
    }

    public function test_staff_detail_shows_login_history_and_security_events_without_secrets(): void
    {
        $super = $this->staff();
        $member = $this->staff('seo-manager', ['password' => self::PW]);
        $this->post('/staff/developerjet/login', ['email' => $member->email, 'password' => 'bad']);
        $this->post('/staff/developerjet/login', ['email' => $member->email, 'password' => self::PW]);
        $this->post('/admin/logout');

        $this->actingAs($super)->get(route('admin.staff.show', $member))->assertOk()
            ->assertSee('Login history')->assertSee('login failed')->assertSee('Security events')
            ->assertDontSee($member->getRawOriginal('password'));

        // Customers are not staff records.
        $this->get(route('admin.staff.show', $this->customer()))->assertNotFound();
        // Without staff.view: 403/404.
        $this->actingAs($this->staff('billing-staff'))->get(route('admin.staff.show', $member))->assertNotFound();
    }

    public function test_staff_cannot_grant_roles_above_their_own_permissions(): void
    {
        $manager = $this->staff('billing-staff');
        Role::where('slug', 'billing-staff')->first()->permissions()->createMany([['permission' => 'staff.view'], ['permission' => 'staff.create'], ['permission' => 'staff.edit']]);
        $admin = Role::where('slug', 'admin')->value('id'); // has permissions billing staff lack

        $this->actingAs($manager)->post('/admin/staff/invitations', ['name' => 'E', 'email' => 'e@example.test', 'role_id' => $admin])->assertForbidden();
        $peer = $this->staff('sales-staff');
        $this->withSession(['staff_reauth_at' => time()])->put('/admin/staff/'.$peer->id, ['role_id' => $admin, 'is_active' => 1])->assertForbidden();
    }

    public function test_sidebar_reflects_permissions(): void
    {
        // Sidebar = sections only; a section shows when at least one of its pages is allowed.
        $this->actingAs($this->staff('technical-support'))->get('/admin')->assertOk()
            ->assertSee('<span>Support</span>', false)->assertDontSee('<span>Settings</span>', false)->assertDontSee('<span>Products</span>', false)
            ->assertDontSee('Staff portal URL');
        $this->get('/admin/tickets')->assertOk()->assertSee('class="admin-tabs"', false)->assertSee('>Tickets</a>', false)->assertSee('>Departments</a>', false);

        $this->actingAs($this->staff('super-admin'))->get('/admin')->assertOk()->assertSee('Staff portal URL')
            ->assertSee('<span>Settings</span>', false)->assertSee('<span>Products</span>', false)->assertDontSee('class="admin-tabs"', false);
        // Related pages are tabs inside their section (nothing was removed).
        $this->get('/admin/settings')->assertOk()->assertSeeInOrder(['class="admin-tabs"', '>Settings</a>', '>Staff &amp; roles</a>', '>Exports</a>'], false);
        $this->get('/admin/tickets')->assertOk()->assertSeeInOrder(['>Tickets</a>', '>Departments</a>', '>Docs</a>'], false);
        $this->get('/admin/r/payments')->assertOk()->assertSeeInOrder(['>Payments</a>', '>Invoices</a>', '>Wallet</a>', '>Renewals</a>'], false);
    }
}
