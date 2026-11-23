<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendLeadEmail;
use App\Jobs\SendTelegramAlert;
use App\Models\NotificationLog;
use App\Models\Provider;
use App\Models\Server;
use App\Models\ServerHealthCheck;
use App\Models\Service;
use App\Services\Notifier;
use App\Support\Audit;
use App\Support\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Admin infrastructure overview: purchase queue, renewals, monitoring alerts and the provider capability matrix. */
class InfrastructureController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('servers.view'), 403);

        return view('admin.infrastructure', [
            'queue' => Service::with('plan.resourceProfile', 'user', 'order')->where('status', 'pending_provisioning')->whereNull('server_id')->latest()->limit(20)->get(),
            'providers' => Provider::withCount('servers')->orderBy('name')->get(),
            'controls' => config('infrastructure.controls'),
            'renewals' => Server::with('supplier')->whereNotNull('renewal_date')->where('status', '!=', 'retired')->orderBy('renewal_date')->limit(15)->get(),
            'alerts' => ServerHealthCheck::with('server')->whereIn('status', ['warn', 'fail'])->latest('id')->limit(10)->get(),
            'unallocated' => Server::where('status', 'available')->whereNull('assigned_order_id')->count(),
        ]);
    }

    /** Records a suspension REQUEST. Suppliers are manual, so staff carry it out with the supplier; nothing is suspended automatically. */
    public function requestSuspension(Request $request, Server $server, Notifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('servers.edit') && $request->user()->hasPermission('deployments.deploy'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $server->update(['suspension_requested_at' => now()]);
        Audit::log('server.suspension_requested', $server, ['reason' => $data['reason']], $request->user()->id);
        $notifier->admin('server_monitoring_alert', 'suspend:'.$server->id.':'.now()->timestamp, "Suspension requested for server {$server->label}: ".mb_substr($data['reason'], 0, 120));

        return back()->with('success', 'Suspension requested and logged. Carry it out with the supplier — nothing was suspended automatically.');
    }

    /** Safe test controls: Telegram goes to the configured admin chat; email goes only to the acting staff member. */
    /** Re-queue a failed Telegram alert (same log row, so a later success is never sent twice). */
    public function retryNotification(Request $request, NotificationLog $log): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('notifications.edit'), 403);
        abort_unless($log->status === 'failed' && $log->payload, 422, 'Only failed notifications with a stored message can be retried.');
        $lead = $log->channel === 'mail' ? json_decode((string) $log->payload, true) : null;
        abort_unless($log->channel === 'telegram' || (is_array($lead) && ($lead['type'] ?? null) === 'lead_email'), 422, 'Only failed Telegram alerts and Business Automation emails can be retried.');

        $log->update(['status' => 'pending', 'error' => null]);
        if ($log->channel === 'telegram') {
            SendTelegramAlert::dispatch($log->id, $log->payload);
        } else {
            SendLeadEmail::dispatch($log->id, (string) $lead['to'], (string) $lead['template'], (array) ($lead['vars'] ?? []));
        }
        Audit::log('notification.retry', $log, [], $request->user()->id);

        return back()->with('success', 'Alert queued again. Check this log for the result.');
    }

    public function testNotification(Request $request, Notifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);
        $channel = $request->validate(['channel' => ['required', 'in:telegram,email,sales_email']])['channel'];
        $key = Str::random(12);

        if ($channel === 'telegram') {
            if (! $notifier->telegramEnabled('test')) {
                return back()->with('error', 'Telegram is not configured (bot token and chat ID are required).');
            }
            $notifier->admin('test', $key, 'Test notification from the admin console. Delivery is queued — run the queue worker.');
        } elseif ($channel === 'sales_email') {
            $to = Setting::get('sales.notify_email');
            if (! $to) {
                return back()->with('error', 'Set the Sales notification email first (Settings → Sales & lead notifications).');
            }
            $log = NotificationLog::create(['dedupe_key' => 'mail:test_sales:'.$key, 'event' => 'test_sales', 'channel' => 'mail', 'status' => 'pending']);
            SendLeadEmail::dispatch($log->id, $to, 'test_notification', ['name' => 'Sales team']);
        } else {
            $notifier->customer('test_email', $key, $request->user(), 'test_notification');
        }

        Audit::log('notification.test', null, ['channel' => $channel], $request->user()->id);

        return back()->with('success', 'Test '.$channel.' notification queued. Check the Notifications log for the delivery result.');
    }
}
