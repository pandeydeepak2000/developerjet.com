<?php

/*
 | Site navigation (header mega menu, mobile drawer and footer).
 | Each link: [label, route name, route parameter|null, short description, icon, url fragment|null].
 | Copy rule: no prices, no resource figures, no capability that is not live. Keep the word "hosting"
 | capitalised in labels — the refund page asserts it never shows the lowercase word.
 |
 | Structure (owner brief, Business Automation sprint): three product groups only. There is no standalone
 | "Cloud VPS" group and no "Managed vs Unmanaged" entry (that comparison lives in product documentation and
 | /compare). Every destination appears ONCE: Docker VPS Hosting is the single canonical Docker link.
 | NavigationTest asserts uniqueness, so add new links here rather than duplicating them in the layout.
 */
return [
    'products' => [
        'AI Products' => [
            'icon' => 'spark',
            'links' => [
                ['AI All-Access', 'ai-all-access', null, 'Your AI models in one workspace · coming soon', 'layers', null],
                ['Hermes CLI', 'hermes.cli', null, 'Terminal AI agent on a managed VPS', 'terminal', null],
                ['Hermes Workspace', 'hermes.workspace', null, 'Browser workspace, installed with Docker', 'browser', null],
                ['n8n Automation Hosting', 'n8n', null, 'Workflow automation on a managed VPS', 'workflow', null],
                ['OpenClaw', 'product.show', 'openclaw', 'Isolated personal AI assistant', 'shield', null],
                ['AI Complete Bundle', 'product.show', 'ai-complete-bundle', 'Hermes CLI, Workspace and n8n together', 'layers', null],
            ],
        ],
        'Developer Hosting' => [
            'icon' => 'code',
            'links' => [
                ['Claude Code Hosting', 'product.show', 'claude-code', 'Claude Code on an always-on managed server', 'code', null],
                ['Docker VPS Hosting', 'product.show', 'docker-vps', 'Docker Engine + Compose with SSH/root access', 'container', null],
                ['AI Application Hosting', 'ai-app-hosting', null, 'Custom requirements, scoped with our team', 'cpu', null],
                ['Compare Developer Products', 'compare.index', null, 'Plain-language product comparisons', 'grid', null],
            ],
        ],
        'Digital Services' => [
            'icon' => 'globe',
            'links' => [
                ['WordPress Development', 'services.show', 'wordpress', 'Business sites and stores', 'globe', null],
                ['Laravel Development', 'services.show', 'laravel-development', 'Custom applications and dashboards', 'code', null],
                ['Web Development', 'services.show', 'web-development', 'Landing pages to custom builds', 'browser', null],
                ['SEO Services', 'services.show', 'seo', 'Audits, tasks and monthly reports', 'search', null],
                ['Website Maintenance', 'digital-services', null, 'Ongoing website care, on request', 'wrench', 'maintenance'],
                ['Business Automation', 'business-automation', null, 'Custom workflows for your business', 'workflow', null],
            ],
        ],
    ],
];
