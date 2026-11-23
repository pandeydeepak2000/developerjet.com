<?php

namespace App\Jobs;

use App\Mail\TemplatedMail;
use App\Models\SubscriptionReminder;
use App\Services\Notifier;
use App\Support\BillingCycle;
use App\Support\Money;
use App\Support\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one renewal reminder e-mail. The reminder row is the de-duplication record and the failure log: a reminder
 * already "sent" is never sent again (safe for retries); failures are recorded and staff alerted, and staff can
 * retry from Admin → Billing → Reminders.
 */
class SendRenewalReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public const TEMPLATES = [
        'before' => 'renewal_reminder', 'due' => 'renewal_due_today', 'overdue' => 'renewal_overdue', 'suspension_warning' => 'suspension_warning',
    ];

    public function __construct(public int $reminderId) {}

    public function handle(): void
    {
        $reminder = SubscriptionReminder::with('subscription.user', 'subscription.service', 'subscription.plan', 'invoice')->find($this->reminderId);
        if (! $reminder || $reminder->status === 'sent' || $reminder->status === 'skipped') {
            return;
        }

        $sub = $reminder->subscription;
        $invoice = $reminder->invoice;
        if (! $sub || ! $sub->user) {
            $reminder->update(['status' => 'skipped', 'error' => 'Subscription or customer no longer exists.']);

            return;
        }
        if ($invoice && $invoice->status !== 'unpaid') {
            $reminder->update(['status' => 'skipped', 'error' => 'Invoice already paid.']);

            return;
        }

        $reminder->increment('attempts');
        $template = self::TEMPLATES[explode(':', $reminder->kind)[0]] ?? 'renewal_reminder';
        $support = trim(implode(' · ', array_filter([Setting::get('company.email'), Setting::get('sales.phone'), route('dashboard.tickets.create')])));

        Mail::to($sub->user->email)->sendNow(new TemplatedMail($template, [
            'name' => $sub->user->name,
            'service' => $sub->service?->label ?? $sub->plan?->name ?? 'your service',
            'invoice' => $invoice?->number ?? '—',
            'amount' => $invoice ? Money::inr($invoice->total_minor) : Money::inr($sub->price_minor),
            'date' => $reminder->due_date->format('d M Y'),
            'cycle' => BillingCycle::label($sub->billing_interval),
            'url' => $invoice?->order_id ? route('dashboard.orders.show', $invoice->order_id) : route('dashboard.subscriptions'),
            'support' => $support,
        ]));

        $reminder->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        $reminder = SubscriptionReminder::find($this->reminderId);
        if (! $reminder) {
            return;
        }
        // Store the exception class and a short message only (mail errors can echo server details).
        $reminder->update(['status' => 'failed', 'error' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 280)]);
        app(Notifier::class)->admin('reminder_failed', "reminder:{$reminder->id}:{$reminder->attempts}", "Renewal reminder #{$reminder->id} ({$reminder->kind}) failed to send. Retry from Admin → Billing → Reminders.");
    }
}
