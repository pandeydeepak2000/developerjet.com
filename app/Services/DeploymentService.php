<?php

namespace App\Services;

use App\Jobs\RunDeployment;
use App\Models\Deployment;
use App\Models\ResourceProfile;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\Services\Provisioning\DryRunWorker;
use App\Services\Provisioning\PlacementPlanner;
use App\Services\Provisioning\ProvisioningWorker;
use App\Services\Provisioning\RecipeRegistry;
use App\Services\Provisioning\SshWorker;
use App\Support\Audit;
use App\Support\OrderStatus as S;
use App\Support\Setting;
use Illuminate\Support\Facades\DB;

class DeploymentService
{
    public function __construct(private PlacementPlanner $planner, private OrderService $orders, private Notifier $notifier) {}

    public function worker(): ProvisioningWorker
    {
        return config('provisioning.worker') === 'ssh' ? app(SshWorker::class) : app(DryRunWorker::class);
    }

    /** Admin assigns a VPS to a paid service after compatibility validation. For plans with no resource profile
     *  (n8n, Docker VPS, Claude Code, bundle, OpenClaw), staff record the required size explicitly — nothing invented. */
    public function assign(Service $service, Server $server, User $actor, ?array $confirmed = null): Service
    {
        if (! in_array($service->status, ['pending_provisioning', 'failed'], true)) {
            throw new OrderException('Only services awaiting provisioning can be assigned a server.');
        }

        if ($service->order && ! S::isPaidState($service->order->status)) {
            throw new OrderException('The order has no verified payment.');
        }

        $requirement = $service->plan?->resourceProfile;
        $newConfig = null;

        if ($requirement === null) {
            // No catalogue profile: the staff member must state what this service needs (checked against the chosen server).
            if (! is_array($confirmed) || ! isset($confirmed['vcpu'], $confirmed['ram_gb'], $confirmed['disk_gb'])) {
                throw new OrderException('This plan has no confirmed resource profile. Record the required vCPU, RAM and disk for this service before assigning a server.');
            }
            $confirmed = array_map('intval', $confirmed);
            $requirement = new ResourceProfile([
                'vcpu' => max(1, $confirmed['vcpu']), 'ram_gb' => max(1, $confirmed['ram_gb']), 'disk_gb' => max(1, $confirmed['disk_gb']),
            ]);
            $newConfig = ['confirmed_resources' => $confirmed + ['confirmed_by' => $actor->id, 'confirmed_at' => now()->toIso8601String()]];
        }

        DB::transaction(function () use ($service, $server, $actor, $requirement, $newConfig) {
            $locked = Server::whereKey($server->id)->lockForUpdate()->firstOrFail();
            // A server added without a size was bought for this order: it takes the plan's size.
            if ($locked->sizeUnknown() && $requirement->vcpu && $requirement->ram_gb && $requirement->disk_gb) {
                $locked->forceFill(['cpu_capacity' => $requirement->vcpu, 'ram_capacity_gb' => $requirement->ram_gb, 'storage_capacity_gb' => $requirement->disk_gb])->save();
            }
            $reason = $this->planner->incompatibility($locked, $requirement);

            $reason ??= $this->accessModelMismatch($locked, $service) ?? $this->economicsProblem($locked, $service);

            if ($reason) {
                throw new OrderException($reason);
            }

            if ($service->server_id && $service->server_id !== $locked->id) {
                Server::whereKey($service->server_id)->update(['status' => 'available', 'assigned_user_id' => null, 'assigned_order_id' => null, 'deployment_status' => 'none']);
            }

            $locked->update(['status' => 'assigned', 'assigned_user_id' => $service->user_id, 'assigned_order_id' => $service->order_id]);
            $service->update(array_merge(['server_id' => $locked->id], $newConfig ? ['config' => array_merge($service->config ?? [], $newConfig)] : []));
            Audit::log('server.assigned', $locked, ['service_id' => $service->id, 'order_id' => $service->order_id] + ($newConfig ?? []), $actor->id);
        });

        return $service->fresh();
    }

    /** The placement requirement for a service: the catalogue profile, or the size staff recorded at assignment. */
    private function requirementFor(Service $service): ?ResourceProfile
    {
        if ($profile = $service->plan?->resourceProfile) {
            return $profile;
        }

        $confirmed = $service->config['confirmed_resources'] ?? null;
        if (! is_array($confirmed) || ! isset($confirmed['vcpu'], $confirmed['ram_gb'], $confirmed['disk_gb'])) {
            return null;
        }

        return new ResourceProfile([
            'vcpu' => (int) $confirmed['vcpu'], 'ram_gb' => (int) $confirmed['ram_gb'], 'disk_gb' => (int) $confirmed['disk_gb'],
        ]);
    }

    /** The server's access model must match the plan's (all current plans are self-hosted / root access); the architecture must be supported. */
    public function accessModelMismatch(Server $server, Service $service): ?string
    {
        $model = $service->plan?->access_model ?? 'unmanaged';
        if ($server->access_model !== $model) {
            return "This server is set up for {$server->access_model} access but the plan is {$model}.";
        }
        if (! in_array($server->architecture, config('provisioning.supported_arch'), true)) {
            return 'Server CPU architecture is not supported by the deployment recipes.';
        }

        return null;
    }

    /**
     * Cost guard-rails. Only enforced when the owner has configured limits (Settings → Provisioning); unknown figures
     * never block and are never invented.
     */
    public function economicsProblem(Server $server, Service $service): ?string
    {
        $monthlyCost = $this->monthlyCost($server);
        $limit = Setting::get('infrastructure.max_monthly_cost_minor');
        if ($monthlyCost !== null && $limit !== null && $limit !== '' && $monthlyCost > (int) $limit) {
            return 'Server monthly cost exceeds the configured cost limit.';
        }

        $minMargin = Setting::get('infrastructure.min_margin_percent');
        $price = $service->plan?->priceFor('monthly');
        if ($monthlyCost !== null && $price && $minMargin !== null && $minMargin !== '') {
            $margin = ($price - $monthlyCost) / $price * 100;
            if ($margin < (float) $minMargin) {
                return 'Margin on this server ('.round($margin, 1).'%) is below the configured minimum ('.$minMargin.'%).';
            }
        }

        return null;
    }

    public function monthlyCost(Server $server): ?int
    {
        if (! $server->purchase_cost_minor) {
            return null;
        }

        return (int) round($server->purchase_cost_minor / match ($server->billing_period) {
            'quarterly' => 3, 'yearly' => 12, default => 1
        });
    }

    /** Why this service cannot be deployed right now (null = OK). */
    public function blockedReason(Service $service, string $mode): ?string
    {
        if (Setting::get('provisioning.mode', 'manual') !== 'manual') {
            return 'Automatic provisioning is not verified. Switch to Manual Assignment to deploy.';
        }
        if (! $service->server_id) {
            return 'Assign a server first.';
        }
        if (! $service->order || ! S::isPaidState($service->order->status)) {
            return 'The order has no verified payment.';
        }
        if (! in_array($service->order->status, [S::AWAITING_PROVISIONING, S::PROVISIONING], true)) {
            return 'The order is not awaiting provisioning.';
        }
        $recipe = app(RecipeRegistry::class)->recipe($service->kind);
        if (! $recipe) {
            return 'No installation recipe exists for this service type.';
        }
        $gate = $recipe['gated'] ?? null;
        if ($gate === 'n8n_license' && ! Setting::bool('n8n.resale_verified')) {
            return 'n8n commercial resale licensing has not been verified.';
        }
        if ($gate === 'openclaw_spec' && ! Setting::bool('openclaw.spec_approved')) {
            return 'The OpenClaw hosting specification has not been approved.';
        }
        if ($mode === 'live' && (! config('provisioning.live_enabled') || ! $this->worker()->isLive())) {
            return 'Live provisioning is disabled. Run a dry run instead.';
        }
        if ($reason = $this->planner->incompatibility($this->serverForCheck($service), $this->requirementFor($service))) {
            return $reason;
        }

        return null;
    }

    /** The assigned server is "assigned" to this very service, so re-check capacity only. */
    private function serverForCheck(Service $service): Server
    {
        $server = $service->server;
        $probe = $server->replicate();
        $probe->status = 'available';
        $probe->assigned_order_id = null;
        $probe->assigned_user_id = null;

        return $probe;
    }

    public function deploy(Service $service, User $actor, string $mode = 'dry_run'): Deployment
    {
        if ($reason = $this->blockedReason($service, $mode)) {
            throw new OrderException($reason);
        }

        $recipe = config('provisioning.recipes.'.$service->kind);

        $deployment = DB::transaction(function () use ($service, $actor, $mode, $recipe) {
            Service::whereKey($service->id)->lockForUpdate()->first();

            // Idempotent: a deployment already in flight is returned instead of starting a second one.
            if ($active = Deployment::where('service_id', $service->id)->whereIn('status', ['queued', 'preflight', 'running'])->first()) {
                return $active;
            }

            $attempt = Deployment::where('service_id', $service->id)->count() + 1;

            // Safe retry: resume after steps already confirmed by a previous failed LIVE attempt on the same server/recipe version.
            $previous = $mode === 'live' ? Deployment::where('service_id', $service->id)->where('server_id', $service->server_id)
                ->where('mode', 'live')->where('status', 'failed')->where('recipe_version', $recipe['version'])->latest('id')->first() : null;

            return Deployment::create([
                'service_id' => $service->id, 'server_id' => $service->server_id, 'order_id' => $service->order_id,
                'requested_by' => $actor->id, 'recipe' => $service->kind, 'recipe_version' => $recipe['version'],
                'mode' => $mode, 'status' => 'queued', 'attempt' => $attempt, 'completed_steps' => $previous?->completed_steps,
                'idempotency_key' => "deploy:{$service->id}:{$attempt}",
            ]);
        });

        if ($deployment->wasRecentlyCreated) {
            Audit::log('deployment.requested', $deployment, ['mode' => $mode], $actor->id);
            RunDeployment::dispatch($deployment->id)->afterCommit();
        }

        return $deployment;
    }

    /** Runs inside the queue worker. */
    public function execute(Deployment $deployment): Deployment
    {
        $service = $deployment->service()->with('order.user', 'server', 'plan.resourceProfile')->first();
        $live = $deployment->mode === 'live';
        $label = $live ? '' : ' (DRY RUN)';

        $deployment->update(['status' => 'preflight', 'started_at' => now()]);
        $service->server->update(['deployment_status' => 'running']);
        $this->notifier->admin('deployment_started', "deployment:{$deployment->id}", "Deployment #{$deployment->id}{$label} started for {$service->label}.");

        if ($live && $service->order->status === S::AWAITING_PROVISIONING) {
            $this->orders->transition($service->order, S::PROVISIONING, null, 'Deployment started.');
        }

        // Preflight (real, DB-based): resource compatibility.
        $reason = $this->planner->incompatibility($this->serverForCheck($service), $this->requirementFor($service));
        if ($reason) {
            return $this->finish($deployment, $service, false, [['step' => 'check_resources', 'level' => 'error', 'message' => $reason]], $reason, $live);
        }

        $deployment->update(['status' => 'running']);
        $steps = config('provisioning.recipes.'.$deployment->recipe.'.steps', []);

        try {
            // A dry run NEVER touches a server, whatever the worker config says: only a live deployment uses the real worker.
            $worker = $deployment->mode === 'live' ? $this->worker() : app(DryRunWorker::class);
            $result = $worker->run($deployment, $steps);
        } catch (\Throwable $e) {
            report($e);
            $result = new Provisioning\WorkerResult(false, [['step' => 'worker', 'level' => 'error', 'message' => 'Worker raised an unexpected error.']], 'An unexpected error occurred.', $live);
        }

        return $this->finish($deployment, $service, $result->success, $result->log, $result->safeError, $live);
    }

    private function finish(Deployment $deployment, Service $service, bool $success, array $log, ?string $safeError, bool $live): Deployment
    {
        foreach ($log as $row) {
            $deployment->logs()->create(['level' => $row['level'], 'step' => $row['step'], 'message' => mb_substr($row['message'], 0, 500)]);
        }

        $server = $service->server;

        if (! $live) {
            // A dry run never changes service/order state and is never reported as a real success.
            $deployment->update(['status' => $success ? 'dry_run_ok' : 'failed', 'finished_at' => now(), 'error_summary' => $safeError]);
            $server->healthChecks()->create(['check' => 'dry_run', 'status' => 'simulated', 'detail' => 'Simulation only — no remote check performed.']);
            $server->update(['deployment_status' => 'none']);

            return $deployment->fresh();
        }

        if ($success) {
            $deployment->update(['status' => 'succeeded', 'finished_at' => now(), 'error_summary' => null]);
            $server->update(['deployment_status' => 'succeeded']);
            $service->update(['status' => 'active']);

            if ($service->order->services()->where('status', '!=', 'active')->doesntExist()) {
                $this->orders->transition($service->order->fresh(), S::ACTIVE, null, 'All services provisioned.');
            }

            $this->notifier->admin('deployment_completed', "deployment:{$deployment->id}", "Deployment #{$deployment->id} completed for {$service->label}.");
            $this->notifier->customer('deployment_completed', "deployment:{$deployment->id}", $service->user, 'deployment_completed',
                ['service' => $service->label, 'url' => route('dashboard.services.show', $service)]);
        } else {
            $deployment->update(['status' => 'failed', 'finished_at' => now(), 'error_summary' => $safeError ? mb_substr($safeError, 0, 250) : 'Deployment failed']);
            $server->update(['deployment_status' => 'failed']);
            // The service stays inactive; the order is marked failed (retryable).
            $order = $service->order()->first(); // reload: the in-memory copy predates the provisioning transition
            if ($order && $order->status === S::PROVISIONING) {
                $this->orders->transition($order, S::FAILED, null, 'Provisioning failed: '.($safeError ?? 'see deployment log'));
            }
            $this->notifier->admin('deployment_failed', "deployment:{$deployment->id}", "Deployment #{$deployment->id} FAILED for {$service->label}: ".($safeError ?? 'see log'));
        }

        Audit::log('deployment.finished', $deployment, ['status' => $deployment->status]);

        return $deployment->fresh();
    }

    /** Safe retry: only after a failure; the order returns to awaiting provisioning and a fresh attempt is queued by deploy(). */
    public function prepareRetry(Service $service, User $actor): void
    {
        $order = $service->order;

        if ($order && $order->status === S::FAILED) {
            $this->orders->transition($order, S::AWAITING_PROVISIONING, $actor, 'Retrying provisioning.');
        }
    }
}
