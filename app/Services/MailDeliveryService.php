<?php

namespace App\Services;

use App\Support\Setting;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * Reports whether transactional email can actually reach an inbox, and tests the configured
 * SMTP server without sending a message. Credentials come only from the encrypted admin
 * settings (or the environment mailer); they are never hardcoded, logged or returned.
 * The intended sender (e.g. support@developerjet.com) stays whatever a Super Admin saves
 * as smtp.from_address in Email Settings.
 */
class MailDeliveryService
{
    /** @return list<string> setting keys that must be saved before SMTP delivery can work */
    public function missingSmtpSettings(): array
    {
        // config('mail.mailers.smtp.host') defaults to 127.0.0.1 when MAIL_HOST is unset, and
        // the stock .env sets MAIL_HOST=127.0.0.1 as a placeholder, so neither counts as a
        // configured SMTP host. Only an explicitly saved setting or a non-localhost env host does.
        $missing = [];
        $envHost = env('MAIL_HOST');
        $envHostIsReal = is_string($envHost) && $envHost !== '' && ! in_array(strtolower($envHost), ['127.0.0.1', 'localhost', '::1'], true);
        if (! Setting::has('smtp.host') && ! $envHostIsReal) {
            $missing[] = 'smtp.host';
        }
        if (! Setting::has('smtp.username') && ! env('MAIL_USERNAME')) {
            $missing[] = 'smtp.username';
        }
        if (! Setting::has('smtp.password') && ! env('MAIL_PASSWORD')) {
            $missing[] = 'smtp.password';
        }
        $from = Setting::get('smtp.from_address') ?: env('MAIL_FROM_ADDRESS');
        if (! $from || $from === 'hello@example.com') {
            $missing[] = 'smtp.from_address';
        }

        return $missing;
    }

    public function effectiveMailer(): string
    {
        return Setting::has('smtp.host') ? 'smtp' : (string) config('mail.default');
    }

    public function deliversToInbox(): bool
    {
        return $this->effectiveMailer() === 'smtp' && $this->missingSmtpSettings() === [];
    }

    /** Opens and authenticates an SMTP session, then closes it. Sends no email. */
    public function testConnection(): array
    {
        $missing = $this->missingSmtpSettings();
        if ($missing !== []) {
            return ['ok' => false, 'missing' => $missing, 'message' => 'SMTP is not configured. Missing: '.implode(', ', $missing).'. Save them in Settings - Email (SMTP); no connection was attempted and no email was sent.'];
        }
        $host = (string) (Setting::get('smtp.host') ?: config('mail.mailers.smtp.host'));
        $port = (int) (Setting::get('smtp.port', 587) ?: config('mail.mailers.smtp.port', 587));
        $username = (string) (Setting::get('smtp.username') ?: config('mail.mailers.smtp.username'));
        $password = (string) (Setting::get('smtp.password') ?: config('mail.mailers.smtp.password'));
        $scheme = Setting::get('smtp.encryption', 'tls') === 'ssl' ? 'smtps' : 'smtp';
        try {
            $transport = Transport::fromDsn($scheme.'://'.rawurlencode($username).':'.rawurlencode($password).'@'.$host.':'.$port);
            if (! $transport instanceof SmtpTransport) {
                return ['ok' => false, 'missing' => [], 'message' => 'The configured transport is not SMTP; no email was sent.'];
            }
            $transport->start();
            $transport->stop();

            return ['ok' => true, 'missing' => [], 'message' => 'SMTP connection and authentication succeeded for '.$host.':'.$port.'. No email was sent by this test.'];
        } catch (Throwable $e) {
            $error = str_replace([$username, $password], '[redacted]', $e->getMessage());

            return ['ok' => false, 'missing' => [], 'message' => 'SMTP connection failed ('.$e::class.'): '.mb_substr($error, 0, 200).' No email was sent.'];
        }
    }
}
