<?php

return [
    /*
     | Live provisioning is OFF. The only available worker is the dry-run simulator, which records the
     | planned steps but installs nothing and never marks a service active. The live worker needs verified
     | VPS access, host-key pinning, an approved SSH transport and owner approval (see docs/02_PROJECT_STATUS.md).
     */
    'live_enabled' => env('PROVISIONING_LIVE_ENABLED', false),
    'worker' => env('PROVISIONING_WORKER', 'dry_run'),

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
        'security_baseline' => ['label' => 'Apply approved security baseline (firewall, SSH hardening, updates)', 'command' => null],
        'install_dependencies' => ['label' => 'Install required dependencies', 'command' => null],
        'install_docker' => ['label' => 'Install Docker Engine and Compose', 'command' => null],
        'install_hermes_cli' => ['label' => 'Install Hermes CLI', 'command' => null],
        'install_hermes_runtime_once' => ['label' => 'Install the shared Hermes runtime', 'command' => null],
        'install_hermes_workspace' => ['label' => 'Install Hermes Workspace', 'command' => null],
        'install_n8n' => ['label' => 'Install n8n', 'command' => null],
        'install_openclaw' => ['label' => 'Install OpenClaw', 'command' => null],
        'install_claude_code' => ['label' => 'Install Claude Code CLI', 'command' => null],
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
        'record_state' => ['label' => 'Record final deployment state', 'command' => null],
    ],

    /*
     | Versioned installation recipes. `gated` blocks deploy until the owner lifts it. `access_model` is what the
     | customer receives. Steps reference the catalogue above; RecipeRegistry validates this on every load.
     */
    'recipes' => [
        'hermes_cli' => ['version' => '1.1.0', 'label' => 'Hermes CLI', 'needs_domain' => false, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_hermes_cli', 'configure_auth', 'configure_monitoring', 'health_check', 'record_state']],
        'hermes_workspace' => ['version' => '1.1.0', 'label' => 'Hermes Workspace', 'needs_domain' => true, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker', 'install_hermes_workspace', 'configure_auth', 'configure_domain', 'configure_https', 'configure_monitoring', 'health_check', 'record_state']],
        'n8n' => ['version' => '1.1.0', 'label' => 'n8n', 'needs_domain' => true, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker', 'install_n8n', 'configure_persistence', 'configure_backup', 'configure_auth', 'configure_domain', 'configure_https', 'configure_monitoring', 'health_check', 'record_state']],
        'bundle' => ['version' => '1.1.0', 'label' => 'AI Complete Bundle', 'needs_domain' => true, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker', 'install_hermes_runtime_once', 'install_hermes_workspace', 'install_n8n', 'configure_network_isolation', 'configure_auth', 'configure_domain', 'configure_https', 'configure_monitoring', 'health_check', 'record_state']],
        'openclaw' => ['version' => '0.2.0', 'label' => 'OpenClaw', 'needs_domain' => true, 'gated' => 'openclaw_spec', 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'create_isolated_user', 'install_openclaw', 'configure_gateway_auth', 'configure_domain', 'configure_https', 'configure_monitoring', 'health_check', 'record_state']],
        // The customer supplies an eligible Claude subscription/API credential. Access is never resold implicitly.
        'claude_code' => ['version' => '0.1.0', 'label' => 'Claude Code', 'needs_domain' => false, 'access_model' => 'unmanaged', 'customer_credentials' => 'claude',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'create_isolated_user', 'install_claude_code', 'configure_monitoring', 'health_check', 'record_state']],
        // Self-hosted developer VPS: Docker Engine + Compose only. A management UI is out of scope until one is approved.
        'docker_vps' => ['version' => '0.1.0', 'label' => 'Docker Engine and Compose', 'needs_domain' => false, 'access_model' => 'unmanaged',
            'steps' => ['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker', 'configure_monitoring', 'health_check', 'record_state']],
    ],
];
