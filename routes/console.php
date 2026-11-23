<?php

use App\Services\Payments\PaymentService;
use App\Services\RenewalService;
use App\Services\ServerMonitor;
use App\Services\TicketService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:reconcile', function (PaymentService $payments) {
    $this->info('Settled '.$payments->reconcile().' payment(s).');
})->purpose('Settle captured gateway payments that the browser/webhook path missed');

Artisan::command('billing:run', function (RenewalService $renewals) {
    $s = $renewals->run();
    $this->info("Renewal invoices {$s['invoices']}, reminders queued {$s['reminded']}, newly overdue {$s['overdue']}, suspension pending {$s['suspension_pending']}, cancelled at period end {$s['cancelled']}, expired {$s['expired']}, errors {$s['errors']}.");
})->purpose('Daily billing run: renewal invoices, reminders, grace period and suspension queue (never charges customers)');

// Backwards-compatible name used by earlier docs.
Artisan::command('billing:renewals', function () {
    $this->call('billing:run');
})->purpose('Alias of billing:run');

Artisan::command('support:sla-check', function (TicketService $tickets) {
    $this->info('Tickets newly past an SLA target: '.$tickets->slaCheck().'.');
})->purpose('Flag support tickets that missed a configured SLA target (idempotent; does nothing when no SLA is configured)');

Schedule::command('support:sla-check')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFifteenMinutes()->withoutOverlapping();
// Idempotent: running more often is safe; reminders/invoices are created once per due date.
Schedule::command('billing:run')->dailyAt('08:00')->withoutOverlapping();

Artisan::command('servers:check-renewals {days=7}', function (ServerMonitor $monitor, string $days) {
    $this->info('Flagged '.$monitor->checkRenewals((int) $days).' server renewal(s).');
})->purpose('Alert admins about supplier VPS renewals that are due or overdue');

Schedule::command('servers:check-renewals')->dailyAt('08:30')->withoutOverlapping();
