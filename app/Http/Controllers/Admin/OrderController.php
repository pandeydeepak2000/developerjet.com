<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Support\Audit;
use App\Support\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('orders.view'), 403);

        $query = Order::with('user', 'assignee')->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('number', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', '%'.addcslashes($term, '%_\\').'%')));
        }
        if ($request->query('assigned') === 'me') {
            $query->where('assigned_to', $request->user()->id);
        }

        return view('admin.orders.index', ['orders' => $query->paginate(15)->withQueryString()]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($request->user()->hasPermission('orders.view'), 403);
        $order->load('user', 'items', 'events.user', 'attempts', 'invoices', 'services.server', 'services.plan.resourceProfile', 'assignee');

        return view('admin.orders.show', [
            'order' => $order,
            'staff' => User::where('role', 'staff')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'servers' => Server::where('status', 'available')->where('role', 'customer_vps')->orderBy('label')->get(),
        ]);
    }

    public function note(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('orders.edit'), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000'], 'internal' => ['nullable', 'boolean']]);

        $this->orders->event($order, 'note', null, null, $data['message'], $request->user()->id, $request->boolean('internal', true));

        return back()->with('success', 'Note added.');
    }

    public function assign(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('orders.assign'), 403);
        $data = $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);

        $assignee = $data['assigned_to'] ? User::where('role', 'staff')->where('is_active', true)->findOrFail($data['assigned_to']) : null;
        $order->update(['assigned_to' => $assignee?->id]);
        $this->orders->event($order, 'assignment', null, null, $assignee ? "Assigned to {$assignee->name}." : 'Unassigned.', $request->user()->id, true);
        Audit::log('order.assigned', $order, ['to' => $assignee?->id]);

        return back()->with('success', 'Assignment updated.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('orders.edit'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->orders->cancel($order, $request->user(), $data['reason']);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order cancelled.');
    }

    public function refundRequest(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('orders.refund'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->orders->requestRefund($order, $request->user(), $data['reason']);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund requested.');
    }

    public function refundComplete(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('orders.refund') && $request->user()->hasPermission('orders.approve'), 403);
        $data = $request->validate(['destination' => ['required', 'in:wallet,gateway']]);

        try {
            $this->orders->completeRefund($order, $request->user(), $data['destination'] === 'wallet');
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund recorded and credit note issued.');
    }

    public function reverseRefund(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('orders.approve'), 403);

        try {
            $this->orders->transition($order, $order->services()->where('status', 'active')->exists() ? OrderStatus::ACTIVE : OrderStatus::AWAITING_PROVISIONING, $request->user(), 'Refund request withdrawn.');
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund request withdrawn.');
    }
}
