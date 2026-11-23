<?php

namespace App\Services;

use App\Jobs\SendTelegramAlert;
use App\Mail\TemplatedMail;
use App\Models\NotificationLog;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/** Single entry point for alerts. `$key` identifies the occurrence (e.g. "order:12:paid"); repeats are suppressed. */
class Notifier
{
    public function admin(string $event, string $key, string $text): void
    {
        $dedupe = "telegram:{$event}:{$key}";

        if (! $this->claim($dedupe, $event, 'telegram', $enabled = $this->telegramEnabled($event))) {
            return;
        }
        // Keep the (encrypted) text so a failed delivery can be retried from Admin → Notification log.
        NotificationLog::where('dedupe_key', $dedupe)->update(['payload' => Crypt::encryptString("[DeveloperJet] {$text}")]);

        if ($enabled) {
            SendTelegramAlert::dispatch(NotificationLog::where('dedupe_key', $dedupe)->value('id'), "[DeveloperJet] {$text}");
        }
    }

    public function customer(string $event, string $key, User $user, string $template, array $vars = [], ?string $pdfPath = null, ?string $pdfName = null): void
    {
        if (! $this->claim("mail:{$event}:{$key}", $event, 'mail', true)) {
            return;
        }

        $vars += ['name' => $user->name];
        $mail = Mail::to($user->email);
        $mail->queue(new TemplatedMail($template, $vars, $pdfPath, $pdfName));
        NotificationLog::where('dedupe_key', "mail:{$event}:{$key}")->update(['status' => 'sent', 'attempts' => 1]);
    }

    public function telegramEnabled(string $event): bool
    {
        return Setting::has('telegram.bot_token') && Setting::has('telegram.chat_id') && Setting::bool('notify.telegram.'.$event, true);
    }

    /** @return bool true when this occurrence is new */
    private function claim(string $dedupe, string $event, string $channel, bool $active): bool
    {
        try {
            NotificationLog::create([
                'dedupe_key' => $dedupe, 'event' => $event, 'channel' => $channel,
                'status' => $active ? 'pending' : 'skipped',
                'error' => $active ? null : 'Channel not configured or event disabled',
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
