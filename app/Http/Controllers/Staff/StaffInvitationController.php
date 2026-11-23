<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\Audit;
use App\Support\StaffPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Invitation acceptance: the invitee sets their own password (nobody else ever knows it). Proving receipt of the
 * emailed link verifies the email address. The invitation is consumed atomically and cannot be reused.
 */
class StaffInvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->usable($token);

        return view('staff.invitation', ['invitation' => $invitation, 'token' => $token]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', StaffPasswordController::rule()],
        ]);

        $user = DB::transaction(function () use ($token, $data) {
            $invitation = StaffInvitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($invitation && $invitation->isUsable() && $invitation->role, 404);
            abort_if(User::where('email', $invitation->email)->exists(), 409, 'An account with this email already exists. Ask a Super Admin for help.');

            $user = new User(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
            $user->forceFill([
                'role' => User::ROLE_STAFF, 'role_id' => $invitation->role_id, 'is_active' => true,
                'email_verified_at' => now(), 'password_change_required' => false,
            ])->save();
            $user->departments()->sync($invitation->department_ids ?? []);
            $invitation->forceFill(['accepted_at' => now(), 'user_id' => $user->id])->save();
            Audit::log('staff.invitation_accepted', $user, ['invitation' => $invitation->id, 'role' => $invitation->role->slug], $user->id);

            return $user;
        });

        return redirect()->to(StaffPortal::url('login'))->with('status', 'Your staff account is ready, '.$user->name.'. Sign in to continue.');
    }

    private function usable(string $token): StaffInvitation
    {
        $invitation = StaffInvitation::findByToken($token);
        abort_unless($invitation && $invitation->isUsable(), 404);

        return $invitation;
    }
}
