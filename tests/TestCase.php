<?php

namespace Tests;

use App\Models\User;
use App\Support\StaffSecurity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The admin area uses a separate `staff` guard. For convenience `actingAs($staffUser)` (no guard given)
     * signs the account in on both guards with a current staff session epoch, exactly as a completed staff-portal
     * sign-in would. Isolation tests pass an explicit guard (`actingAs($user, 'web')`) to opt out.
     */
    public function be(Authenticatable $user, $guard = null)
    {
        if ($guard === null && $user instanceof User && $user->isStaffAccount()) {
            $this->app['auth']->guard('staff')->setUser($user);
            $this->withSession([StaffSecurity::EPOCH_KEY => (int) $user->staff_session_epoch]);
        } elseif ($guard !== 'staff' && $this->app['auth']->guard('staff')->hasUser()) {
            // Switching to a customer: a real browser would not carry someone else's staff session.
            $this->app['auth']->guard('staff')->logout();
        }

        return parent::be($user, $guard);
    }
}
