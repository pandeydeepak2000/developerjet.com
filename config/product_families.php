<?php

/*
 | Product families — the compact cards used on the home page, the all-plans page and product choosers.
 | One card per family: what it is (one sentence), who it is for, three capabilities, and a link to its plans.
 |
 | RULES: no prices or resource figures here. A "From ₹… / month" line is added automatically by
 | App\Support\ProductFamilies only when a plan in `categories` has an owner-APPROVED price.
 | status: available | coming_soon | quote. Coming-soon capabilities are labelled "Planned" on the card.
 | route = [route name, parameter|null]. home = shown on the home page (keep to 6 so it fills two rows of three).
 */
return [
    'hermes' => [
        'name' => 'Hermes AI Agent', 'icon' => 'terminal', 'route' => ['hermes', null], 'status' => 'available', 'home' => true,
        'summary' => 'The Hermes AI agent installed for you on a managed VPS — use it in a terminal or in your browser.',
        'audience' => 'Developers and teams who want an always-on AI agent',
        'capabilities' => ['Terminal (CLI) or browser (Workspace) editions', 'Installed and configured by our team', 'Works with your own AI provider key'],
        'categories' => ['hermes-cli', 'hermes-workspace'],
    ],
    'ai-all-access' => [
        'name' => 'AI All-Access', 'icon' => 'spark', 'route' => ['ai-all-access', null], 'status' => 'coming_soon', 'home' => true,
        'summary' => 'One workspace for the AI models you already have API access to. Bring your own keys.',
        'audience' => 'People and teams who use more than one AI provider',
        'capabilities' => ['Connect providers with your own API keys', 'Choose the provider and model for each chat', 'Organise conversations in one place'],
        'categories' => ['ai-all-access'],
    ],
    'claude-code' => [
        'name' => 'Claude Code Hosting', 'icon' => 'code', 'route' => ['product.show', 'claude-code'], 'status' => 'available', 'home' => true,
        'summary' => 'An always-on managed server prepared for Claude Code, reached securely over SSH.',
        'audience' => 'Developers who run long AI coding sessions',
        'capabilities' => ['Server prepared and secured by our team', 'SSH access from any computer', 'Uses your own Claude subscription or API key'],
        'categories' => ['claude-code'],
    ],
    'docker-vps' => [
        'name' => 'Docker VPS Hosting', 'icon' => 'container', 'route' => ['product.show', 'docker-vps'], 'status' => 'available', 'home' => true,
        'summary' => 'An unmanaged VPS delivered with Docker Engine and Compose, with full root access.',
        'audience' => 'Developers who want full control of their server',
        'capabilities' => ['Docker Engine and Compose installed', 'Full SSH / root access', 'You manage updates and security'],
        'categories' => ['docker-vps'],
    ],
    'n8n' => [
        'name' => 'n8n Automation Hosting', 'icon' => 'workflow', 'route' => ['n8n', null], 'status' => 'available', 'home' => true,
        'summary' => 'n8n workflow automation on a managed VPS, with plans sized by workflow capacity.',
        'audience' => 'Teams connecting apps with automated workflows',
        'capabilities' => ['Browser-based workflow editor', 'Plans by active-workflow capacity', 'Server set up and looked after by our team'],
        'categories' => ['n8n'],
    ],
    'openclaw' => [
        'name' => 'OpenClaw', 'icon' => 'shield', 'route' => ['product.show', 'openclaw'], 'status' => 'available', 'home' => false,
        'summary' => 'A personal AI assistant deployed in isolation for each customer, behind authenticated access.',
        'audience' => 'Individuals who want a private AI assistant',
        'capabilities' => ['One isolated deployment per customer', 'Authenticated access', 'Works with your own AI provider key'],
        'categories' => ['openclaw'],
    ],
    'ai-complete-bundle' => [
        'name' => 'AI Complete Bundle', 'icon' => 'layers', 'route' => ['product.show', 'ai-complete-bundle'], 'status' => 'available', 'home' => false,
        'summary' => 'Hermes CLI, Hermes Workspace and n8n planned together on one managed server.',
        'audience' => 'Teams who want agent, workspace and automation together',
        'capabilities' => ['Terminal and browser agent access', 'Workflow automation alongside', 'One managed server to look after'],
        'categories' => ['ai-complete-bundle'],
    ],
    'digital-services' => [
        'name' => 'Web & SEO Services', 'icon' => 'browser', 'route' => ['digital-services', null], 'status' => 'quote', 'home' => true,
        'summary' => 'WordPress, Laravel and custom web development, plus report-driven SEO.',
        'audience' => 'Businesses that need a website, an application or search visibility',
        'capabilities' => ['WordPress and Laravel builds', 'Scope and price agreed before work starts', 'SEO audits and monthly reports'],
        'categories' => [],
    ],
];
