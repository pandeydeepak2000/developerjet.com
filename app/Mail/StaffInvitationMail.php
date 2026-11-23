<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StaffInvitationMail extends Mailable
{
    public function __construct(public string $name, public string $roleName, public string $url, public int $hours) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been invited to the DeveloperJet staff portal');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.staff-invitation');
    }
}
