<?php

namespace App\Http\Controllers;

use App\Models\CustomerApiKey;
use App\Models\Service;
use App\Services\Infrastructure\ControlPanelCapabilities;
use App\Services\OrderException;
use App\Services\Provisioning\RecipeRegistry;
use App\Services\Provisioning\ServerActions;
use App\Services\Provisioning\ServerStats;
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

        if ($service->kind === 'ai_access') {
            return view('dashboard.service-ai', ['service' => $service, 'invoices' => $invoices]);
        }
        $serverActions = app(ServerActions::class);

        return view('dashboard.service-show', [
            'service' => $service,
            'latest' => $service->deployments->first(),
            'providers' => config('provisioning.ai_providers'),
            'savedKeys' => CustomerApiKey::where('user_id', $service->user_id)->latest('id')->get(),
            'controls' => app(ControlPanelCapabilities::class)->for($service),
            'registry' => app(RecipeRegistry::class),
            'progress' => DeploymentProgress::for($service),
            'invoices' => $invoices,
            'health' => $health,
            'actions' => $service->status === 'active' ? $serverActions->available($service, true) : [],
            'canResetRoot' => $serverActions->canResetRootPassword($service),
            'canReinstall' => $service->status === 'active' && $serverActions->canReinstall($service),
            'liveStats' => app(ServerStats::class)->available($service) && $service->status === 'active',
        ]);
    }

    /** Customer one-click actions on their own delivered server (reboot, restart app, reset app login). */
    public function action(Request $request, Service $service, string $action, ServerActions $actions): RedirectResponse
    {
        $this->authorize('view', $service);
        abort_unless($service->status === 'active' && array_key_exists($action, $actions->available($service, true)), 404);

        try {
            $result = $actions->run($service, $action, $request->user());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message'].($result['ok'] && $action === 'reset_login' ? ' Open your app and create the owner account again — your workflows are kept.' : ''));
    }

    /** Live usage (CPU, RAM, disk, network, uptime) for the panel. Cached per server, so polling is cheap. */
    public function stats(Service $service, ServerStats $stats): JsonResponse
    {
        $this->authorize('view', $service);
        abort_unless($stats->available($service), 404);

        return response()->json($stats->snapshot($service))->header('Cache-Control', 'no-store, private');
    }

    /**
     * Customer changes the root password — to one they type, or a random strong one. The panel keeps working because
     * the new password is saved (encrypted) for it.
     */
    public function rootPassword(Request $request, Service $service, ServerActions $actions): RedirectResponse
    {
        $this->authorize('view', $service);
        abort_unless($actions->canResetRootPassword($service), 404);

        $chosen = null;
        if ($request->input('mode') === 'custom') {
            $data = $request->validateWithBag('rootPassword', [
                'root_password' => ['required', 'string', 'min:12', 'max:64', 'confirmed', 'regex:/^[\x21-\x7E]+$/', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
            ], [
                'root_password.regex' => 'Use 12–64 characters with letters and numbers, and no spaces.',
                'root_password.confirmed' => 'The two passwords do not match.',
            ]);
            $chosen = $data['root_password'];
        }

        try {
            $result = $actions->resetRootPassword($service, $request->user(), $chosen);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to(route('dashboard.services.show', $service).'#access')->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /** Customer "Reinstall fresh": deletes the app and its data, then installs it again. Must type REINSTALL. */
    public function reinstall(Request $request, Service $service, ServerActions $actions): RedirectResponse
    {
        $this->authorize('view', $service);
        abort_unless($service->status === 'active' && $actions->canReinstall($service), 404);
        $request->validate(['confirm' => ['required', 'string', 'in:REINSTALL']], ['confirm.in' => 'Type REINSTALL in capitals to confirm.']);

        try {
            $actions->reinstall($service, $request->user());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard.services.show', $service)
            ->with('success', 'Reinstall started. Everything was deleted and a fresh install is running — follow the progress below.');
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
        abort_unless($service->usesAi(), 404);

        if ($request->boolean('skip')) {
            $service->update(['config' => array_merge($service->config ?? [], ['ai_skipped' => true]), 'ai_provider' => null, 'ai_api_key' => null]);

            return back()->with('success', 'AI configuration skipped. Your service is delivered with configuration marked incomplete.');
        }

        // Either pick a key already saved on the account, or add a new one (which is also saved to the account).
        if ($request->filled('api_key_id')) {
            $key = CustomerApiKey::where('user_id', $service->user_id)->findOrFail($request->integer('api_key_id'));
        } else {
            $data = $request->validate([
                'ai_provider' => ['required', 'in:'.implode(',', array_keys(config('provisioning.ai_providers')))],
                'ai_api_key' => ['required', 'string', 'min:8', 'max:300', 'not_regex:/\s/'],
            ]);
            $key = CustomerApiKey::store($service->user, $data['ai_provider'], $data['ai_api_key'], 'Added on '.$service->label);
            Audit::log('customer.api_key_added', $key, ['provider' => $key->provider], $service->user_id);
        }
        $key->applyTo($service);
        Audit::log('service.ai_configured', $service, ['provider' => $key->provider]); // key never logged

        return back()->with('success', $key->providerLabel().' key '.$key->masked().' is now used by this service.');
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
