<?php

namespace App\Services\Provisioning;

use App\Models\Deployment;

/**
 * Simulator: records the steps a live worker WOULD perform. Nothing is installed, no connection is opened, and the
 * result is never treated as a real deployment (the service is not activated).
 */
class DryRunWorker implements ProvisioningWorker
{
    public function isLive(): bool
    {
        return false;
    }

    public function run(Deployment $deployment, array $steps): WorkerResult
    {
        $log = [];
        $recipes = app(RecipeRegistry::class);
        $target = $deployment->server->ip_address ?: $deployment->server->label;
        foreach ($steps as $step) {
            $log[] = ['step' => $step, 'level' => 'info', 'message' => '[dry run] '.$recipes->label($step).' — would run on '.$target.', no changes made.'];
        }

        return new WorkerResult(true, $log, null, false);
    }
}
