<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\Infrastructure\ControlPanelCapabilities;
use App\Services\Provisioning\RecipeRegistry;
use App\Support\Audit;
use App\Support\DeploymentProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Customer service panel. Only real recorded data is shown: no fake metrics, no sample credentials. */
class ServicePanelController extends Controller
{
    public function show(Service $service): View
    {
        $this->authorize('view', $service);
        $service->load('plan.resourceProfile', 'order.invoices', 'deployments', 'server.supplier', 'subscription.invoices');

        // Invoices for THIS service only: the purchase invoice (order) plus its renewal invoices (subscription).
        $invoices = collect($service->order?->invoices ?? [])
            ->merge($service->subscription?->invoices ?? collect())
            ->unique('id')->sortByDesc('id')->take(6)->values();

        // Real recorded health observations only; simulated (dry-run) rows are never presented as monitoring.
        $health = $service->server
            ? $service->server->healthChecks()->where('status', '!=', 'simulated')->latest('id')->limit(5)->get()
            : collect();

        return view('dashboard.service-show', [
            'service' => $service,
            'latest' => $service->deployments->first(),
            'providers' => config('provisioning.ai_providers'),
            'controls' => app(ControlPanelCapabilities::class)->for($service),
            'registry' => app(RecipeRegistry::class),
            'progress' => DeploymentProgress::for($service),
            'invoices' => $invoices,
            'health' => $health,
        ]);
    }

    /** Polling endpoint for the setup timeline: owner-only, DB-derived, no internal diagnostics, never cached. */
    public function progress(Service $service): JsonResponse
    {
        $this->authorize('view', $service);
        $service->load('order', 'deployments');
        $progress = DeploymentProgress::for($service);

        return response()->json($progress + ['service_status' => $service->status])
            ->header('Cache-Control', 'no-store, private');
    }

    /** Optional customer-supplied AI key: stored encrypted, write-only, never echoed back or logged. */
    public function configureAi(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);
        abort_unless(in_array($service->kind, ['hermes_cli', 'hermes_workspace', 'bundle', 'openclaw', 'claude_code'], true), 404);

        if ($request->boolean('skip')) {
            $service->update(['config' => array_merge($service->config ?? [], ['ai_skipped' => true]), 'ai_provider' => null, 'ai_api_key' => null]);

            return back()->with('success', 'AI configuration skipped. Your service is delivered with configuration marked incomplete.');
        }

        $data = $request->validate([
            'ai_provider' => ['required', 'in:'.implode(',', array_keys(config('provisioning.ai_providers')))],
            'ai_api_key' => ['required', 'string', 'min:8', 'max:300'],
        ]);

        $service->update(['ai_provider' => $data['ai_provider'], 'ai_api_key' => $data['ai_api_key'], 'config' => array_merge($service->config ?? [], ['ai_skipped' => false])]);
        Audit::log('service.ai_configured', $service, ['provider' => $data['ai_provider']]); // key never logged

        return back()->with('success', 'AI provider saved securely.');
    }

    public function domain(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);
        abort_unless(in_array($service->kind, ['hermes_workspace', 'n8n', 'bundle', 'openclaw'], true), 404);

        $data = $request->validate(['custom_domain' => ['required', 'string', 'max:190', 'regex:/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/i']]);
        $config = $service->config ?? [];
        $config['domain_token'] ??= 'djet-verify-'.Str::lower(Str::random(24));

        $service->update(['custom_domain' => strtolower($data['custom_domain']), 'domain_verified' => false, 'config' => $config]);

        return back()->with('success', 'Domain saved. Add the DNS TXT record shown below, then press Verify.');
    }

    public function verifyDomain(Service $service): RedirectResponse
    {
        $this->authorize('view', $service);
        abort_unless($service->custom_domain && ($token = $service->config['domain_token'] ?? null), 404);

        $records = @dns_get_record('_developerjet-verify.'.$service->custom_domain, DNS_TXT) ?: [];
        $found = collect($records)->contains(fn ($r) => ($r['txt'] ?? null) === $token);

        if ($found) {
            $service->update(['domain_verified' => true]);
            Audit::log('service.domain_verified', $service, ['domain' => $service->custom_domain]);

            return back()->with('success', 'Domain verified.');
        }

        return back()->with('error', 'We could not find the TXT record yet. DNS changes can take time to propagate.');
    }
}
