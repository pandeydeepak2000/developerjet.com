<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\DeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** One attempt only (tries = 1): retries are explicit admin actions that create a new idempotent deployment attempt. */
class RunDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $deploymentId) {}

    public function handle(DeploymentService $service): void
    {
        $deployment = Deployment::find($this->deploymentId);

        // Guard against queue redelivery: only a queued deployment may start.
        if ($deployment && $deployment->status === 'queued') {
            $service->execute($deployment);
        }
    }
}
