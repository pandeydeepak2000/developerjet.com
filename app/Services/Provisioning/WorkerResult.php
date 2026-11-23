<?php

namespace App\Services\Provisioning;

class WorkerResult
{
    /** @param  array<int, array{step: string, level: string, message: string}>  $log */
    public function __construct(
        public bool $success,
        public array $log = [],
        public ?string $safeError = null, // customer-neutral; raw output/credentials never go here
        public bool $live = false,
    ) {}
}
