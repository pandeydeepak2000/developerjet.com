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
            ['Access', 'Self-hosted, full root access.', 'Self-hosted, full root access.'],
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
            ['Plans', 'Several sizes, monthly or yearly.', 'Several sizes, monthly or yearly.'],
            ['AI provider key', 'Your own.', 'Your own.'],
            ['Access', 'Self-hosted, full root access.', 'Self-hosted, full root access.'],
        ],
        'choose' => ['Pick the assistant you already prefer to use — hosting is similar for both.'],
    ],
    'n8n-vs-ai-agent-hosting' => [
        'title' => 'n8n vs AI agent hosting',
        'left' => 'n8n', 'right' => 'AI agent hosting (Hermes / OpenClaw)',
        'intro' => 'n8n connects apps with automated workflows. AI agents answer and act on requests in conversation. They can be combined.',
        'rows' => [
            ['What it does', 'Runs workflows you design that connect services.', 'Responds to requests using an AI model.'],
            ['Needs an AI key', 'Only if your workflows call an AI service.', 'Yes — your own provider key.'],
            ['Domain', 'Default or verified custom domain.', 'Hermes CLI none; Workspace and OpenClaw default or custom.'],
            ['How it is delivered', 'Self-hosted n8n on your own VPS, root access included.', 'Self-hosted on your own VPS, root access included.'],
            ['Best for', 'Repeatable automation.', 'Interactive assistance.'],
        ],
        'choose' => ['Need automation between systems? n8n.', 'Need an assistant to talk to? An AI agent product.', 'Need both on one server? AI Complete Bundle.'],
    ],
    'claude-code-vs-hermes-cli' => [
        'title' => 'Claude Code Hosting vs Hermes CLI',
        'left' => 'Claude Code Hosting', 'right' => 'Hermes CLI',
        'intro' => 'Both give you a server of your own that you use over SSH. They install different tools.',
        'rows' => [
            ['Installed tool', 'Claude Code, from Anthropic.', 'Hermes CLI.'],
            ['Model access', 'Your own eligible Claude subscription or API credentials. DeveloperJet never resells Claude access.', 'Your own AI provider key of your choice.'],
            ['Domain', 'Not needed.', 'Not needed.'],
            ['Access', 'Self-hosted, full root access.', 'Self-hosted, full root access.'],
            ['Plans', 'Pricing and resources to be confirmed.', 'Several sizes, monthly or yearly.'],
        ],
        'choose' => ['Already use Claude Code? Claude Code Hosting.', 'Prefer Hermes? Hermes CLI.'],
    ],
    'docker-vs-managed-ai' => [
        'title' => 'Docker VPS vs ready-made AI apps',
        'left' => 'Docker VPS Hosting', 'right' => 'AI app hosting (Hermes, n8n, OpenClaw)',
        'intro' => 'One gives you a server to run anything. The other gives you a ready-to-use AI application.',
        'rows' => [
            ['You get', 'A VPS with Docker Engine and Compose.', 'A VPS with a specific AI application installed and secured.'],
            ['Access', 'SSH/root.', 'The app, plus SSH/root.'],
            ['Who looks after the server', 'You.', 'You, after we set it up.'],
            ['Skills needed', 'Linux, SSH, Docker.', 'Little to start; basic server care later.'],
            ['AI provider key', 'Not applicable.', 'Yours, for the AI product.'],
        ],
        'choose' => ['Want an empty server for your own containers? Docker VPS.', 'Want an AI app installed and ready? AI app hosting.'],
    ],
    'managed-vs-unmanaged-vps' => [
        'title' => 'What self-hosted means: our part vs your part',
        'left' => 'DeveloperJet does', 'right' => 'You do',
        'intro' => 'Every DeveloperJet plan is self-hosted: we set up the server and install your software, then hand it over with full root access.',
        'rows' => [
            ['Server', 'Provides a VPS dedicated to you.', 'Use it as you like.'],
            ['Software', 'Installs and checks your product (Hermes, n8n, OpenClaw, Docker…).', 'Configure, update and extend it.'],
            ['Root / SSH access', 'Hands it over to you.', 'Have full control.'],
            ['Operating system updates', 'Not after handover.', 'Keep the server updated and secure.'],
            ['Backups', 'Not included; available as a separate plan from sales.', 'Keep your own backups or ask sales for a plan.'],
            ['Your data', 'Not responsible for changes or deletions.', 'Responsible for your data.'],
        ],
        'choose' => ['Want help with backups or upkeep? Ask our sales team for a support or backup plan.'],
    ],
];
