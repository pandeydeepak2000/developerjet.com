<?php

/*
 | Permission catalogue: "<module>.<action>". Actions available per module are listed explicitly so the
 | role editor never offers meaningless grants. Super Admin implicitly holds everything.
 */
// 'reply' was added in the completion sprint; Super Admin grants it to existing roles (tickets.edit still allows replying).
$all = ['view', 'create', 'edit', 'delete', 'approve', 'refund', 'deploy', 'export', 'assign', 'reply', 'impersonate'];

return [
    'actions' => $all,

    'modules' => [
        'dashboard' => ['label' => 'Dashboard', 'actions' => ['view']],
        'customers' => ['label' => 'Customers', 'actions' => ['view', 'edit', 'export', 'impersonate']],
        'products' => ['label' => 'Products', 'actions' => ['view']],
        'categories' => ['label' => 'Categories', 'actions' => ['view', 'create', 'edit', 'delete']],
        'plans' => ['label' => 'Plans', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'addons' => ['label' => 'Add-ons', 'actions' => ['view', 'create', 'edit', 'delete']],
        'orders' => ['label' => 'Orders', 'actions' => ['view', 'edit', 'approve', 'refund', 'export', 'assign']],
        'payments' => ['label' => 'Payments', 'actions' => ['view', 'refund', 'export']],
        'wallet' => ['label' => 'Wallet', 'actions' => ['view', 'create', 'approve', 'export']],
        'invoices' => ['label' => 'Invoices', 'actions' => ['view', 'create', 'refund', 'export']],
        'subscriptions' => ['label' => 'Subscriptions', 'actions' => ['view', 'edit', 'export']],
        'servers' => ['label' => 'Servers', 'actions' => ['view', 'create', 'edit', 'delete', 'assign']],
        // Supplier purchase costs & margins (internal finance). Not granted to Technical Support by default.
        'supplier_costs' => ['label' => 'Supplier costs & margins', 'actions' => ['view']],
        'deployments' => ['label' => 'Deployments', 'actions' => ['view', 'deploy', 'export']],
        'projects' => ['label' => 'Projects', 'actions' => ['view', 'create', 'edit', 'approve', 'assign']],
        // Business Automation leads & quotations. Without leads.assign a member sees only leads they own.
        'leads' => ['label' => 'Sales leads & quotations', 'actions' => ['view', 'edit', 'assign', 'export']],
        'seo' => ['label' => 'SEO Services', 'actions' => ['view', 'create', 'edit']],
        'tickets' => ['label' => 'Support Tickets', 'actions' => ['view', 'reply', 'edit', 'assign', 'export']],
        'staff' => ['label' => 'Staff Management', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'docs' => ['label' => 'Documentation', 'actions' => ['view', 'create', 'edit', 'delete']],
        'notifications' => ['label' => 'Notifications', 'actions' => ['view', 'edit']],
        'settings' => ['label' => 'Settings', 'actions' => ['view', 'edit']],
        'security_logs' => ['label' => 'Security Logs', 'actions' => ['view', 'export']],
    ],

    // Default role templates. Seeded once; Super Admin may edit them (custom roles are unrestricted).
    'default_roles' => [
        'super-admin' => ['name' => 'Super Admin', 'super' => true, 'grants' => []],
        'admin' => ['name' => 'Administrator', 'grants' => [
            'dashboard.view', 'customers.view', 'customers.edit', 'customers.impersonate', 'products.view', 'categories.*', 'plans.view', 'plans.create', 'plans.edit',
            'addons.*', 'orders.view', 'orders.edit', 'orders.assign', 'payments.view', 'wallet.view', 'invoices.view', 'subscriptions.*',
            'servers.*', 'deployments.*', 'projects.*', 'seo.*', 'tickets.*', 'docs.*', 'notifications.view', 'leads.*', 'supplier_costs.view',
        ]],
        'billing-staff' => ['name' => 'Billing Staff', 'grants' => [
            'dashboard.view', 'customers.view', 'orders.view', 'payments.view', 'payments.export', 'wallet.view', 'wallet.create', 'wallet.approve',
            'invoices.view', 'invoices.create', 'invoices.export', 'subscriptions.view',
        ]],
        'technical-support' => ['name' => 'Technical Support', 'grants' => [
            'dashboard.view', 'customers.view', 'orders.view', 'servers.view', 'deployments.view', 'tickets.view', 'tickets.reply', 'tickets.edit', 'docs.view', 'leads.view',
        ]],
        'sales-staff' => ['name' => 'Sales Staff', 'grants' => [
            'dashboard.view', 'customers.view', 'orders.view', 'wallet.view', 'projects.view', 'projects.create', 'tickets.view', 'leads.view', 'leads.edit',
        ]],
        'developer' => ['name' => 'Developer', 'grants' => [
            'dashboard.view', 'projects.view', 'projects.edit', 'servers.view', 'deployments.view', 'deployments.deploy', 'tickets.view', 'leads.view',
        ]],
        'seo-manager' => ['name' => 'SEO Manager', 'grants' => [
            'dashboard.view', 'projects.view', 'projects.edit', 'seo.*', 'docs.view', 'tickets.view',
        ]],
    ],
];
