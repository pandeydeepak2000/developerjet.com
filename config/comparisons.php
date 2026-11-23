<?php

/*
 | Product comparisons. Statements describe how DeveloperJet delivers each product; they make no uptime, speed or
 | review claims. Where a fact is not yet confirmed it says so.
 */
return [
    'hermes-cli-vs-hermes-workspace' => [
        'title' => 'Hermes CLI vs Hermes Workspace',
        'left' => 'Hermes CLI', 'right' => 'Hermes Workspace',
        'intro' => 'Both run Hermes on a server dedicated to you. The difference is how you use it.',
        'rows' => [
            ['How you use it', 'In a terminal over SSH or PuTTY.', 'In a web browser.'],
            ['Domain', 'Not needed — no subdomain selection.', 'Default DeveloperJet address, or your own verified domain.'],
            ['HTTPS', 'Not applicable.', 'Configured during setup where a domain is available.'],
            ['Installed with', 'Directly on the VPS.', 'Docker.'],
            ['Best for', 'People comfortable in a terminal.', 'People who prefer a graphical workspace.'],
            ['AI provider key', 'Your own (optional at order time).', 'Your own (optional at order time).'],
            ['Access model', 'Managed.', 'Managed.'],
        ],
        'choose' => ['Choose Hermes CLI if you live in the terminal.', 'Choose Hermes Workspace if you want to work from a browser.', 'Want both plus n8n? See the AI Complete Bundle.'],
    ],
    'hermes-vs-openclaw' => [
        'title' => 'Hermes vs OpenClaw',
        'left' => 'Hermes (CLI / Workspace)', 'right' => 'OpenClaw',
        'intro' => 'They are different assistants from different projects. DeveloperJet hosts each on its own isolated server.',
        'rows' => [
            ['What it is', 'The Hermes assistant, as CLI or browser workspace.', 'The OpenClaw assistant behind a gateway.'],
            ['Isolation', 'Dedicated server per customer.', 'Dedicated server per customer, isolated service user and gateway authentication.'],
            ['Availability to order', 'Plans exist; prices still need owner approval.', 'Disabled until the owner approves the specification.'],
            ['AI provider key', 'Your own.', 'Your own.'],
            ['Access model', 'Managed.', 'Managed.'],
        ],
        'choose' => ['Pick the assistant you already prefer to use — hosting is similar for both.', 'OpenClaw cannot be ordered until its specification is approved.'],
    ],
    'n8n-vs-ai-agent-hosting' => [
        'title' => 'n8n vs AI agent hosting',
        'left' => 'n8n', 'right' => 'AI agent hosting (Hermes / OpenClaw)',
        'intro' => 'n8n connects apps with automated workflows. AI agents answer and act on requests in conversation. They can be combined.',
        'rows' => [
            ['What it does', 'Runs workflows you design that connect services.', 'Responds to requests using an AI model.'],
            ['Needs an AI key', 'Only if your workflows call an AI service.', 'Yes — your own provider key.'],
            ['Domain', 'Default or verified custom domain.', 'Hermes CLI none; Workspace and OpenClaw default or custom.'],
            ['Commercial hosting', 'Gated until n8n licensing is verified.', 'No such gate for Hermes; OpenClaw gated until specification approved.'],
            ['Best for', 'Repeatable automation.', 'Interactive assistance.'],
        ],
        'choose' => ['Need automation between systems? n8n.', 'Need an assistant to talk to? An AI agent product.', 'Need both on one server? AI Complete Bundle (not orderable yet).'],
    ],
    'claude-code-vs-hermes-cli' => [
        'title' => 'Claude Code Hosting vs Hermes CLI',
        'left' => 'Claude Code Hosting', 'right' => 'Hermes CLI',
        'intro' => 'Both give you a managed server you use over SSH. They install different tools.',
        'rows' => [
            ['Installed tool', 'Claude Code, from Anthropic.', 'Hermes CLI.'],
            ['Model access', 'Your own eligible Claude subscription or API credentials. DeveloperJet never resells Claude access.', 'Your own AI provider key of your choice.'],
            ['Domain', 'Not needed.', 'Not needed.'],
            ['Access model', 'Managed.', 'Managed.'],
            ['Plans', 'Three draft slots — pricing and resources to be confirmed.', 'Three plans — prices awaiting owner approval.'],
        ],
        'choose' => ['Already use Claude Code? Claude Code Hosting.', 'Prefer Hermes? Hermes CLI.'],
    ],
    'docker-vs-managed-ai' => [
        'title' => 'Docker hosting vs managed AI hosting',
        'left' => 'Docker VPS Hosting', 'right' => 'Managed AI hosting',
        'intro' => 'One gives you a server to run anything. The other gives you a ready-to-use AI application.',
        'rows' => [
            ['You get', 'A VPS with Docker Engine and Compose.', 'A VPS with a specific AI application installed and secured.'],
            ['Access', 'SSH/root.', 'Application access; root restricted by service policy.'],
            ['Who maintains the server', 'You.', 'DeveloperJet.'],
            ['Skills needed', 'Linux, SSH, Docker.', 'Little or none.'],
            ['AI provider key', 'Not applicable.', 'Yours, for the AI product.'],
        ],
        'choose' => ['Want control and your own containers? Docker VPS.', 'Want it to just work? Managed AI hosting.'],
    ],
    'managed-vs-unmanaged-vps' => [
        'title' => 'Managed hosting vs unmanaged VPS',
        'left' => 'Managed hosting', 'right' => 'Unmanaged VPS',
        'intro' => 'You choose between us running the server for you and you running it yourself. Checkout shows which one you are buying.',
        'rows' => [
            ['Server setup and security', 'DeveloperJet.', 'DeveloperJet installs Docker; afterwards it is yours.'],
            ['Root / SSH access', 'Restricted by service policy.', 'Provided to you.'],
            ['Operating system updates', 'DeveloperJet.', 'You.'],
            ['Control panel features', 'Only those verified and shown as available.', 'Only those verified and shown as available.'],
            ['Typical products', 'Hermes, n8n, OpenClaw, Claude Code, AI Complete Bundle.', 'Docker VPS Hosting.'],
            ['Support', 'Service, access and application setup (draft scope).', 'Infrastructure and initial install (draft scope).'],
        ],
        'choose' => ['No sysadmin time? Managed.', 'Need full control? Unmanaged.'],
    ],
];
