<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Server;
use App\Models\Subscription;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently delete a TEST order and everything that belongs to it (services, subscriptions, invoices, payment
 * attempts, deployments and their logs) so the same customer/VPS can be used for another test. The server record is
 * kept and released back to inventory (nothing on the VPS itself is touched). Refuses orders paid in LIVE mode.
 *
 *   php artisan orders:purge DJ261100001 --dry-run   # show what would be deleted
 *   php artisan orders:purge DJ261100001             # asks you to type the order number to confirm
 */
class PurgeOrderCommand extends Command
{
    protected $signature = 'orders:purge {number : Order number, e.g. DJ261100001} {--dry-run : Only show what would be deleted}';

    protected $description = 'Permanently delete a test order and its services, invoices, subscriptions and deployments (server kept, released)';

    public function handle(): int
    {
        $order = Order::with('services.subscription', 'attempts', 'invoices', 'items')->where('number', $this->argument('number'))->first();
        if (! $order) {
            $this->error('No order with number '.$this->argument('number').'.');

            return self::FAILURE;
        }

        $serviceIds = $order->services->pluck('id');
        $subscriptions = Subscription::whereIn('service_id', $serviceIds)->orWhere('order_id', $order->id)->get();
        $invoices = Invoice::where('order_id', $order->id)->orWhereIn('subscription_id', $subscriptions->pluck('id'))->get();
        $deployments = Deployment::whereIn('service_id', $serviceIds)->count();
        $servers = Server::whereIn('id', $order->services->pluck('server_id')->filter())->orWhere('assigned_order_id', $order->id)->get();
        $live = $order->attempts->contains(fn ($a) => $a->mode === 'live' && in_array($a->status, ['verified', 'captured', 'paid'], true));

        $this->line("Order {$order->number} · {$order->user?->email} · status {$order->status} · total ".Money::inr($order->total_minor));
        $this->table(['What', 'Count / detail'], [
            ['Services', $order->services->map(fn ($s) => $s->label.' ('.$s->status.')')->implode(', ') ?: '0'],
            ['Subscriptions', $subscriptions->count()],
            ['Invoices', $invoices->pluck('number')->implode(', ') ?: '0'],
            ['Payment attempts', $order->attempts->map(fn ($a) => $a->gateway.' '.$a->mode.' '.$a->status)->implode(', ') ?: '0'],
            ['Deployments (+ logs)', $deployments],
            ['Servers released (kept in inventory)', $servers->map(fn ($s) => $s->label.' '.$s->ip_address)->implode(', ') ?: '0'],
            ['Wallet used on this order', Money::inr((int) $order->wallet_applied_minor).' (NOT refunded automatically — adjust the wallet in admin if needed)'],
        ]);
        $this->warn('Nothing on the VPS is changed: installed apps keep running until you remove them there.');

        if ($live) {
            $this->error('This order has a LIVE (real money) payment. Refusing to delete it — refund/cancel it from the admin panel instead.');

            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was deleted.');

            return self::SUCCESS;
        }
        if ($this->ask('Type the order number to permanently delete it') !== $order->number) {
            $this->error('Order number did not match. Nothing was deleted.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($order, $serviceIds, $subscriptions, $invoices, $servers) {
            foreach ($servers as $server) {
                $server->forceFill(['status' => 'available', 'assigned_user_id' => null, 'assigned_order_id' => null, 'deployment_status' => 'none'])->save();
            }
            Deployment::whereIn('service_id', $serviceIds)->delete();          // deployment_logs cascade
            foreach ($invoices as $invoice) {
                Storage::disk('local')->delete('invoices/'.$invoice->number.'.pdf');
                $invoice->delete();                                             // invoice_items cascade
            }
            Subscription::whereIn('id', $subscriptions->pluck('id'))->delete(); // events, reminders, add-ons, actions cascade
            $order->services()->delete();
            Audit::log('order.purged', null, ['number' => $order->number, 'user_id' => $order->user_id]);
            $order->delete();                                                   // items, events, payment attempts cascade
        });

        $this->info("Order {$order->number} was deleted. The server is free to use again.");

        return self::SUCCESS;
    }
}
