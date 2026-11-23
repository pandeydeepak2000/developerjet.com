<?php

/*
 | Documentation centre presentation settings. Article content lives in the database (Admin → Documentation);
 | the shipped starter content is in database/seeders/docs/*.md and loaded by DocsSeeder.
 */
return [
    // Category card text and icon on /docs, keyed by doc_categories.slug. Unknown categories fall back to a book icon.
    'categories' => [
        'getting-started' => ['icon' => 'rocket', 'description' => 'Create your account, verify your email, sign in and find your way around the dashboard.'],
        'billing' => ['icon' => 'card', 'description' => 'Choosing a plan, cart and checkout, payment methods, invoices, the wallet and renewals.'],
        'products' => ['icon' => 'layers', 'description' => 'How each DeveloperJet product is delivered, what is included and what you need to bring.'],
        'infrastructure' => ['icon' => 'lock', 'description' => 'Service access, AI provider keys, connecting your own domain, SSH and security.'],
        'help' => ['icon' => 'headset', 'description' => 'Fixes for common errors and how to get help from our team through a support ticket.'],
    ],

    // Old article slugs → current slugs (301). Keeps links in emails, tickets and bookmarks working.
    'redirects' => [
        'email-otp' => 'email-verification',
        'billing-and-wallet' => 'invoices-and-wallet',
        'renewals' => 'billing-and-renewals',
        'ai-api-providers' => 'api-provider-configuration',
        'domains-and-ssl' => 'custom-domain-setup',
        'server-access' => 'service-access',
        'troubleshooting' => 'common-errors',
        'support' => 'support-tickets',
    ],

    // Popular guides shown on the documentation home (slugs; missing or unpublished ones are skipped).
    'popular' => ['getting-started', 'cart-and-checkout', 'billing-and-renewals', 'service-access', 'custom-domain-setup', 'common-errors'],
];
