<?php

return [
    /*
     | Live provisioning is OFF. The only available worker is the dry-run simulator, which records the
     | planned steps but installs nothing and never marks a service active. The live worker needs verified
     | VPS access, host-key pinning, an approved SSH transport and owner approval (see docs/02_PROJECT_STATUS.md).
     */
    'live_enabled' => env('PROVISIONING_LIVE_ENABLED', false),
    'worker' => env('PROVISIONING_WORKER', 'dry_run'),
    // How a live install runs: 'after_response' (in the web process after the page is sent; no queue worker or cron
    // needed) or 'queue' (a queue:work process must be running).
    'dispatch' => env('PROVISIONING_DISPATCH', 'after_response'),
    'ssh_connect_timeout' => (int) env('PROVISIONING_SSH_CONNECT_TIMEOUT', 15),
    'ssh_command_timeout' => (int) env('PROVISIONING_SSH_COMMAND_TIMEOUT', 1800),

    // Allowed AI providers for the optional customer-supplied key (key itself is encrypted, write-only).
    'ai_providers' => [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'google' => 'Google Gemini',
        'openrouter' => 'OpenRouter',
    ],

    /*
    | Shown to the customer after delivery: how to add their own AI key on the server. Only commands from the official
    | docs (checked 2026-11-25): Hermes Agent (hermes-agent.nousresearch.com), OpenClaw (docs.openclaw.ai),
    | Claude Code (code.claude.com), n8n (keys are added as Credentials in the n8n editor).
    */
    'key_help' => [
        'hermes_cli' => [
            'intro' => 'Connect over SSH, then save your OpenRouter key (or run "hermes model" to pick another provider):',
            'commands' => ['hermes config set OPENROUTER_API_KEY your-key-here', 'hermes model', 'hermes'],
        ],
        'hermes_workspace' => [
            'intro' => 'Connect over SSH, save your key for the Hermes agent, then restart it:',
            'commands' => ['hermes config set OPENROUTER_API_KEY your-key-here', 'hermes model', 'hermes gateway restart'],
        ],
        'openclaw' => [
            'intro' => 'Connect over SSH and run the OpenClaw setup for OpenRouter (it asks for the key):',
            'commands' => ['openclaw onboard --auth-choice openrouter-api-key', 'openclaw models status'],
        ],
        'claude_code' => [
            'intro' => 'Connect over SSH, then sign in with your Claude account or use your Anthropic API key:',
            'commands' => ['claude auth login', 'export ANTHROPIC_API_KEY=your-key-here', 'claude'],
        ],
        'n8n' => [
            'intro' => 'Open your n8n address, create the owner account, then add AI keys under Credentials (for example "OpenAI" or "OpenRouter").',
            'commands' => [],
        ],
        'bundle' => [
            'intro' => 'Connect over SSH and save your key for the Hermes agent; for n8n add keys under Credentials in the n8n editor:',
            'commands' => ['hermes config set OPENROUTER_API_KEY your-key-here', 'hermes model'],
        ],
    ],

    // Engineering safeguards (editable defaults, not product promises).
    'min_free_disk_gb' => (int) env('PROVISIONING_MIN_FREE_DISK_GB', 10),
    'supported_os' => ['ubuntu' => ['22.04', '24.04'], 'debian' => ['12']],
    'supported_arch' => ['x86_64'],

    /*
     | Recipe step catalogue. `command` is a FIXED template: null means "not owner-approved yet" and a live run stops
     | safely at that step. Only read-only preflight probes are defined. `{domain}` is the sole placeholder and is
     | filled only from a DNS-verified, regex-validated domain (never from free text) and shell-escaped.
     */
    'step_catalog' => [
        'check_ssh' => ['label' => 'Verify connectivity and SSH host identity', 'command' => 'echo ok'],
        'check_os' => ['label' => 'Check operating system support', 'command' => 'cat /etc/os-release'],
        'check_arch' => ['label' => 'Check CPU architecture', 'command' => 'uname -m'],
        'check_resources' => ['label' => 'Check available disk', 'command' => 'df -Pk /'],
        'security_baseline' => ['label' => 'Security baseline (keep SSH reachable, report firewall)', 'command' => '@common/security'],
        'install_dependencies' => ['label' => 'Install required dependencies', 'command' => '@common/dependencies'],
        'install_docker' => ['label' => 'Install Docker Engine and Compose', 'command' => '@common/docker'],
        'open_web_ports' => ['label' => 'Open web ports 80/443 in the firewall', 'command' => '@common/web_ports'],
        'install_hermes_cli' => ['label' => 'Install Hermes CLI (official installer, can take 10–20 minutes)', 'command' => '@hermes_cli/install'],
        'install_hermes_runtime_once' => ['label' => 'Install the shared Hermes runtime', 'command' => null],
        'install_hermes_workspace' => ['label' => 'Install Hermes Workspace', 'command' => null],
        'install_n8n' => ['label' => 'Install n8n (Docker) with automatic HTTPS', 'command' => '@n8n/install', 'needs_domain' => true],
        'install_openclaw' => ['label' => 'Install OpenClaw', 'command' => null],
        'install_claude_code' => ['label' => 'Install Claude Code CLI (official installer)', 'command' => '@claude_code/install'],
        'create_isolated_user' => ['label' => 'Create an isolated service user', 'command' => null],
        'configure_persistence' => ['label' => 'Configure persistent storage', 'command' => null],
        'configure_backup' => ['label' => 'Configure backups', 'command' => null],
        'configure_network_isolation' => ['label' => 'Isolate application networks', 'command' => null],
        'configure_auth' => ['label' => 'Configure authentication', 'command' => null],
        'configure_gateway_auth' => ['label' => 'Configure secure gateway authentication', 'command' => null],
        'configure_domain' => ['label' => 'Configure verified domain', 'command' => null, 'needs_domain' => true],
        'configure_https' => ['label' => 'Issue HTTPS certificate', 'command' => null, 'needs_domain' => true],
        'configure_monitoring' => ['label' => 'Configure health monitoring', 'command' => null],
        'health_check' => ['label' => 'Run application health check', 'command' => null],
        'record_state' => ['label' => 'Record final deployment state', 'command' => '@common/record'],
    ],

    /*
     | Versioned installation recipes. `gated` blocks deploy until the owner lifts it. `access_model` is what the
     | customer receives. Steps reference the catalogue above; RecipeRegistry validates this on every load.
     */
    /*
     | One-click server actions over the same SSH channel (admin order page + customer panel). A recipe's `actions`
     | supply the app-specific scripts; an action with no script is simply not offered for that product.
     | `customer` = also offered in the customer's panel.
     */
    'server_actions' => [
        'reboot' => ['label' => 'Reboot server', 'command' => '@common/reboot', 'customer' => true],
        'restart_app' => ['label' => 'Restart app', 'command' => null, 'customer' => true],
        'reset_login' => ['label' => 'Reset app login (forgot password)', 'command' => null, 'customer' => true],
        'stop_app' => ['label' => 'Stop app', 'command' => null, 'customer' => false],
        'start_app' => ['label' => 'Start app', 'command' => null, 'customer' => false],
        'wipe_app' => ['label' => 'Delete app and all its data', 'command' => null, 'customer' => false],
    ],

    'recipes' => [
        // v2.0.0 tested on Ubuntu 24.04 (2026-11-25): Hermes Agent v0.23.4 via the official non-interactive installer.
        'hermes_cli' => ['version' => '2.0.0', 'label' => 'Hermes CLI', 'needs_domain' => false, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_hermes_cli', 'health_check', 'record_state'],
            'commands' => ['health_check' => '@hermes_cli/health'],
            'actions' => ['wipe_app' => '@hermes_cli/wipe']],
        'hermes_workspace' => ['version' => '1.1.0', 'label' => 'Hermes Workspace', 'needs_domain' => true, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker', 'install_hermes_workspace', 'configure_auth', 'configure_domain', 'configure_https', 'configure_monitoring', 'health_check', 'record_state']],
        // v2.0.0 tested end-to-end on Ubuntu 24.04 (2026-11-25): Docker + n8n + Caddy (Let's Encrypt), data in a Docker volume.
        'n8n' => ['version' => '2.0.0', 'label' => 'n8n', 'needs_domain' => true, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker', 'open_web_ports', 'install_n8n', 'health_check', 'record_state'],
            'commands' => ['health_check' => '@n8n/health'],
            'actions' => ['restart_app' => '@n8n/restart', 'reset_login' => '@n8n/reset_login', 'stop_app' => '@n8n/stop', 'start_app' => '@n8n/start', 'wipe_app' => '@n8n/wipe']],
        'bundle' => ['version' => '1.1.0', 'label' => 'AI Complete Bundle', 'needs_domain' => true, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker', 'install_hermes_runtime_once', 'install_hermes_workspace', 'install_n8n', 'configure_network_isolation', 'configure_auth', 'configure_domain', 'configure_https', 'configure_monitoring', 'health_check', 'record_state']],
        'openclaw' => ['version' => '0.2.0', 'label' => 'OpenClaw', 'needs_domain' => true, 'gated' => 'openclaw_spec', 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'create_isolated_user', 'install_openclaw', 'configure_gateway_auth', 'configure_domain', 'configure_https', 'configure_monitoring', 'health_check', 'record_state']],
        // The customer supplies an eligible Claude subscription/API credential. Access is never resold implicitly.
        // v1.0.0 tested on Ubuntu 24.04 (2026-11-25): Claude Code 2.1.332 via Anthropic's official installer.
        'claude_code' => ['version' => '1.0.0', 'label' => 'Claude Code', 'needs_domain' => false, 'access_model' => 'unmanaged', 'customer_credentials' => 'claude',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_claude_code', 'health_check', 'record_state'],
            'commands' => ['health_check' => '@claude_code/health'],
            'actions' => ['wipe_app' => '@claude_code/wipe']],
        // Self-hosted developer VPS: Docker Engine + Compose only. A management UI is out of scope until one is approved.
        'docker_vps' => ['version' => '1.0.0', 'label' => 'Docker Engine and Compose', 'needs_domain' => false, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker', 'health_check', 'record_state'],
            'commands' => ['health_check' => '@docker/health']],
    ],
];
