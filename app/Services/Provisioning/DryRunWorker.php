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
        foreach ($steps as $step) {
            $log[] = ['step' => $step, 'level' => 'info', 'message' => '[dry run] would execute recipe step "'.$step.'" on '.$deployment->server->label.' — no changes made.'];
        }

        return new WorkerResult(true, $log, null, false);
    }
}
