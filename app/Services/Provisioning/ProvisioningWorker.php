<?php

namespace App\Services\Provisioning;

use App\Models\Deployment;

/**
 * Executes an approved deployment job against a server. Implementations run in an isolated worker context and MUST NOT
 * interpolate customer-controlled input into commands (only validated, escaped values such as a verified domain).
 */
interface ProvisioningWorker
{
    /** True only when the worker really changes remote servers. */
    public function isLive(): bool;

    /** @param  array<int, string>  $steps  ordered recipe step identifiers */
    public function run(Deployment $deployment, array $steps): WorkerResult;
}
