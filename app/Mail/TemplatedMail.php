<?php

namespace App\Mail;

use App\Support\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Generic customer e-mail rendered from an admin-editable template. Plain text only; variables are escaped on output. */
class TemplatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public string $template, public array $vars = [], public ?string $pdfPath = null, public ?string $pdfName = null) {}

    public static function renderTemplate(string $template, array $vars): array
    {
        $default = config('notifications.templates.'.$template, ['Notification', '{url}']);
        $subject = Setting::get('mail.template.'.$template.'.subject', $default[0]);
        $body = Setting::get('mail.template.'.$template.'.body', $default[1]);
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{'.$k.'}'] = (string) $v;
        }

        return [strtr($subject, $map), strtr($body, $map)];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::renderTemplate($this->template, $this->vars)[0]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.templated', with: ['bodyText' => self::renderTemplate($this->template, $this->vars)[1]]);
    }

    public function attachments(): array
    {
        return $this->pdfPath && is_file($this->pdfPath)
            ? [Attachment::fromPath($this->pdfPath)->as($this->pdfName ?? 'invoice.pdf')->withMime('application/pdf')]
            : [];
    }
}
