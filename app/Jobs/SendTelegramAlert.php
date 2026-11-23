<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Support\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Sends one admin alert via the Telegram Bot API (sendMessage). Retries with backoff; the log row prevents duplicates. */
class SendTelegramAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $logId, public string $text) {}

    public function handle(): void
    {
        $log = NotificationLog::find($this->logId);

        if (! $log || $log->status === 'sent') {
            return; // already delivered: retries never double-send
        }

        $token = Setting::get('telegram.bot_token');
        $chat = Setting::get('telegram.chat_id');

        if (! $token || ! $chat) {
            $log->update(['status' => 'skipped', 'error' => 'Telegram not configured']);

            return;
        }

        $log->increment('attempts');
        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chat,
                'text' => $this->text,
            ]);
        } catch (ConnectionException) {
            // The original exception message contains the request URL (and therefore the bot token): never propagate it.
            $log->update(['status' => 'failed', 'error' => 'Telegram connection error']);
            throw new RuntimeException('Telegram connection failed');
        }

        if (! $response->successful()) {
            // Never persist the URL (it contains the token): record the status code only.
            $log->update(['status' => 'failed', 'error' => 'Telegram HTTP '.$response->status()]);
            throw new RuntimeException('Telegram delivery failed with HTTP '.$response->status());
        }

        $log->update(['status' => 'sent', 'error' => null]);
    }

    public function failed(\Throwable $e): void
    {
        NotificationLog::whereKey($this->logId)->update(['status' => 'failed']);
    }
}
