<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Audit;
use App\Support\StaffSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Unified User Manager: customers + staff (Support / Sales teams) in one place. */
class UserManagerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('customers.view') || $request->user()->hasPermission('staff.view'), 403);
        $type = $request->query('type', 'customers');
        if (! in_array($type, ['customers', 'staff', 'all'], true)) {
            $type = 'customers';
        }
        $perPage = in_array((int) $request->query('per_page', 25), [15, 25, 50, 100], true) ? (int) $request->query('per_page', 25) : 25;
        $query = User::query()->with('staffRole')->withCount('orders');
        if ($type === 'customers') {
            $query->where('role', 'customer');
        } elseif ($type === 'staff') {
            $query->whereIn('role', ['staff', 'admin']);
        }
        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
        }
        if ($request->query('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->query('status') === 'suspended') {
            $query->where('is_active', false);
        }
        if ($roleId = $request->integer('role_id')) {
            $query->where('role_id', $roleId);
        }

        return view('admin.users.index', [
            'users' => $query->latest('id')->paginate($perPage)->withQueryString(),
            'type' => $type, 'perPage' => $perPage,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'counts' => ['customers' => User::where('role', 'customer')->count(), 'staff' => User::whereIn('role', ['staff', 'admin'])->count()],
        ]);
    }

    public function show(Request $request, User $user): View
    {
        abort_unless($request->user()->hasPermission('customers.view') || $request->user()->hasPermission('staff.view'), 403);
        $user->load('staffRole', 'departments')->loadCount(['orders', 'services', 'invoices']);

        return view('admin.users.show', [
            'member' => $user,
            'ticketCount' => Ticket::where('user_id', $user->id)->count(),
            'recentOrders' => Order::where('user_id', $user->id)->latest('id')->limit(10)->get(),
            'recentTickets' => Ticket::where('user_id', $user->id)->latest('last_activity_at')->limit(10)->get(),
            'canImpersonate' => $request->user()->hasPermission('customers.impersonate') && $user->role === User::ROLE_CUSTOMER && $user->is_active,
        ]);
    }

    public function updateEmail(Request $request, User $user): RedirectResponse
    {
        $isStaff = $user->isStaffAccount();
        abort_unless($request->user()->hasPermission($isStaff ? 'staff.edit' : 'customers.edit'), 403);
        if ($user->isSuperAccount() && ! $request->user()->isSuper()) {
            abort(403, 'Only a Super Admin can modify a Super Admin.');
        }
        $data = $request->validate(['email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)]]);
        $old = $user->email;
        DB::transaction(function () use ($user, $data, $old) {
            $user->forceFill(['email' => strtolower($data['email']), 'email_verified_at' => null])->save();
            Audit::log('user.email_changed', $user, ['old' => $old, 'new' => $user->email]);
        });
        if ($isStaff) {
            StaffSecurity::revokeSessions($user);
        }

        return back()->with('success', 'Email updated. The account must verify the new address before signing in.');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('customers.impersonate'), 403);
        abort_unless($user->role === User::ROLE_CUSTOMER && $user->is_active, 404);
        abort_if($user->id === $request->user()->id, 403);
        $request->session()->put('impersonator_id', $request->user()->id);
        $request->session()->put('impersonated_user_id', $user->id);
        $request->session()->put('impersonated_at', now()->toDateTimeString());
        Auth::guard('web')->login($user);
        Audit::log('user.impersonation_started', $user, ['by' => $request->user()->id]);

        return redirect()->route('dashboard')->with('success', 'Viewing as '.$user->name.' - read-only. Use Exit to return to admin.');
    }

    public function exitImpersonation(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        $customerId = $request->session()->get('impersonated_user_id');
        if (! $impersonatorId) {
            return redirect()->route('admin.dashboard');
        }
        $customer = $customerId ? User::find($customerId) : null;
        Auth::guard('web')->logout();
        $request->session()->forget(['impersonator_id', 'impersonated_user_id', 'impersonated_at']);
        if ($customer) {
            Audit::log('user.impersonation_ended', $customer, ['by' => $impersonatorId]);
        }

        return $customer ? redirect()->route('admin.users.show', $customer)->with('success', 'Returned to admin.') : redirect()->route('admin.users.index')->with('success', 'Returned to admin.');
    }
}
