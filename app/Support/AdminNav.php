<?php

namespace App\Support;

use App\Models\User;
use App\Services\ExportService;

/**
 * Admin navigation (2026-11-28): a short sidebar of sections; each section's related pages are tabs at the top of the
 * page. Nothing was removed — every page is still one click away, in the section it belongs to. Visibility follows the
 * same permissions as before (and every page still checks its own permission on the server).
 */
class AdminNav
{
    /** [label, icon, tabs: list<[label, route(:param), permission, url patterns (|-separated)]>] */
    private const SECTIONS = [
        ['Dashboard', 'dashboard', [
            ['Dashboard', 'admin.dashboard', 'dashboard.view', 'admin.dashboard'],
        ]],
        ['Orders', 'cart', [
            ['Orders', 'admin.orders.index', 'orders.view', 'admin/orders*'],
            ['Install logs', 'admin.resource.index:deployments', 'deployments.view', 'admin/r/deployments*|admin/deployments*'],
        ]],
        ['Customers', 'user', [
            ['Customers', 'admin.users.index', 'customers.view', 'admin/users*|admin/impersonat*'],
        ]],
        ['Servers', 'server', [
            ['Servers', 'admin.resource.index:servers', 'servers.view', 'admin/r/servers*'],
            ['Infrastructure', 'admin.infrastructure', 'servers.view', 'admin/infrastructure*'],
            ['Suppliers', 'admin.resource.index:providers', 'servers.view', 'admin/r/providers*'],
            ['Supplier offers', 'admin.resource.index:provider_offers', 'servers.view', 'admin/r/provider_offers*'],
            ['Server pools', 'admin.resource.index:server_pools', 'servers.view', 'admin/r/server_pools*'],
        ]],
        ['Billing', 'card', [
            ['Payments', 'admin.resource.index:payments', 'payments.view', 'admin/r/payments*'],
            ['Invoices', 'admin.resource.index:invoices', 'invoices.view', 'admin/r/invoices*'],
            ['Wallet', 'admin.wallet.index', 'wallet.view', 'admin/wallet*'],
            ['Renewals', 'admin.billing.index', 'subscriptions.view', 'admin/billing|admin/billing/*'],
            ['Subscriptions', 'admin.resource.index:subscriptions', 'subscriptions.view', 'admin/r/subscriptions*'],
            ['Billing checks', 'admin.billing-ops', 'invoices.view', 'admin/billing-ops*'],
        ]],
        ['Support', 'headset', [
            ['Tickets', 'admin.tickets.index', 'tickets.view', 'admin/tickets*'],
            ['Departments', 'admin.resource.index:departments', 'tickets.view', 'admin/r/departments*'],
            ['Docs', 'admin.resource.index:docs', 'docs.view', 'admin/r/docs*'],
            ['Doc categories', 'admin.resource.index:doc_categories', 'docs.view', 'admin/r/doc_categories*'],
        ]],
        ['Products', 'layers', [
            ['Plans', 'admin.plans.index', 'plans.view', 'admin/plans*'],
            ['Categories', 'admin.resource.index:categories', 'categories.view', 'admin/r/categories*'],
            ['Add-ons', 'admin.resource.index:addons', 'addons.view', 'admin/r/addons*'],
        ]],
        ['Sales', 'workflow', [
            ['Leads', 'admin.leads.index', 'leads.view', 'admin/leads*'],
            ['Quote requests', 'admin.resource.index:service_requests', 'projects.view', 'admin/r/service_requests*|admin/requests*'],
            ['Projects', 'admin.projects.index', 'projects.view', 'admin/projects*'],
            ['SEO', 'admin.seo.index', 'seo.view', 'admin/seo*'],
        ]],
        ['Settings', 'settings', [
            ['Settings', 'admin.settings.edit', 'settings.view', 'admin/settings|admin/settings/*'],
            ['Staff & roles', 'admin.staff.index', 'staff.view', 'admin/staff*|admin/roles*'],
            ['Notifications', 'admin.resource.index:notifications', 'notifications.view', 'admin/r/notifications*'],
            ['Security logs', 'admin.resource.index:security_logs', 'security_logs.view', 'admin/r/security_logs*'],
            ['Exports', 'admin.exports', '__export', 'admin/exports*'],
        ]],
    ];

    /**
     * Sections this staff member can open, each with its visible tabs and whether it is the current section.
     *
     * @return list<array{label: string, icon: string, href: string, active: bool, tabs: list<array{label: string, href: string, active: bool}>}>
     */
    public static function sections(User $user): array
    {
        $request = request();
        $canExport = null;
        $out = [];

        foreach (self::SECTIONS as [$label, $icon, $tabs]) {
            $visible = [];
            foreach ($tabs as [$tabLabel, $route, $permission, $patterns]) {
                if ($permission === '__export') {
                    $canExport ??= app(ExportService::class)->allowedTypes($user) !== [];
                    if (! $canExport) {
                        continue;
                    }
                } elseif (! $user->hasPermission($permission)) {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $route), 2, null);
                $active = false;
                foreach (explode('|', $patterns) as $pattern) {
                    $active = $active || $request->is($pattern) || $request->routeIs($pattern);
                }
                $visible[] = ['label' => $tabLabel, 'href' => $param ? route($name, $param) : route($name), 'active' => $active];
            }
            if ($visible === []) {
                continue;
            }
            $out[] = ['label' => $label, 'icon' => $icon, 'href' => $visible[0]['href'],
                'active' => collect($visible)->contains('active', true), 'tabs' => $visible];
        }

        return $out;
    }
}
