<?php

namespace App\Support;

use App\Models\AutomationRequest;
use App\Models\BillingAction;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TopupRequest;

/** Real counts shown in the admin notifications panel (nothing synthetic). */
class AdminAlerts
{
    public static function summary(): array
    {
        // Cached 60s: this runs on every admin page load, and 7 separate counts on 20k rows add up.
        return cache()->remember('admin.alerts', 60, fn () => [
            'topups' => TopupRequest::where('status', 'pending')->count(),
            'provisioning' => Order::where('status', 'awaiting_provisioning')->count(),
            'tickets' => Ticket::whereIn('status', ['open', 'customer_reply'])->count(),
            'failed_notifications' => NotificationLog::where('status', 'failed')->count(),
            'refunds' => Order::where('status', 'refund_pending')->count(),
            'billing_actions' => BillingAction::where('status', 'open')->count(),
            'new_leads' => AutomationRequest::where('status', 'new')->count(),
        ]);
    }
}
