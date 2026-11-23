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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeploymentController extends Controller
{
    public function __construct(private DeploymentService $deployments) {}

    public function show(Request $request, Deployment $deployment): View
    {
        abort_unless($request->user()->hasPermission('deployments.view'), 403);

        return view('admin.deployments.show', ['deployment' => $deployment->load('service.order', 'server', 'logs')]);
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

        try {
            if ($service->order?->status === 'failed') {
                $this->deployments->prepareRetry($service, $request->user());
                $service->load('order');
            }
            $deployment = $this->deployments->deploy($service->fresh('order', 'server', 'plan.resourceProfile'), $request->user(), $data['mode']);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.deployments.show', $deployment)->with('success', $data['mode'] === 'dry_run' ? 'Dry run queued — nothing will be installed.' : 'Deployment queued.');
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
