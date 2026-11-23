<?php

namespace App\Support;

/**
 * Business Automation quotation lifecycle. Transitions are enforced server-side (AutomationLeadService).
 * "converted" is reachable only through the conversion action (never by a plain status change), and nothing in
 * this lifecycle creates an order, invoice or payment: acceptance records agreement only.
 */
class LeadStatus
{
    public const LABELS = [
        'new' => 'New request',
        'reviewing' => 'Reviewing',
        'assigned' => 'Assigned to Sales',
        'requirements_discussion' => 'Requirements discussion',
        'quotation_prepared' => 'Quotation prepared',
        'quotation_sent' => 'Quotation sent',
        'accepted' => 'Customer accepted',
        'declined' => 'Customer declined',
        'converted' => 'Converted to project',
        'closed' => 'Closed',
    ];

    /** Allowed manual transitions (from => [to…]). */
    public const TRANSITIONS = [
        'new' => ['reviewing', 'assigned', 'closed'],
        'reviewing' => ['assigned', 'requirements_discussion', 'closed'],
        'assigned' => ['reviewing', 'requirements_discussion', 'quotation_prepared', 'closed'],
        'requirements_discussion' => ['assigned', 'quotation_prepared', 'closed'],
        'quotation_prepared' => ['requirements_discussion', 'quotation_sent', 'closed'],
        'quotation_sent' => ['requirements_discussion', 'accepted', 'declined', 'closed'],
        'accepted' => ['closed'],
        'declined' => ['requirements_discussion', 'closed'],
        'converted' => ['closed'],
        'closed' => ['reviewing'],
    ];

    /** Statuses the customer is told about by email (meaningful milestones only). */
    public const CUSTOMER_MILESTONES = ['quotation_sent', 'accepted', 'declined', 'converted', 'closed'];

    public const OPEN = ['new', 'reviewing', 'assigned', 'requirements_discussion', 'quotation_prepared', 'quotation_sent', 'accepted'];

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Short, customer-facing description of a status (no internal detail). */
    public static function customerText(string $status): string
    {
        return match ($status) {
            'new' => 'We have received your request.',
            'reviewing', 'assigned' => 'Our team is reviewing your requirements.',
            'requirements_discussion' => 'We are discussing your requirements with you.',
            'quotation_prepared' => 'We are preparing your quotation.',
            'quotation_sent' => 'Your quotation is ready for review.',
            'accepted' => 'You accepted the quotation. Our team will arrange the next steps and any invoice separately.',
            'declined' => 'You declined the quotation.',
            'converted' => 'Your request has become a project. You can follow it in your dashboard.',
            'closed' => 'This request is closed.',
            default => 'Your request has been updated.',
        };
    }
}
