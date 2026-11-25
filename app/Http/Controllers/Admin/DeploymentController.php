<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Service;
use App\Services\DeploymentService;
use App\Services\OrderException;
use App\Support\Audit;
use App\Support\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeploymentController extends Controller
{
    public function __construct(private DeploymentService $deployments) {}

    public function show(Request $request, Deployment $deployment): View
    {
        abort_unless($request->user()->hasPermission('deployments.view'), 403);

        return view('admin.deployments.show', ['deployment' => $deployment->load('service.order', 'server', 'logs')]);
    }

    /** Live terminal feed: new log lines after `after` (log id) plus the current status. Never includes secrets. */
    public function log(Request $request, Deployment $deployment): JsonResponse
    {
        abort_unless($request->user()->hasPermission('deployments.view'), 403);
        $after = (int) $request->query('after', 0);

        return response()->json([
            'status' => $deployment->status,
            'finished' => ! in_array($deployment->status, ['queued', 'preflight', 'running'], true),
            'completed_steps' => $deployment->completed_steps ?? [],
            'failed_step' => $deployment->failed_step,
            'lines' => $deployment->logs()->where('id', '>', $after)->orderBy('id')->limit(500)->get()
                ->map(fn ($l) => ['id' => $l->id, 'time' => Carbon::parse($l->created_at)->format('H:i:s'), 'level' => $l->level, 'step' => $l->step, 'message' => $l->message])
                ->values(),
        ]);
    }

    /**
     * Staff choose the app address when the customer picked "Use a DeveloperJet address" (or has not verified their
     * own domain yet). DeveloperJet controls this DNS, so it is used by the installer without a TXT check. The
     * DNS A record must point at the assigned server before the HTTPS step can succeed.
     */
    public function domain(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('servers.assign'), 403);
        abort_unless($service->needsDomain(), 404);
        $data = $request->validate([
            'assigned_domain' => ['required', 'string', 'max:190', 'regex:/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/i'],
        ], ['assigned_domain.regex' => 'Enter a hostname like client1.developerjet.com (no http:// or slashes).']);

        $domain = strtolower($data['assigned_domain']);
        if ($this->domainTaken($service, $domain)) {
            return back()->withErrors(['assigned_domain' => 'This address is already used by another service.']);
        }

        $this->saveDomain($service, $domain);

        return back()->with('success', 'App address set to '.$domain.'. Point its DNS A record at the server before deploying.');
    }

    /**
     * One-step setup from the order page: staff paste the VPS bought for this order (IP, SSH user, root password or
     * key) and, for apps that need a web address, the subdomain. The server record is created with the plan's size,
     * assigned to the service, and the service is then ready for Deploy. Supplier, cost and renewal details are
     * optional and can be filled in later under Infrastructure → Servers.
     */
    public function setup(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('servers.assign'), 403);
        if (! in_array($service->status, ['pending_provisioning', 'failed'], true)) {
            return back()->with('error', 'Server details can only be changed while the service is awaiting setup.');
        }

        $needsAddress = $service->needsDomain() && ! ($service->domain_verified && $service->custom_domain);
        $profile = $service->plan?->resourceProfile;

        $data = $request->validate([
            // Either a server already added under Infrastructure → Servers, or a new VPS typed in here.
            'server_id' => ['nullable', 'integer', 'exists:servers,id'],
            'ip_address' => ['required_without:server_id', 'nullable', 'ip'],
            'ssh_port' => ['nullable', 'integer', 'between:1,65535'],
            'ssh_user' => ['nullable', 'alpha_dash', 'max:40'],
            // Root password or private key: stored encrypted (Server::ssh_credential), never displayed again.
            'ssh_password' => [$service->server_id ? 'nullable' : 'required_without:server_id', 'nullable', 'string', 'max:8000'],
            'assigned_domain' => [$needsAddress ? 'required' : 'nullable', 'string', 'max:190', 'regex:/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/i'],
            // Only for plans without a catalogue size.
            'vcpu' => [$profile ? 'nullable' : 'required', 'integer', 'min:1', 'max:256'],
            'ram_gb' => [$profile ? 'nullable' : 'required', 'integer', 'min:1', 'max:4096'],
            'disk_gb' => [$profile ? 'nullable' : 'required', 'integer', 'min:1', 'max:65536'],
        ], [
            'ssh_password.required' => 'Enter the root password (or SSH key) of the VPS.',
            'ssh_password.required_without' => 'Enter the root password (or SSH key) of the VPS, or pick a server you already added.',
            'ip_address.required_without' => 'Enter the VPS IP address, or pick a server you already added.',
            'assigned_domain.required' => 'This app needs a web address, e.g. client1.developerjet.com.',
            'assigned_domain.regex' => 'Enter a hostname like client1.developerjet.com (no http:// or slashes).',
        ]);

        $domain = isset($data['assigned_domain']) ? strtolower($data['assigned_domain']) : null;
        if ($domain && $this->domainTaken($service, $domain)) {
            return back()->withInput($request->except('ssh_password'))->withErrors(['assigned_domain' => 'This address is already used by another service.']);
        }

        $port = (int) ($data['ssh_port'] ?? 22);
        $size = $profile
            ? ['vcpu' => (int) $profile->vcpu, 'ram_gb' => (int) $profile->ram_gb, 'disk_gb' => (int) $profile->disk_gb]
            : ['vcpu' => (int) $data['vcpu'], 'ram_gb' => (int) $data['ram_gb'], 'disk_gb' => (int) $data['disk_gb']];

        // A server already in inventory: just set the address and assign it (size/access checks run in assign()).
        if (! empty($data['server_id'])) {
            try {
                DB::transaction(function () use ($request, $service, $data, $domain, $profile, $size) {
                    if ($domain) {
                        $this->saveDomain($service, $domain);
                    }
                    if ((int) $service->server_id !== (int) $data['server_id']) {
                        $this->deployments->assign($service->fresh(), Server::findOrFail($data['server_id']), $request->user(), $profile ? null : $size);
                    }
                });
            } catch (OrderException $e) {
                return back()->withInput($request->except('ssh_password'))->with('error', $e->getMessage());
            }

            return back()->with('success', 'Server assigned'.($domain ? ' and app address set to '.$domain : '').'. Next: click Deploy.');
        }

        // The same IP may already be in inventory: reuse it only if it is this service's server or free.
        $server = Server::where('ip_address', $data['ip_address'])->where('ssh_port', $port)->first();
        if ($server && $server->id !== $service->server_id && ($server->status !== 'available' || $server->assigned_order_id || $server->assigned_user_id)) {
            return back()->withInput($request->except('ssh_password'))->withErrors(['ip_address' => 'This server ('.$server->label.') is already in use.']);
        }

        try {
            DB::transaction(function () use ($request, $service, $server, $data, $port, $size, $domain, $profile) {
                $server ??= $service->server_id ? Server::find($service->server_id) : null;
                $server ??= new Server(['status' => 'available', 'deployment_status' => 'none']);
                $isNew = ! $server->exists;

                $attrs = [
                    'label' => $server->label ?: trim(($service->order?->number ?? 'SRV').' · '.($service->plan?->name ?? $service->kind)),
                    'ip_address' => $data['ip_address'],
                    'ssh_port' => $port,
                    'ssh_user' => $data['ssh_user'] ?? 'root',
                    'role' => 'customer_vps',
                    'access_model' => $service->plan?->access_model ?? 'unmanaged',
                    'cpu_capacity' => max((int) $server->cpu_capacity, $size['vcpu']),
                    'ram_capacity_gb' => max((int) $server->ram_capacity_gb, $size['ram_gb']),
                    'storage_capacity_gb' => max((int) $server->storage_capacity_gb, $size['disk_gb']),
                ];
                if (! empty($data['ssh_password'])) {
                    $attrs['ssh_credential'] = $data['ssh_password'];
                }
                // A changed IP means a different machine: the old pinned host key no longer applies.
                if ($server->exists && $server->ip_address !== $data['ip_address']) {
                    $attrs['ssh_host_fingerprint'] = null;
                }
                $server->forceFill($attrs)->save();
                Audit::log($isNew ? 'server.created_from_order' : 'server.updated_from_order', $server, ['service_id' => $service->id, 'order_id' => $service->order_id], $request->user()->id);

                if ($domain) {
                    $this->saveDomain($service, $domain);
                }

                if ($service->server_id !== $server->id) {
                    $this->deployments->assign($service->fresh(), $server->fresh(), $request->user(), $profile ? null : $size);
                }
            });
        } catch (OrderException $e) {
            return back()->withInput($request->except('ssh_password'))->with('error', $e->getMessage());
        }

        return back()->with('success', 'Server details saved'.($domain ? ' and app address set to '.$domain : '').'. Next: click Deploy.');
    }

    /** The team installed the app by hand: hand the server over to the customer. */
    public function delivered(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('deployments.deploy'), 403);

        try {
            $this->deployments->markDelivered($service, $request->user());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Delivered. The customer has been emailed and can see the IP and root login in their dashboard.');
    }

    private function domainTaken(Service $service, string $domain): bool
    {
        return Service::where('id', '!=', $service->id)
            ->where(fn ($q) => $q->where('config->assigned_domain', $domain)->orWhere(fn ($q) => $q->where('custom_domain', $domain)->where('domain_verified', true)))
            ->exists();
    }

    private function saveDomain(Service $service, string $domain): void
    {
        $service->forceFill(['config' => array_merge($service->config ?? [], ['assigned_domain' => $domain])])->save();
        Audit::log('service.domain_assigned', $service, ['domain' => $domain]);
    }

    public function assign(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('servers.assign'), 403);
        $data = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
            // Only used when the plan has no catalogue resource profile: the size this service needs.
            'vcpu' => ['nullable', 'integer', 'min:1', 'max:256'],
            'ram_gb' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'disk_gb' => ['nullable', 'integer', 'min:1', 'max:65536'],
        ]);

        $confirmed = null;
        if (isset($data['vcpu'], $data['ram_gb'], $data['disk_gb'])) {
            $confirmed = ['vcpu' => $data['vcpu'], 'ram_gb' => $data['ram_gb'], 'disk_gb' => $data['disk_gb']];
        }

        try {
            $this->deployments->assign($service, Server::findOrFail($data['server_id']), $request->user(), $confirmed);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Server assigned after compatibility validation.');
    }

    public function deploy(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('deployments.deploy'), 403);
        $data = $request->validate(['mode' => ['required', 'in:dry_run,live']]);

        // While automatic install is switched off, Deploy runs a test run so staff still see every step in the terminal.
        $liveOff = $data['mode'] === 'live' && (! config('provisioning.live_enabled') || ! $this->deployments->worker()->isLive());
        $mode = $liveOff ? 'dry_run' : $data['mode'];

        try {
            if ($service->order?->status === 'failed') {
                $this->deployments->prepareRetry($service, $request->user());
                $service->load('order');
            }
            $deployment = $this->deployments->deploy($service->fresh('order', 'server', 'plan.resourceProfile'), $request->user(), $mode);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        // A test run is instant and needs no background worker: run it now (the queued job skips it afterwards).
        if ($mode === 'dry_run' && $deployment->fresh()->status === 'queued') {
            $this->deployments->execute($deployment->fresh());
        }

        return redirect()->route('admin.deployments.show', $deployment)->with('success', match (true) {
            $liveOff => 'Automatic install is switched off, so this ran as a test run — nothing was installed. Install over SSH, then press "mark delivered".',
            $mode === 'dry_run' => 'Test run finished — nothing was installed.',
            default => 'Install started. Follow it in the terminal below.',
        });
    }

    public function mode(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);
        $data = $request->validate(['mode' => ['required', 'in:manual,automatic']]);

        Setting::set('provisioning.mode', $data['mode']);
        Audit::log('settings.provisioning_mode', null, ['mode' => $data['mode']]);

        return back()->with('success', $data['mode'] === 'automatic'
            ? 'Automatic mode saved, but deployments stay blocked until a provider integration is verified.'
            : 'Manual assignment mode active.');
    }
}
