<?php

use App\Models\Addon;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Deployment;
use App\Models\DocArticle;
use App\Models\DocCategory;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\PaymentAttempt;
use App\Models\ProductCategory;
use App\Models\Provider;
use App\Models\ProviderOffer;
use App\Models\Server;
use App\Models\ServerPool;
use App\Models\ServiceRequest;
use App\Models\Subscription;
use App\Models\User;

/*
 | Declarative admin resources rendered by Admin\ResourceController (list + search + filter + pagination + forms).
 | Every action is authorised against "<module>.<action>" on the backend. Secret fields are write-only.
 */
return [
    'customers' => [
        'model' => User::class, 'module' => 'customers', 'title' => 'Customers', 'scope' => ['role', 'customer'],
        'search' => ['name', 'email', 'phone'], 'actions' => ['edit'], 'with' => [],
        'columns' => [['name', 'Name'], ['email', 'Email'], ['phone', 'Phone'], ['email_verified_at', 'Verified', 'bool'], ['is_active', 'Active', 'bool'], ['created_at', 'Joined', 'date']],
        'fields' => [['name', 'Name', 'text', 'required|string|max:120'], ['is_active', 'Account active', 'checkbox', 'boolean']],
        'filters' => [['is_active', 'Active', ['1' => 'Active', '0' => 'Disabled']]],
    ],
    'categories' => [
        'model' => ProductCategory::class, 'module' => 'categories', 'title' => 'Categories', 'search' => ['name', 'slug'],
        'actions' => ['create', 'edit', 'delete'],
        'columns' => [['name', 'Name'], ['slug', 'Slug'], ['is_active', 'Active', 'bool'], ['sort_order', 'Order']],
        'fields' => [['name', 'Name', 'text', 'required|string|max:120'], ['slug', 'Slug', 'text', 'required|alpha_dash|max:80|unique'], ['description', 'Description', 'textarea', 'nullable|string|max:1000'], ['sort_order', 'Sort order', 'number', 'nullable|integer|min:0|max:999'], ['is_active', 'Active', 'checkbox', 'boolean']],
    ],
    'addons' => [
        // Add-on prices are customer-facing prices: setting/changing one needs pricing approval (plans.approve).
        'model' => Addon::class, 'module' => 'addons', 'title' => 'Add-ons', 'search' => ['name', 'slug'], 'approval_fields' => ['price_minor' => 'plans.approve'],
        'actions' => ['create', 'edit', 'delete'],
        'columns' => [['name', 'Name'], ['price_minor', 'Price', 'money'], ['billing_type', 'Billing'], ['is_active', 'Active', 'bool']],
        'fields' => [['name', 'Name', 'text', 'required|string|max:120'], ['slug', 'Slug', 'text', 'required|alpha_dash|max:80|unique'], ['description', 'Description', 'textarea', 'nullable|string|max:1000'],
            ['price_minor', 'Price (₹, blank = not yet priced)', 'money', 'nullable|numeric|min:0'], ['billing_type', 'Billing', 'select', 'required|in:recurring,one_time', ['recurring' => 'Recurring', 'one_time' => 'One time']], ['is_active', 'Active', 'checkbox', 'boolean']],
    ],
    'providers' => [
        'model' => Provider::class, 'module' => 'servers', 'title' => 'Providers', 'search' => ['name', 'slug', 'supplier_reference'],
        'actions' => ['create', 'edit', 'delete'],
        'columns' => [['name', 'Provider'], ['kind', 'Kind'], ['availability', 'Availability', 'badge'], ['provisioning_method', 'Method'], ['api_status', 'API', 'badge'], ['is_active', 'Active', 'bool']],
        'filters' => [['api_status', 'API status', ['none' => 'No API', 'unverified' => 'Unverified', 'verified' => 'Verified']]],
        'fields' => [['name', 'Name', 'text', 'required|string|max:120'], ['slug', 'Slug', 'text', 'required|alpha_dash|max:60|unique'],
            ['kind', 'Kind', 'select', 'required|in:vps_supplier,broker,dedicated_provider,proxmox_internal', ['vps_supplier' => 'VPS supplier', 'broker' => 'Broker', 'dedicated_provider' => 'Dedicated server provider', 'proxmox_internal' => 'Own Proxmox KVM (future)']],
            ['availability', 'Availability', 'select', 'required|in:available,limited,unavailable,unknown', ['available' => 'Available', 'limited' => 'Limited', 'unavailable' => 'Unavailable', 'unknown' => 'Unknown']],
            ['regions', 'Regions (comma separated)', 'textarea', 'nullable|string|max:500'],
            ['provisioning_method', 'Provisioning method', 'select', 'required|in:manual,api,proxmox', ['manual' => 'Manual purchase and assignment', 'api' => 'Provider API (needs a built, verified adapter)', 'proxmox' => 'Proxmox KVM (disabled)']],
            ['api_status', 'API status', 'select', 'required|in:none,unverified,verified', ['none' => 'No API', 'unverified' => 'Unverified — do not rely on it', 'verified' => 'Verified against official docs + authorised credentials']],
            ['verified_capabilities', 'Verified controls, one per line (only after proving each one): ssh_keys, power, web_console, snapshots, backups, reinstall, firewall, resource_usage', 'lines', 'nullable|string|max:500'],
            ['supplier_reference', 'Supplier account reference (never a password or API key)', 'text', 'nullable|string|max:190'],
            ['website_url', 'Website', 'text', 'nullable|url|max:190'], ['notes', 'Notes', 'textarea', 'nullable|string|max:2000'], ['is_active', 'Active', 'checkbox', 'boolean']],
    ],
    'provider_offers' => [
        'model' => ProviderOffer::class, 'module' => 'servers', 'title' => 'Provider offers (cost vs selling price)', 'search' => ['label', 'region'], 'with' => ['provider'],
        'restricted' => ['purchase_cost_minor' => 'supplier_costs.view'],
        'actions' => ['create', 'edit', 'delete'],
        'columns' => [['provider.name', 'Provider'], ['label', 'Offer'], ['region', 'Region'], ['vcpu', 'vCPU'], ['ram_gb', 'RAM GB'], ['purchase_cost_minor', 'Our cost', 'money'], ['selling_price_minor', 'Selling ref.', 'money'], ['billing_period', 'Billing'], ['status', 'Status', 'badge']],
        'filters' => [['status', 'Status', ['draft' => 'Draft', 'approved' => 'Approved']]],
        'fields' => [['provider_id', 'Provider', 'select', 'required|exists:providers,id', null, [Provider::class, 'name']], ['label', 'Offer name', 'text', 'required|string|max:150'], ['region', 'Region', 'text', 'nullable|string|max:80'],
            ['vcpu', 'vCPU', 'number', 'nullable|integer|min:0'], ['ram_gb', 'RAM (GB)', 'number', 'nullable|integer|min:0'], ['disk_gb', 'Storage (GB)', 'number', 'nullable|integer|min:0'],
            ['billing_period', 'Billing period', 'select', 'required|in:monthly,quarterly,yearly', ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly']],
            ['purchase_cost_minor', 'Purchase cost ₹ (internal only; blank = unknown)', 'money', 'nullable|numeric|min:0'], ['selling_price_minor', 'Reference selling price ₹ (blank = unknown)', 'money', 'nullable|numeric|min:0'],
            ['status', 'Status', 'select', 'required|in:draft,approved', ['draft' => 'Draft', 'approved' => 'Approved']], ['notes', 'Notes', 'textarea', 'nullable|string|max:1000']],
    ],
    'servers' => [
        'model' => Server::class, 'module' => 'servers', 'title' => 'Servers (inventory)', 'search' => ['label', 'ip_address', 'supplier_reference'], 'with' => ['supplier', 'assignedUser'],
        'restricted' => ['purchase_cost_minor' => 'supplier_costs.view'],
        'actions' => ['create', 'edit', 'delete'],
        'columns' => [['label', 'Server'], ['ip_address', 'IP'], ['supplier.name', 'Supplier'], ['cpu_capacity', 'vCPU'], ['ram_capacity_gb', 'RAM GB'], ['renewal_date', 'Renews', 'date'], ['assignedUser.email', 'Customer'], ['status', 'Status', 'badge'], ['deployment_status', 'Deploy', 'badge']],
        'filters' => [['status', 'Status', ['available' => 'Available', 'assigned' => 'Assigned', 'maintenance' => 'Maintenance', 'retired' => 'Retired']], ['access_model', 'Access', ['managed' => 'Managed', 'unmanaged' => 'Unmanaged']]],
        'fields' => [['label', 'Server ID / label', 'text', 'required|string|max:120'], ['ip_address', 'IP address', 'text', 'required|ip'], ['provider_id', 'Supplier', 'select', 'nullable|exists:providers,id', null, [Provider::class, 'name']],
            ['supplier_reference', 'Supplier reference (order / VPS ID)', 'text', 'nullable|string|max:190'], ['location', 'Region', 'text', 'nullable|string|max:80'],
            ['ssh_port', 'SSH port', 'number', 'required|integer|between:1,65535'], ['ssh_user', 'SSH user', 'text', 'required|alpha_dash|max:40'], ['role', 'Role', 'select', 'required|in:customer_vps,shared,internal', ['customer_vps' => 'Customer VPS', 'shared' => 'Shared', 'internal' => 'Internal']],
            ['access_model', 'Access model', 'select', 'required|in:managed,unmanaged', ['managed' => 'Managed (root restricted by service policy)', 'unmanaged' => 'Unmanaged (customer gets SSH/root)']],
            ['cpu_capacity', 'vCPU capacity', 'number', 'required|integer|min:0'], ['ram_capacity_gb', 'RAM capacity (GB)', 'number', 'required|integer|min:0'], ['storage_capacity_gb', 'Storage capacity (GB)', 'number', 'required|integer|min:0'],
            ['architecture', 'CPU architecture', 'select', 'required|in:x86_64,aarch64', ['x86_64' => 'x86_64', 'aarch64' => 'aarch64 (not supported by recipes yet)']],
            ['os_name', 'Operating system', 'text', 'nullable|string|max:40'],
            ['purchase_cost_minor', 'Purchase cost ₹ per billing period (internal only)', 'money', 'nullable|numeric|min:0'],
            ['billing_period', 'Supplier billing period', 'select', 'required|in:monthly,quarterly,yearly', ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly']],
            ['renewal_date', 'Supplier renewal date (YYYY-MM-DD)', 'text', 'nullable|date_format:Y-m-d'],
            ['ssh_host_fingerprint', 'SSH host key fingerprint (pinned; required for live deploy) — OpenSSH format SHA256:… from ssh-keyscan | ssh-keygen -lf -', 'text', 'nullable|string|max:120|regex:#^SHA256:[A-Za-z0-9+/]{43}=?$#'],
            ['ssh_credential', 'SSH private key / temporary credential (stored encrypted, never shown). Never enter a permanent root password.', 'secret', 'nullable|string|max:8000'],
            ['status', 'Status', 'select', 'required|in:available,assigned,maintenance,retired', ['available' => 'Available', 'assigned' => 'Assigned', 'maintenance' => 'Maintenance', 'retired' => 'Retired']]],
    ],
    'server_pools' => [
        'model' => ServerPool::class, 'module' => 'servers', 'title' => 'Server pools', 'search' => ['name'], 'actions' => ['create', 'edit', 'delete'],
        'columns' => [['name', 'Name'], ['kind', 'Kind'], ['is_active', 'Active', 'bool']],
        'fields' => [['name', 'Name', 'text', 'required|string|max:120'], ['kind', 'Kind', 'select', 'required|in:dedicated_vps,shared_host', ['dedicated_vps' => 'Dedicated VPS', 'shared_host' => 'Shared host (never sold as dedicated VPS)']], ['is_active', 'Active', 'checkbox', 'boolean']],
    ],
    'payments' => [
        'model' => PaymentAttempt::class, 'module' => 'payments', 'title' => 'Payments', 'search' => ['gateway_order_id', 'gateway_payment_id'], 'actions' => [], 'with' => ['order'],
        'columns' => [['id', '#'], ['order.number', 'Order'], ['gateway', 'Gateway'], ['mode', 'Mode'], ['amount_minor', 'Amount', 'money'], ['status', 'Status', 'badge'], ['created_at', 'Created', 'date']],
        'filters' => [['status', 'Status', ['created' => 'Created', 'verified' => 'Verified', 'failed' => 'Failed', 'amount_mismatch' => 'Amount mismatch']], ['gateway', 'Gateway', ['razorpay' => 'Razorpay']]],
    ],
    'invoices' => [
        'model' => Invoice::class, 'module' => 'invoices', 'title' => 'Invoices', 'search' => ['number'], 'actions' => [], 'with' => ['user'], 'row_link' => 'admin.invoices.download',
        'columns' => [['number', 'Number'], ['type', 'Type'], ['user.email', 'Customer'], ['total_minor', 'Total', 'money'], ['status', 'Status', 'badge'], ['issued_at', 'Issued', 'date']],
        'filters' => [['type', 'Type', ['invoice' => 'Invoice', 'credit_note' => 'Credit note']], ['status', 'Status', ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'issued' => 'Issued (credit note)', 'cancelled' => 'Cancelled / void']]],
    ],
    'subscriptions' => [
        'model' => Subscription::class, 'module' => 'subscriptions', 'title' => 'Subscriptions', 'search' => [], 'actions' => [], 'with' => ['user', 'plan'],
        'columns' => [['id', '#'], ['user.email', 'Customer'], ['plan.name', 'Plan'], ['billing_interval', 'Interval'], ['status', 'Status', 'badge'], ['next_due_at', 'Next due', 'date']],
        'filters' => [['status', 'Status', ['active' => 'Active', 'past_due' => 'Past due', 'cancelled' => 'Cancelled']]],
    ],
    'deployments' => [
        'model' => Deployment::class, 'module' => 'deployments', 'title' => 'Deployments', 'search' => ['recipe'], 'actions' => [], 'with' => ['service', 'server'], 'row_link' => 'admin.deployments.show',
        'columns' => [['id', '#'], ['service.label', 'Service'], ['server.label', 'Server'], ['recipe', 'Recipe'], ['mode', 'Mode'], ['status', 'Status', 'badge'], ['created_at', 'Created', 'date']],
        'filters' => [['status', 'Status', ['queued' => 'Queued', 'running' => 'Running', 'dry_run_ok' => 'Dry run OK', 'succeeded' => 'Succeeded', 'failed' => 'Failed']]],
    ],
    'service_requests' => [
        'model' => ServiceRequest::class, 'module' => 'projects', 'title' => 'Quote requests', 'search' => ['number'], 'actions' => [], 'with' => ['user', 'plan'], 'row_link' => 'admin.requests.show',
        'columns' => [['number', 'Number'], ['user.email', 'Customer'], ['kind', 'Kind'], ['plan.name', 'Plan'], ['quote_minor', 'Quote', 'money'], ['status', 'Status', 'badge'], ['created_at', 'Submitted', 'date']],
        'filters' => [['status', 'Status', ['submitted' => 'Submitted', 'quoted' => 'Quoted', 'accepted' => 'Accepted', 'declined' => 'Declined']], ['kind', 'Kind', ['wordpress' => 'WordPress', 'laravel' => 'Laravel', 'web' => 'Web', 'seo' => 'SEO', 'ai_access' => 'AI All-Access']]],
    ],
    'departments' => [
        // Creating/editing departments (incl. SLA targets and alert inbox) needs tickets.assign (supervisors), not tickets.edit.
        'model' => Department::class, 'module' => 'tickets', 'perms' => ['create' => 'assign', 'edit' => 'assign'], 'title' => 'Departments', 'search' => ['name'], 'actions' => ['create', 'edit'],
        'columns' => [['name', 'Name'], ['slug', 'Slug'], ['is_active', 'Active', 'bool'], ['sla_first_response_hours', 'First response (h)'], ['sla_resolution_hours', 'Resolution (h)']],
        'fields' => [['name', 'Name', 'text', 'required|string|max:80'], ['slug', 'Slug', 'text', 'required|alpha_dash|max:60|unique'],
            ['description', 'Description (shown to customers when opening a ticket)', 'text', 'nullable|string|max:255'],
            ['alert_email', 'Team alert inbox (optional, staff address)', 'text', 'nullable|email|max:190'],
            ['is_active', 'Accepts new tickets', 'checkbox', 'nullable|boolean'],
            ['sla_first_response_hours', 'SLA first-response target in hours (blank = use priority defaults / none)', 'number', 'nullable|integer|min:1|max:720'],
            ['sla_resolution_hours', 'SLA resolution target in hours (blank = use priority defaults / none)', 'number', 'nullable|integer|min:1|max:2160']],
    ],
    'doc_categories' => [
        'model' => DocCategory::class, 'module' => 'docs', 'title' => 'Doc categories', 'search' => ['name'], 'actions' => ['create', 'edit', 'delete'],
        'columns' => [['name', 'Name'], ['slug', 'Slug'], ['sort_order', 'Order']],
        'fields' => [['name', 'Name', 'text', 'required|string|max:120'], ['slug', 'Slug', 'text', 'required|alpha_dash|max:80|unique'], ['sort_order', 'Sort order', 'number', 'nullable|integer|min:0']],
    ],
    'docs' => [
        'model' => DocArticle::class, 'module' => 'docs', 'title' => 'Documentation', 'search' => ['title', 'slug'], 'actions' => ['create', 'edit', 'delete'], 'with' => ['category'],
        'columns' => [['title', 'Title'], ['category.name', 'Category'], ['is_published', 'Published', 'bool'], ['updated_at', 'Updated', 'date']],
        'fields' => [['doc_category_id', 'Category', 'select', 'required|exists:doc_categories,id', null, [DocCategory::class, 'name']], ['title', 'Title', 'text', 'required|string|max:160'], ['slug', 'Slug', 'text', 'required|alpha_dash|max:120|unique'],
            ['excerpt', 'Excerpt', 'textarea', 'nullable|string|max:300'], ['meta_description', 'SEO meta description', 'text', 'nullable|string|max:200'], ['body', 'Body (Markdown)', 'textarea', 'required|string|max:60000'],
            ['sort_order', 'Sort order', 'number', 'nullable|integer|min:0'], ['is_published', 'Published', 'checkbox', 'boolean']],
    ],
    'notifications' => [
        'model' => NotificationLog::class, 'module' => 'notifications', 'title' => 'Notification log', 'search' => ['event', 'dedupe_key'], 'actions' => [], 'row_action' => 'notification_retry',
        'columns' => [['event', 'Event'], ['channel', 'Channel'], ['status', 'Status', 'badge'], ['attempts', 'Attempts'], ['error', 'Error'], ['created_at', 'When', 'date']],
        'filters' => [['status', 'Status', ['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped']], ['channel', 'Channel', ['telegram' => 'Telegram', 'mail' => 'Email']]],
    ],
    'security_logs' => [
        'model' => AuditLog::class, 'module' => 'security_logs', 'title' => 'Security & audit logs', 'search' => ['action', 'ip'], 'actions' => [], 'with' => ['user'],
        'columns' => [['action', 'Action'], ['user.email', 'Actor'], ['subject_type', 'Subject'], ['subject_id', 'ID'], ['ip', 'IP'], ['created_at', 'When', 'date']],
    ],
];
