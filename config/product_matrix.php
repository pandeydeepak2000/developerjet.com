<?php

/*
 | Product chooser matrix used on the home page and on every product page.
 | Facts are taken from config/product_pages.php and config/comparisons.php — describe delivery and responsibility
 | only. No prices, resource figures, speed or uptime claims. Route = [route name, parameter|null].
 */
return [
    'rows' => [
        'interface' => 'How you use it',
        'access' => 'Access model',
        'root' => 'Root access',
        'ai_key' => 'AI credentials',
        'domain' => 'Domain',
        'best_for' => 'Best for',
    ],

    'products' => [
        'hermes-cli' => [
            'name' => 'Hermes CLI', 'route' => ['hermes.cli', null], 'icon' => 'terminal',
            'interface' => 'Terminal over SSH or PuTTY', 'access' => 'Managed', 'root' => 'Restricted',
            'ai_key' => 'Your own provider key (optional at order)', 'domain' => 'Not needed',
            'best_for' => 'People who live in the terminal',
        ],
        'hermes-workspace' => [
            'name' => 'Hermes Workspace', 'route' => ['hermes.workspace', null], 'icon' => 'browser',
            'interface' => 'Web browser', 'access' => 'Managed', 'root' => 'Restricted',
            'ai_key' => 'Your own provider key (optional at order)', 'domain' => 'Default address or your verified domain',
            'best_for' => 'A graphical, browser-based workspace',
        ],
        'n8n' => [
            'name' => 'n8n Automation Hosting', 'route' => ['n8n', null], 'icon' => 'workflow',
            'interface' => 'Browser workflow editor', 'access' => 'Managed', 'root' => 'Restricted',
            'ai_key' => 'Only for workflows that call AI services', 'domain' => 'Browser address provided at handover',
            'best_for' => 'Connecting apps with automated workflows',
        ],
        'openclaw' => [
            'name' => 'OpenClaw', 'route' => ['product.show', 'openclaw'], 'icon' => 'shield',
            'interface' => 'Authenticated gateway', 'access' => 'Managed', 'root' => 'Restricted',
            'ai_key' => 'Your own provider key (optional at order)', 'domain' => 'Access details in your dashboard',
            'best_for' => 'An isolated personal AI assistant',
        ],
        'ai-complete-bundle' => [
            'name' => 'AI Complete Bundle', 'route' => ['product.show', 'ai-complete-bundle'], 'icon' => 'layers',
            'interface' => 'Terminal, browser and workflow editor', 'access' => 'Managed', 'root' => 'Restricted',
            'ai_key' => 'Your own provider key (optional at order)', 'domain' => 'Default address or your verified domain',
            'best_for' => 'Agent, workspace and automation on one server',
        ],
        'claude-code' => [
            'name' => 'Claude Code Hosting', 'route' => ['product.show', 'claude-code'], 'icon' => 'code',
            'interface' => 'Terminal over SSH', 'access' => 'Managed', 'root' => 'Restricted',
            'ai_key' => 'Your own Claude subscription or API credentials', 'domain' => 'Not needed',
            'best_for' => 'Always-on AI coding sessions',
        ],
        'docker-vps' => [
            'name' => 'Docker VPS Hosting', 'route' => ['product.show', 'docker-vps'], 'icon' => 'container',
            'interface' => 'SSH with Docker and Compose', 'access' => 'Unmanaged', 'root' => 'Full SSH/root',
            'ai_key' => 'Not required', 'domain' => 'You configure it',
            'best_for' => 'Developers who want full control',
        ],
        'ai-all-access' => [
            'name' => 'AI All-Access', 'route' => ['ai-all-access', null], 'icon' => 'spark', 'status' => 'Coming soon',
            'interface' => 'Web workspace (in development)', 'access' => 'Hosted service — no server to manage', 'root' => 'Not applicable',
            'ai_key' => 'Your own API keys from each provider (BYOK)', 'domain' => 'Not needed',
            'best_for' => 'Using several AI providers from one place',
        ],
    ],
];
