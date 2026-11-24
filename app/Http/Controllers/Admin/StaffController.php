<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\StaffPasswordController;
use App\Mail\StaffInvitationMail;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\Audit;
use App\Support\Permissions;
use App\Support\StaffPortal;
use App\Support\StaffSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/** Staff accounts and roles. Role/permission configuration is Super Admin only; others can never grant above themselves. */
class StaffController extends Controller
{
    /** Sidebar menu label => the permission that shows it (mirrors layouts/admin.blade.php). */
    public const SIDEBAR_ITEMS = [
        ['Dashboard', 'dashboard.view'], ['User Manager', 'customers.view'], ['Staff & roles', 'staff.view'], ['Departments', 'tickets.view'], ['Orders', 'orders.view'], ['Payments', 'payments.view'], ['Wallet', 'wallet.view'], ['Invoices', 'invoices.view'], ['Billing & renewals', 'subscriptions.view'], ['Billing operations', 'invoices.view'], ['Subscriptions', 'subscriptions.view'], ['Products & plans', 'plans.view'], ['Categories', 'categories.view'], ['Add-ons', 'addons.view'], ['Servers', 'servers.view'], ['Infrastructure', 'servers.view'], ['Providers', 'servers.view'], ['Provider offers', 'servers.view'], ['Server pools', 'servers.view'], ['Deployments', 'deployments.view'], ['Automation leads', 'leads.view'], ['Quote requests', 'projects.view'], ['Projects', 'projects.view'], ['SEO services', 'seo.view'], ['Support tickets', 'tickets.view'], ['Documentation', 'docs.view'], ['Doc categories', 'docs.view'], ['Notifications', 'notifications.view'], ['Settings', 'settings.view'], ['Security logs', 'security_logs.view'],
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('staff.view'), 403);

        return view('admin.staff.index', [
            'staff' => User::where('role', 'staff')->with('staffRole', 'departments')->orderBy('name')->paginate(25)->withQueryString(),
            'roles' => Role::withCount('users')->with('permissions')->orderBy('id')->get(),
            'departments' => Department::orderBy('name')->get(),
            'modules' => config('permissions.modules'),
            'isSuper' => $request->user()->isSuper(),
            'invitations' => StaffInvitation::with('role')->whereNull('accepted_at')->latest('id')->limit(20)->get(),
            'sidebarItems' => self::SIDEBAR_ITEMS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.create'), 403);
        // Authorize the requested role before validating anything else (no information about other fields leaks).
        if ($requested = Role::find($request->integer('role_id'))) {
            $this->guardRole($request->user(), $requested);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', StaffPasswordController::rule()], 'role_id' => ['required', 'exists:roles,id'],
            'departments' => ['nullable', 'array'], 'departments.*' => ['exists:departments,id'],
        ]);
        $role = Role::findOrFail($data['role_id']);
        $this->guardRole($request->user(), $role);

        $user = DB::transaction(function () use ($data) {
            $user = new User(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
            // The password chosen by the manager is temporary: the new staff member must replace it at first sign-in,
            // and must verify the email address (one-time code) before the first staff session starts.
            $user->forceFill(['role' => User::ROLE_STAFF, 'role_id' => $data['role_id'], 'is_active' => true, 'password_change_required' => true])->save();
            $user->departments()->sync($data['departments'] ?? []);

            return $user;
        });
        Audit::log('staff.created', $user, ['role' => $role->slug]);

        return back()->with('success', 'Staff account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.edit') && $user->role === User::ROLE_STAFF, 403);
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'], 'is_active' => ['nullable', 'boolean'],
            'departments' => ['nullable', 'array'], 'departments.*' => ['exists:departments,id'],
        ]);
        $role = Role::findOrFail($data['role_id']);
        $this->guardRole($request->user(), $role);

        if ($user->isSuperAccount() && ! $request->user()->isSuper()) {
            abort(403, 'Only a Super Admin can modify a Super Admin.');
        }
        if ($user->id === $request->user()->id && ! $request->boolean('is_active')) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        // Never leave the system without an active super admin.
        $demoting = $user->isSuperAccount() && (! $role->is_super || ! $request->boolean('is_active'));
        if ($demoting && User::where('role', 'staff')->where('is_active', true)->whereHas('staffRole', fn ($q) => $q->where('is_super', true))->count() <= 1) {
            return back()->with('error', 'At least one active Super Admin is required.');
        }

        $user->forceFill(['role_id' => $role->id, 'is_active' => $request->boolean('is_active')])->save();
        $user->departments()->sync($data['departments'] ?? []);
        Audit::log('staff.updated', $user, ['role' => $role->slug, 'active' => $request->boolean('is_active')]);

        return back()->with('success', 'Staff updated.');
    }

    public function saveRole(Request $request, ?Role $role = null): RedirectResponse
    {
        abort_unless($request->user()->isSuper(), 403, 'Only a Super Admin can configure roles.');
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['string']]);

        if ($role && $role->is_super) {
            return back()->with('error', 'The Super Admin role always has every permission and cannot be edited.');
        }

        $role ??= new Role(['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4))]);
        $role->name = $data['name'];
        $role->save();
        $valid = array_intersect($data['permissions'] ?? [], Permissions::all());

        DB::transaction(function () use ($role, $valid) {
            $role->permissions()->delete();
            foreach ($valid as $perm) {
                $role->permissions()->create(['permission' => $perm]);
            }
        });
        Audit::log('role.saved', $role, ['permissions' => count($valid)]);

        return back()->with('success', 'Role saved.');
    }

    public function destroyRole(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->isSuper(), 403);

        if ($role->is_system || $role->users()->exists()) {
            return back()->with('error', 'System roles and roles with members cannot be deleted.');
        }

        $role->delete();
        Audit::log('role.deleted', null, ['slug' => $role->slug]);

        return back()->with('success', 'Role deleted.');
    }

    /** Staff detail: status, role, departments, MFA, login history and security events. */
    public function show(Request $request, User $user): View
    {
        abort_unless($request->user()->hasPermission('staff.view') && $user->isStaffAccount(), 404);
        $type = $user->getMorphClass();

        return view('admin.staff.show', [
            'member' => $user->load('staffRole', 'departments'),
            'logins' => AuditLog::where('subject_type', $type)->where('subject_id', $user->id)
                ->whereIn('action', StaffProfileController::LOGIN_ACTIONS)->latest('id')->limit(25)->get(),
            'events' => AuditLog::with('user')->where(fn ($q) => $q->where(fn ($q) => $q->where('subject_type', $type)->where('subject_id', $user->id))->orWhere('user_id', $user->id))
                ->where(fn ($q) => $q->where('action', 'like', 'admin.%')->orWhere('action', 'like', 'staff.%')->orWhere('action', 'like', 'staff_portal.%'))
                ->whereNotIn('action', StaffProfileController::LOGIN_ACTIONS)->latest('id')->limit(25)->get(),
            'recoveryLeft' => StaffSecurity::remainingRecoveryCodes($user),
            'canManage' => $this->canManage($request->user(), $user),
            'canEdit' => $request->user()->hasPermission('staff.edit') && $user->role === User::ROLE_STAFF && (! $user->isSuperAccount() || $request->user()->isSuper()),
            'roles' => Role::with('permissions')->orderBy('id')->get(),
            'departments' => Department::orderBy('name')->get(),
            'isSuper' => $request->user()->isSuper(),
            'sidebarItems' => self::SIDEBAR_ITEMS,
        ]);
    }

    /** Invite by email: the invitee chooses their own password through a single-use, expiring link. */
    public function invite(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.create'), 403);
        if ($requested = Role::find($request->integer('role_id'))) {
            $this->guardRole($request->user(), $requested);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'], 'departments' => ['nullable', 'array'], 'departments.*' => ['exists:departments,id'],
        ], ['email.unique' => 'An account with this email already exists. Staff accounts must use their own email address.']);
        $role = Role::findOrFail($data['role_id']);
        $this->guardRole($request->user(), $role);

        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($data, $token, $request) {
            // Only one live invitation per address.
            StaffInvitation::where('email', strtolower($data['email']))->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return StaffInvitation::create([
                'email' => strtolower($data['email']), 'name' => $data['name'], 'role_id' => $data['role_id'],
                'department_ids' => array_map('intval', $data['departments'] ?? []), 'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->id, 'expires_at' => now()->addHours((int) config('staff.invitation_hours')),
            ]);
        });

        $sent = true;
        try {
            Mail::to($invitation->email)->send(new StaffInvitationMail($invitation->name, $role->name, StaffPortal::url('invitation', ['token' => $token]), (int) config('staff.invitation_hours')));
        } catch (Throwable $e) {
            $sent = false;
            Log::error('Staff invitation email failed.', ['invitation' => $invitation->id, 'error' => $e::class]);
        }
        Audit::log('staff.invited', $invitation, ['role' => $role->slug, 'sent' => $sent]);

        return back()->with($sent ? 'success' : 'error', $sent ? 'Invitation sent to '.$invitation->email.'.' : 'The invitation was saved but the email could not be sent. Check mail settings, then revoke and re-invite.');
    }

    public function revokeInvitation(Request $request, StaffInvitation $invitation): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.create'), 403);
        abort_unless($invitation->status() === 'pending', 422);
        $this->guardRole($request->user(), $invitation->role);
        $invitation->forceFill(['revoked_at' => now()])->save();
        Audit::log('staff.invitation_revoked', $invitation);

        return back()->with('success', 'Invitation revoked.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot suspend your own account.');
        }
        if ($user->isSuperAccount() && $user->is_active && $this->activeSuperCount() <= 1) {
            return back()->with('error', 'At least one active Super Admin is required.');
        }

        $user->forceFill(['is_active' => false, 'suspended_at' => now(), 'suspension_reason' => $data['reason']])->save();
        StaffSecurity::revokeSessions($user);
        Audit::log('staff.suspended', $user, ['reason' => $data['reason']]);

        return back()->with('success', $user->name.' is suspended and signed out.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);
        $user->forceFill(['is_active' => true, 'suspended_at' => null, 'suspension_reason' => null])->save();
        Audit::log('staff.reactivated', $user);

        return back()->with('success', $user->name.' is active again.');
    }

    /** Sign the member out everywhere; optionally require a new password (reset link emailed) and/or re-enrolment of MFA. */
    public function resetAccess(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);
        abort_if($user->id === $request->user()->id, 403, 'Use your own profile to change your password.');
        $data = $request->validate(['send_reset_link' => ['nullable', 'boolean'], 'reset_mfa' => ['nullable', 'boolean']]);

        $user->forceFill(['password_change_required' => true])->save();
        if (! empty($data['reset_mfa'])) {
            StaffSecurity::clearTwoFactor($user);
        }
        StaffSecurity::revokeSessions($user);
        $sent = ! empty($data['send_reset_link']) && $user->isStaff() ? StaffPasswordController::sendLink($user) : false;
        Audit::log('staff.access_reset', $user, ['reset_link' => $sent, 'mfa_reset' => ! empty($data['reset_mfa'])]);

        return back()->with('success', 'Access reset for '.$user->name.': signed out, new password required'.($sent ? ', reset link emailed' : '').(! empty($data['reset_mfa']) ? ', two-factor must be enrolled again' : '').'.');
    }

    public function revokeSessions(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);
        StaffSecurity::revokeSessions($user, $user->id === $request->user()->id ? $request : null);
        Audit::log('admin.sessions_revoked', $user, ['scope' => 'all']);

        return back()->with('success', 'All staff sessions for '.$user->name.' were signed out.');
    }

    private function canManage(User $actor, User $target): bool
    {
        return $actor->hasPermission('staff.edit') && $target->isStaffAccount() && (! $target->isSuperAccount() || $actor->isSuper());
    }

    private function authorizeManage(User $actor, User $target): void
    {
        abort_unless($target->isStaffAccount(), 404);
        abort_unless($this->canManage($actor, $target), 403, 'Only a Super Admin can manage a Super Admin account.');
    }

    private function activeSuperCount(): int
    {
        return User::where('role', 'staff')->where('is_active', true)->whereHas('staffRole', fn ($q) => $q->where('is_super', true))->count()
            + User::where('role', User::ROLE_ADMIN)->where('is_active', true)->count();
    }

    private function guardRole(User $actor, Role $role): void
    {
        if ($role->is_super && ! $actor->isSuper()) {
            abort(403, 'Only a Super Admin can assign the Super Admin role.');
        }
        // A non-super staff manager may not grant permissions they do not hold themselves.
        if (! $actor->isSuper()) {
            foreach ($role->permissions()->pluck('permission') as $p) {
                abort_unless($actor->hasPermission($p), 403, 'You cannot assign a role with permissions you do not hold.');
            }
        }
    }
}
