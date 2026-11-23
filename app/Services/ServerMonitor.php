<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Carbon;

/** Records server health observations and raises (de-duplicated) admin alerts. Never invents data. */
class ServerMonitor
{
    public function __construct(private Notifier $notifier) {}

    /** @param  string  $status  ok | warn | fail */
    public function record(Server $server, string $check, string $status, ?string $detail = null): void
    {
        $server->healthChecks()->create(['check' => $check, 'status' => $status, 'detail' => $detail ? mb_substr($detail, 0, 250) : null]);

        if (in_array($status, ['warn', 'fail'], true)) {
            $this->notifier->admin('server_monitoring_alert', "server:{$server->id}:{$check}:".now()->format('Y-m-d-H'),
                "Server {$server->label} ({$server->ip_address}) check \"{$check}\" is {$status}".($detail ? ': '.mb_substr($detail, 0, 120) : '.'));
        }
    }

    /** Alerts for supplier renewals due within $days (or overdue). One alert per server per renewal date. */
    public function checkRenewals(int $days = 7): int
    {
        $count = 0;
        Server::whereNotNull('renewal_date')->where('status', '!=', 'retired')
            ->whereDate('renewal_date', '<=', Carbon::today()->addDays($days))->get()
            ->each(function (Server $server) use (&$count) {
                $overdue = $server->renewal_date->isPast();
                $this->notifier->admin('server_renewal_due', "server:{$server->id}:{$server->renewal_date->toDateString()}",
                    'Supplier renewal '.($overdue ? 'OVERDUE' : 'due')." for server {$server->label} on {$server->renewal_date->format('d M Y')}.");
                $server->update(['renewal_alerted_at' => now()]);
                $count++;
            });

        return $count;
    }
}
