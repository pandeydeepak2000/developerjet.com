<?php

/*
 | Requirement forms per service kind. Each field: [name, label, type, rules, options?].
 | Types: text, textarea, select, checkbox, date. Files are handled separately (private disk, allow-listed types).
 */
return [
    'wordpress' => [
        'title' => 'WordPress website requirements',
        'fields' => [
            ['business_type', 'Business type', 'text', 'required|string|max:120'],
            ['website_purpose', 'Website purpose', 'textarea', 'required|string|max:2000'],
            ['required_pages', 'Required pages', 'textarea', 'required|string|max:2000'],
            ['design_references', 'Design reference websites (one per line)', 'textarea', 'nullable|string|max:2000'],
            ['domain_hosting_status', 'Domain & hosting status', 'select', 'required|in:have_both,have_domain,have_hosting,need_both', ['have_both' => 'I have a domain and hosting', 'have_domain' => 'I have a domain only', 'have_hosting' => 'I have hosting only', 'need_both' => 'I need both']],
            ['ecommerce', 'E-commerce required?', 'select', 'required|in:no,yes', ['no' => 'No', 'yes' => 'Yes']],
            ['ecommerce_notes', 'E-commerce requirements', 'textarea', 'nullable|string|max:2000'],
            ['extra_functionality', 'Additional functionality', 'textarea', 'nullable|string|max:2000'],
            ['content_ready', 'Is your content ready?', 'select', 'required|in:ready,partial,not_ready', ['ready' => 'Ready', 'partial' => 'Partly ready', 'not_ready' => 'Not ready']],
            ['deadline', 'Project deadline', 'date', 'nullable|date|after:today'],
        ],
    ],
    'laravel' => [
        'title' => 'Laravel development requirements',
        'fields' => [
            ['summary', 'Project summary', 'textarea', 'required|string|max:3000'],
            ['custom_features', 'Custom features', 'textarea', 'required|string|max:3000'],
            ['authentication', 'Authentication requirements', 'textarea', 'nullable|string|max:1500'],
            ['admin_dashboard', 'Admin dashboard requirements', 'textarea', 'nullable|string|max:1500'],
            ['api_integrations', 'API integration needs', 'textarea', 'nullable|string|max:1500'],
            ['database', 'Database requirements', 'textarea', 'nullable|string|max:1500'],
            ['hosting_preference', 'Hosting preference', 'text', 'nullable|string|max:200'],
            ['reference_sites', 'Reference websites', 'textarea', 'nullable|string|max:1500'],
            ['budget', 'Budget range or “request a quote”', 'text', 'nullable|string|max:120'],
            ['deadline', 'Deadline', 'date', 'nullable|date|after:today'],
        ],
    ],
    'web' => [
        'title' => 'Web development requirements',
        'fields' => [
            ['business_type', 'Business type', 'text', 'required|string|max:120'],
            ['scope', 'What do you need built?', 'textarea', 'required|string|max:3000'],
            ['reference_sites', 'Reference websites', 'textarea', 'nullable|string|max:1500'],
            ['domain_hosting_status', 'Domain & hosting status', 'text', 'nullable|string|max:200'],
            ['budget', 'Budget range', 'text', 'nullable|string|max:120'],
            ['deadline', 'Deadline', 'date', 'nullable|date|after:today'],
        ],
    ],
    // AI All-Access early-access request (coming soon). Optional keys used by this form only: label, intro, submit,
    // success, pending_note, files, quotable (false = staff cannot quote; the request records interest only).
    'ai_access' => [
        'title' => 'AI All-Access early-access request',
        'label' => 'Access request',
        'intro' => 'AI All-Access is in development and cannot be bought yet. This request only records your interest — nothing is charged, and we will contact you when access opens.',
        'submit' => 'Request access',
        'success' => 'Access request received. AI All-Access is not available yet; we will contact you when it opens. Nothing has been charged.',
        'pending_note' => 'AI All-Access is in development. We have recorded your request and will contact you by email when access opens. Nothing is charged.',
        'files' => false,
        'quotable' => false,
        'fields' => [
            ['use_case', 'What would you use AI All-Access for?', 'textarea', 'required|string|max:2000'],
            ['team_size', 'Who would use it?', 'select', 'required|in:just_me,2_10,11_50,51_plus', ['just_me' => 'Just me', '2_10' => '2–10 people', '11_50' => '11–50 people', '51_plus' => 'More than 50 people']],
            ['providers', 'Which AI providers do you already have API access with?', 'textarea', 'nullable|string|max:1000'],
            ['must_have', 'Must-have features', 'textarea', 'nullable|string|max:1500'],
            ['company', 'Company or project name', 'text', 'nullable|string|max:120'],
        ],
    ],
    'seo' => [
        'title' => 'SEO onboarding',
        'fields' => [
            ['website_url', 'Website URL', 'text', 'required|url|max:200'],
            ['business_type', 'Business type', 'text', 'required|string|max:120'],
            ['target_locations', 'Target locations / audience', 'textarea', 'nullable|string|max:1000'],
            ['target_keywords', 'Keywords you care about (one per line)', 'textarea', 'nullable|string|max:2000'],
            ['competitors', 'Competitor websites', 'textarea', 'nullable|string|max:1500'],
            ['analytics_access', 'Do you have Google Analytics / Search Console?', 'select', 'required|in:both,analytics,search_console,none', ['both' => 'Both', 'analytics' => 'Analytics only', 'search_console' => 'Search Console only', 'none' => 'Neither']],
            ['notes', 'Anything else we should know', 'textarea', 'nullable|string|max:1500'],
        ],
    ],
];
