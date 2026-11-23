<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * Priority 1: once signed in, staff stay signed in while navigating the admin area (sliding session),
 * but idle sessions still expire after the configurable lifetime and sensitive actions still re-auth.
 */
class StaffSessionPersistenceTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const PW = 'Correct-Horse-9!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function signIn(User $user): void
    {
        $this->post('/staff/developerjet/login', ['email' => $user->email, 'password' => self::PW])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_navigation_between_admin_pages_never_asks_to_sign_in_again(): void
    {
        $staff = $this->staff('super-admin', ['password' => self::PW]);
        $this->signIn($staff);

        foreach (['/admin', '/admin/orders', '/admin/tickets', '/admin/users', '/admin/staff', '/admin/settings', '/admin/profile', '/admin'] as $url) {
            $this->get($url)->assertOk();
            $this->assertAuthenticatedAs($staff, 'staff');
        }

        // Re-visiting the login page while signed in lands back in the admin area, not on the form.
        $this->get('/staff/developerjet/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_activity_renews_the_sliding_staff_session_window(): void
    {
        $staff = $this->staff('admin', ['password' => self::PW]);
        $this->signIn($staff);
        $this->assertNotNull(session(StaffSecurity::ACTIVITY_KEY));

        // Idle for almost the whole lifetime, then navigate: the window slides and access is kept.
        $lifetime = (int) config('staff.session_lifetime_minutes');
        session([StaffSecurity::ACTIVITY_KEY => time() - ($lifetime * 60 - 60)]);
        $this->get('/admin')->assertOk();
        $this->assertGreaterThanOrEqual(time() - 5, (int) session(StaffSecurity::ACTIVITY_KEY));
    }

    public function test_idle_staff_session_expires_after_the_configured_lifetime(): void
    {
        $staff = $this->staff('admin', ['password' => self::PW]);
        $this->signIn($staff);

        config(['staff.session_lifetime_minutes' => 5]);
        session([StaffSecurity::ACTIVITY_KEY => time() - 360]);
        auth()->guard('staff')->forgetUser();
        $this->get('/admin')->assertNotFound();
        $this->assertGuest('staff');

        // Within the (shortened) lifetime the same session is still valid.
        $this->signIn($staff);
        session([StaffSecurity::ACTIVITY_KEY => time() - 240]);
        $this->get('/admin')->assertOk();
    }

    public function test_session_persistence_does_not_weaken_reauth_for_sensitive_actions(): void
    {
        $super = $this->staff('super-admin', ['password' => self::PW]);
        $victim = $this->staff('sales-staff', ['password' => self::PW]);
        $this->signIn($super);

        // Normal navigation stays signed in even when the re-auth window has lapsed…
        session([StaffSecurity::REAUTH_KEY => time() - 60 * ((int) config('staff.reauth_minutes') + 1)]);
        $this->get('/admin')->assertOk();

        // …but a sensitive action still demands a fresh password confirmation.
        $this->post(route('admin.staff.suspend', $victim), ['reason' => 'test'])
            ->assertRedirect(route('admin.confirm-access'));
        $this->assertTrue((bool) $victim->fresh()->is_active);
    }

    public function test_logout_still_ends_the_session_immediately(): void
    {
        $staff = $this->staff('admin', ['password' => self::PW]);
        $this->signIn($staff);
        $this->post('/admin/logout')->assertRedirect(url('/staff/developerjet/login'));
        $this->assertGuest('staff');
        $this->assertNull(session(StaffSecurity::ACTIVITY_KEY));
        $this->get('/admin')->assertNotFound();
    }
}
