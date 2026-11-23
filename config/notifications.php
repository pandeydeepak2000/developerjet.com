<?php

return [
    // Admin Telegram alert events (each can be toggled in Admin → Notifications).
    'events' => [
        'new_order' => 'New order',
        'payment_success' => 'Payment success',
        'payment_failure' => 'Payment failure',
        'wallet_topup_request' => 'Wallet top-up request',
        'invoice_generated' => 'Invoice generated',
        'deployment_started' => 'Deployment started',
        'deployment_completed' => 'Deployment completed',
        'deployment_failed' => 'Deployment failed',
        'renewal_due' => 'Renewal due',
        'ticket_opened' => 'Support ticket opened',
        'ticket_assigned' => 'Support ticket assigned',
        'ticket_escalated' => 'Support ticket escalated',
        'ticket_sla_breached' => 'Support SLA target missed',
        'quote_requested' => 'Quote requested',
        'automation_request' => 'Business Automation lead / decision',
        'vps_assignment_required' => 'VPS assignment required',
        'server_monitoring_alert' => 'Server monitoring alert',
        'server_renewal_due' => 'Server renewal due',
        'invoice_due' => 'Invoice due / overdue',
        'suspension_pending' => 'Suspension pending (action required)',
        'reactivation_pending' => 'Reactivation pending (action required)',
        'reminder_failed' => 'Renewal reminder failed',
        'test' => 'Test notification',
    ],

    // Default customer e-mail templates; admins can override subject/body per key. Placeholders: {name} {order} {invoice} {ticket} {url} {service} {date} {project}.
    'templates' => [
        'order_paid' => ['Payment received for order {order}', "Hi {name},\n\nWe have verified your payment for order {order}. Your service is now queued for provisioning and we will email you when it is ready.\n\nView your order: {url}"],
        'invoice' => ['Invoice {invoice}', "Hi {name},\n\nYour invoice {invoice} is attached. You can also download it from your dashboard: {url}"],
        'deployment_completed' => ['Your service is ready: {service}', "Hi {name},\n\nYour service {service} has been set up. Sign in to your dashboard to finish secure first-time access: {url}\n\nFor your security we never email passwords."],
        'ticket_reply' => ['Update on ticket {ticket}', "Hi {name},\n\nThere is a new reply on your ticket {ticket}. View it here: {url}"],
        // Staff-facing (sent only to staff addresses; never include customer secrets).
        'staff_ticket_assigned' => ['Ticket {ticket} assigned to you', "Hi {name},\n\nTicket {ticket} has been assigned to you.\n\nOpen it in the staff portal: {url}"],
        'staff_ticket_new' => ['New ticket {ticket} in {service}', "Hello,\n\nA new support ticket {ticket} was opened in the {service} department.\n\nOpen it in the staff portal: {url}"],
        'staff_ticket_escalated' => ['Ticket {ticket} escalated', "Hi {name},\n\nTicket {ticket} was escalated and needs attention.\n\nOpen it in the staff portal: {url}"],
        'renewal_reminder' => ['Renewal invoice {invoice} for {service} — due {date}', 'Hi {name},

Your {cycle} renewal for {service} is due on {date}.

Invoice: {invoice}
Amount: {amount}

Renew now: {url}

We never charge stored payment details automatically. Need help? {support}'],
        'renewal_due_today' => ['Renewal due today: {service}', 'Hi {name},

The renewal for {service} is due today ({date}).

Invoice: {invoice}
Amount: {amount}

Renew now: {url}

Need help? {support}'],
        'renewal_overdue' => ['Renewal overdue for {service}', 'Hi {name},

The renewal for {service} was due on {date} and has not been paid yet.

Invoice: {invoice}
Amount: {amount}

Please renew now to avoid interruption: {url}

Need help? {support}'],
        'suspension_warning' => ['Action needed: {service} may be suspended', 'Hi {name},

The renewal for {service} (invoice {invoice}, {amount}) was due on {date} and the grace period has ended. The service may be suspended by our team. Your data is not deleted.

Renew now: {url}

Need help? {support}'],
        'service_reactivated' => ['{service} is active again', 'Hi {name},

Thanks for renewing. {service} has been reactivated and checked by our team.

View your subscriptions: {url}'],
        'cancellation_update' => ['Cancellation confirmed: {service}', "Hi {name},\n\nYour cancellation request for {service} has been approved. The service ends on {date} and will not renew. Nothing is deleted before then.\n\nDetails: {url}"],
        'cancellation_rejected' => ['Cancellation request for {service}', "Hi {name},\n\nWe could not approve your cancellation request for {service} as submitted. Our team has added a note to your subscription page.\n\nDetails: {url}"],
        'service_expired' => ['{service} has expired', "Hi {name},\n\n{service} has expired because the renewal was not paid. Contact us if you need help or want to restore it.\n\nDetails: {url}"],
        'test_notification' => ['DeveloperJet test notification', 'Hi {name},

This is a test email from the DeveloperJet admin console. If you can read it, outgoing email works.'],
        // Business Automation (guest-safe: addressed by email, no account needed). Never include credentials.
        'automation_ack' => ['Business Automation request received — {reference}', "Hi {name},\n\nThank you for your Business Automation request. This email confirms we have received it.\n\nRequest reference: {reference}\nBusiness type: {business_type}\n\nSummary of your requirements:\n{summary}\n\nContact details you gave us:\nEmail: {email}\nPhone: {phone}\n\nWhat happens next:\n1. Our sales team reviews your requirements.\n2. We contact you to discuss them and ask any questions.\n3. If it is a good fit, we send you a written scope and quotation.\n\nNothing has been charged and nothing is payable unless you accept a quotation. Please never send passwords or API keys by email.\n\nTrack your request: {url}\nQuestions? {support}"],
        'automation_update' => ['Update on your Business Automation request {reference}: {status}', "Hi {name},\n\nThere is an update on your Business Automation request {reference}.\n\nStatus: {status}\n{message}\n\nDetails: {url}\nQuestions? {support}"],
        'automation_info_request' => ['We need a little more information — {reference}', "Hi {name},\n\nTo continue with your Business Automation request {reference}, our team would like to know:\n\n{message}\n\nPlease reply to this email or contact us: {support}\nPlease never send passwords or API keys.\n\nDetails: {url}"],
        // Staff-facing: sent to the Sales notification address (Admin → Settings → Sales).
        'staff_automation_new' => ['New Business Automation lead {reference} — {customer}', "New Business Automation request.\n\nRequest number: {reference}\nCustomer: {customer}\nCompany: {company}\nEmail: {email}\nPhone: {phone}\nBusiness type: {business_type}\n\nRequirements summary:\n{summary}\n\nOpen in the staff dashboard: {url}"],
        'quote_ready' => ['Your quote {project} is ready', "Hi {name},\n\nWe have prepared a quote for your request. Review it here: {url}\n\nNothing is charged until you accept and pay a separate invoice."],
    ],
];
