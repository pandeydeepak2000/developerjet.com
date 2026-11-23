<?php

return [
    // Customer-facing VPS controls. `scope` decides which gate applies; nothing is offered unless it is really supported.
    'controls' => [
        'app_status' => ['label' => 'Application status', 'scope' => 'application'],
        'app_restart' => ['label' => 'Application restart', 'scope' => 'application'],
        'app_logs' => ['label' => 'Application logs', 'scope' => 'application'],
        'ssh_keys' => ['label' => 'SSH key management', 'scope' => 'infrastructure'],
        'power' => ['label' => 'Power controls (start / stop / reboot)', 'scope' => 'infrastructure'],
        'web_console' => ['label' => 'Web console', 'scope' => 'infrastructure'],
        'snapshots' => ['label' => 'Snapshots', 'scope' => 'infrastructure'],
        'backups' => ['label' => 'Backups', 'scope' => 'infrastructure'],
        'reinstall' => ['label' => 'Reinstall operating system', 'scope' => 'infrastructure'],
        'firewall' => ['label' => 'Firewall', 'scope' => 'infrastructure'],
        'resource_usage' => ['label' => 'Resource usage', 'scope' => 'infrastructure'],
    ],

    // Application-level controls need an approved remote command catalogue AND the live worker. Off until verified.
    'app_controls_enabled' => env('INFRA_APP_CONTROLS_ENABLED', false),

    // Future Proxmox KVM. Stays disabled until dedicated hardware, networking, storage, API authorisation and
    // security testing are approved. Use a least-privilege API token (never a root@pam password) when enabled.
    'proxmox' => [
        'enabled' => env('PROXMOX_ENABLED', false),
        'verify_tls' => true,
    ],

    // Margin / cost guard-rails. Null = not configured (no figure is invented).
    'min_margin_percent' => null,
    'max_monthly_server_cost_minor' => null,
];
