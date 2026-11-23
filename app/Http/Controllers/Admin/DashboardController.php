<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationRequest;
use App\Models\Deployment;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Support\LeadStatus;
use App\Support\OrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $days = collect(range(13, 0))->map(fn ($i) => Carbon::today()->subDays($i));
        // Scale fix: aggregate in SQL (GROUP BY day) instead of loading every paid/placed order into PHP.
        // At 5k-20k orders the old get() + filter() loaded the whole 14-day window into memory on every dashboard visit.
        $paidByDay = Order::whereNotNull('paid_at')->where('paid_at', '>=', $days->first())
            ->selectRaw('DATE(paid_at) as day, SUM(total_minor) as revenue, COUNT(*) as orders')
            ->groupByRaw('DATE(paid_at)')->get()->keyBy('day');
        $placedByDay = Order::where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders')
            ->groupByRaw('DATE(created_at)')->get()->keyBy('day');

        $series = $days->map(fn (Carbon $d) => [
            'label' => $d->format('d M'),
            'revenue' => (int) ($paidByDay[$d->toDateString()]->revenue ?? 0),
            'orders' => (int) ($placedByDay[$d->toDateString()]->orders ?? 0),
        ])->all();

        $paidStatuses = [OrderStatus::PAID, OrderStatus::AWAITING_PROVISIONING, OrderStatus::PROVISIONING, OrderStatus::ACTIVE, OrderStatus::REFUND_PENDING];

        $staff = request()->user();
        $sales = null;
        if ($staff->hasPermission('leads.view')) {
            $mine = fn () => AutomationRequest::where(fn ($q) => $q->where('assigned_to', $staff->id)->orWhere('follow_up_owner_id', $staff->id)->orWhere('technical_owner_id', $staff->id));
            $sales = [
                'mine_open' => $mine()->whereIn('status', LeadStatus::OPEN)->count(),
                'new' => $staff->hasPermission('leads.assign') ? AutomationRequest::where('status', 'new')->whereNull('assigned_to')->count() : null,
                'due' => $mine()->whereIn('status', LeadStatus::OPEN)->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay())->orderBy('follow_up_at')->limit(6)->get(),
            ];
        }

        return view('admin.dashboard', [
            'sales' => $sales,
            'kpi' => [
                'revenue' => (int) Order::whereIn('status', $paidStatuses)->sum('total_minor'),
                'orders' => Order::count(),
                'customers' => User::where('role', 'customer')->count(),
                'invoices' => Invoice::where('type', 'invoice')->count(),
                'awaiting' => Order::where('status', OrderStatus::AWAITING_PROVISIONING)->count(),
                'tickets' => Ticket::whereIn('status', ['open', 'customer_reply'])->count(),
            ],
            'series' => $series,
            'maxRevenue' => max(1, collect($series)->max('revenue')),
            'maxOrders' => max(1, collect($series)->max('orders')),
            'recent' => Order::with('user')->latest()->limit(8)->get(),
            'pendingProvisioning' => Order::with('user')->where('status', OrderStatus::AWAITING_PROVISIONING)->latest()->limit(6)->get(),
            'deployments' => Deployment::with('service', 'server')->latest()->limit(5)->get(),
            'tickets' => Ticket::with('user', 'department')->whereIn('status', ['open', 'customer_reply'])->latest('last_activity_at')->limit(6)->get(),
        ]);
    }
}
