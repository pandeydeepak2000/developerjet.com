<?php

namespace App\Jobs;

use App\Mail\TemplatedMail;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one templated email to an address that may not belong to a user account (guest leads, the sales mailbox).
 * The notification log row is the deduplication guard: a log already marked "sent" is never sent again.
 * "sent" means the configured mail transport ACCEPTED the message — delivery to the inbox is not verified.
 */
class SendLeadEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $logId, public string $to, public string $template, public array $vars = []) {}

    public function handle(): void
    {
        $log = NotificationLog::find($this->logId);
        if (! $log || $log->status === 'sent') {
            return;
        }

        $log->increment('attempts');
        try {
            Mail::to($this->to)->sendNow(new TemplatedMail($this->template, $this->vars));
        } catch (Throwable $e) {
            // Transport errors can include server details; record only the exception type.
            $log->update(['status' => 'failed', 'error' => 'Mail transport error ('.class_basename($e).')']);
            Log::warning('Lead email failed', ['log_id' => $log->id, 'event' => $log->event, 'error' => class_basename($e)]);
            throw $e;
        }

        $log->update(['status' => 'sent', 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        NotificationLog::whereKey($this->logId)->update(['status' => 'failed']);
    }
}
