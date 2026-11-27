<?php

/*
 | Site navigation (header mega menu, mobile drawer and footer).
 | Each link: [label, route name, route parameter|null, short description, icon, url fragment|null].
 | Copy rule: no prices, no resource figures, no capability that is not live. Keep the word "hosting"
 | capitalised in labels — the refund page asserts it never shows the lowercase word.
 |
 | Structure (owner brief, Business Automation sprint): three product groups only. There is no standalone
 | "Cloud VPS" group and no separate access-model entry (that comparison lives in product documentation and
 | /compare). Every destination appears ONCE: Docker VPS Hosting is the single canonical Docker link.
 | NavigationTest asserts uniqueness, so add new links here rather than duplicating them in the layout.
 */
return [
    'products' => [
        'AI Products' => [
            'icon' => 'spark',
            'links' => [
                ['AI All-Access', 'ai-all-access', null, 'One API key for GPT, Claude, Gemini & more', 'layers', null],
                ['Hermes CLI', 'hermes.cli', null, 'Terminal AI agent on your own VPS', 'terminal', null],
                ['Hermes Workspace', 'hermes.workspace', null, 'Browser workspace, installed with Docker', 'browser', null],
                ['n8n Automation Hosting', 'n8n', null, 'Self-hosted workflow automation', 'workflow', null],
                ['OpenClaw', 'product.show', 'openclaw', 'Isolated personal AI assistant', 'shield', null],
                ['AI Complete Bundle', 'product.show', 'ai-complete-bundle', 'Hermes CLI, Workspace and n8n together', 'layers', null],
            ],
        ],
        'Developer Hosting' => [
            'icon' => 'code',
            'links' => [
                ['Claude Code Hosting', 'product.show', 'claude-code', 'Claude Code on an always-on server', 'code', null],
                ['Docker VPS Hosting', 'product.show', 'docker-vps', 'Docker Engine + Compose with SSH/root access', 'container', null],
                ['Node.js Hosting', 'product.show', 'nodejs-hosting', 'Node.js, PM2 and HTTPS on your own VPS', 'cpu', null],
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
