<?php

namespace App\Support;

use App\Models\Deployment;
use App\Models\Service;

/**
 * Customer-facing setup timeline derived ONLY from recorded database state: verified payment, server assignment
 * and the latest LIVE deployment (its status, completed steps and safe error summary). Dry runs never advance the
 * customer timeline, nothing is invented, and internal log detail never leaves the admin area.
 *
 * Stages: Order Confirmed → Environment Preparing → Application Installing → Security Configuration →
 * Final Verification → Ready to Use. Each stage is done | current | pending | failed.
 */
class DeploymentProgress
{
    public const STAGES = [
        'order' => 'Order Confirmed',
        'environment' => 'Environment Preparing',
        'installing' => 'Application Installing',
        'security' => 'Security Configuration',
        'verification' => 'Final Verification',
        'ready' => 'Ready to Use',
    ];

    private const SECURITY_STEPS = [
        'security_baseline', 'create_isolated_user', 'configure_auth', 'configure_gateway_auth',
        'configure_domain', 'configure_https', 'configure_network_isolation', 'configure_persistence', 'configure_backup',
    ];

    private const VERIFICATION_STEPS = ['configure_monitoring', 'health_check', 'record_state'];

    /** @return array{stages: array<int, array{key: string, label: string, state: string, detail: ?string}>, summary: string, error: ?string, poll: bool, ready: bool} */
    public static function for(Service $service): array
    {
        $order = $service->order;
        $paid = $order !== null && OrderStatus::isPaidState($order->status);
        $active = $service->status === 'active';
        $deployment = self::latestLive($service);
        $done = $deployment?->completed_steps ?? [];
        $recipeSteps = config('provisioning.recipes.'.$service->kind.'.steps', []);
        $hasDomain = (bool) $service->domain_verified;

        // Steps that will actually run: domain-dependent steps are skipped without a verified domain.
        $applicable = array_values(array_filter($recipeSteps, fn ($s) => $hasDomain || ! (config('provisioning.step_catalog.'.$s.'.needs_domain') ?? false)));
        $groupDone = fn (array $steps): bool => collect($steps)->every(fn ($s) => in_array($s, $done, true));

        $installSteps = array_values(array_filter($applicable, fn ($s) => str_starts_with($s, 'install_')));
        $securitySteps = array_values(array_filter($applicable, fn ($s) => in_array($s, self::SECURITY_STEPS, true)));
        $verifySteps = array_values(array_filter($applicable, fn ($s) => in_array($s, self::VERIFICATION_STEPS, true)));

        $states = [
            'order' => $paid ? 'done' : 'current',
            'environment' => $service->server_id ? 'done' : ($paid ? 'current' : 'pending'),
            'installing' => 'pending', 'security' => 'pending', 'verification' => 'pending',
            'ready' => $active ? 'done' : 'pending',
        ];
        $details = ['order' => null, 'environment' => $service->server_id ? null : 'Awaiting VPS assignment', 'installing' => null, 'security' => null, 'verification' => null, 'ready' => null];
        $error = null;

        if ($active) {
            $states['installing'] = $states['security'] = $states['verification'] = 'done';
            $summary = 'Your service is ready to use.';
        } elseif ($deployment && $deployment->status === 'failed') {
            $failedStage = self::stageOfStep($deployment->failed_step ?? 'worker');
            foreach (['installing', 'security', 'verification'] as $key) {
                $states[$key] = match (true) {
                    $key === $failedStage => 'failed',
                    self::stageIndex($key) < self::stageIndex($failedStage) => 'done',
                    default => 'pending',
                };
            }
            if ($failedStage === 'environment') {
                $states['environment'] = 'failed';
            }
            $error = $deployment->error_summary ?: 'A setup step failed.';
            $details[$failedStage] = $error;
            $summary = 'Setup needs attention. Our team has been notified and will retry — contact support if you have questions.';
        } elseif ($deployment && in_array($deployment->status, ['queued', 'preflight', 'running'], true)) {
            $states['installing'] = $groupDone($installSteps) ? 'done' : 'current';
            $states['security'] = $states['installing'] === 'done' ? ($groupDone($securitySteps) ? 'done' : 'current') : 'pending';
            $states['verification'] = $states['security'] === 'done' ? ($groupDone($verifySteps) ? 'done' : 'current') : 'pending';
            if ($deployment->status !== 'running') {
                $details['installing'] = $deployment->status === 'queued' ? 'Setup queued' : 'Running preflight checks';
            }
            $summary = 'Your software is being installed and checked. This page updates automatically.';
        } elseif ($deployment && $deployment->status === 'succeeded') {
            // A succeeded live deployment always activates the service (DeploymentService::finish); defensive branch.
            $states['installing'] = $states['security'] = $states['verification'] = 'done';
            $summary = 'Setup finished. Final checks are being recorded.';
        } else {
            $summary = $service->server_id
                ? 'Server assigned — setup will begin shortly.'
                : ($paid ? 'Your service is queued. Our team will assign infrastructure and begin setup.' : 'Waiting for payment confirmation.');
        }

        if ($order && in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            $summary = $order->status === OrderStatus::CANCELLED ? 'This order was cancelled.' : 'This order was refunded.';
        }

        $stages = [];
        foreach (self::STAGES as $key => $label) {
            $stages[] = ['key' => $key, 'label' => $label, 'state' => $states[$key], 'detail' => $details[$key]];
        }

        $terminal = $active || ($order && in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true));

        return ['stages' => $stages, 'summary' => $summary, 'error' => $error, 'poll' => ! $terminal, 'ready' => $active];
    }

    /** Short label of where the service currently is (for list pages). */
    public static function stageLabel(Service $service): string
    {
        $progress = self::for($service);
        $reached = false;
        foreach ($progress['stages'] as $stage) {
            if (in_array($stage['state'], ['current', 'failed'], true)) {
                return $stage['label'];
            }
            if ($stage['state'] === 'pending') {
                $reached = true;
                break;
            }
        }

        if ($progress['ready']) {
            return 'Ready to Use';
        }

        // Everything so far is done but the next step has not started yet (e.g. server assigned, install not queued).
        return $reached ? 'Awaiting setup' : 'Order Confirmed';
    }

    private static function latestLive(Service $service): ?Deployment
    {
        if ($service->relationLoaded('deployments')) {
            return $service->deployments->firstWhere('mode', 'live');
        }
        if ($service->relationLoaded('latestLiveDeployment')) {
            return $service->latestLiveDeployment;
        }

        return $service->deployments()->where('mode', 'live')->first();
    }

    private static function stageOfStep(string $step): string
    {
        return match (true) {
            str_starts_with($step, 'check_') => 'environment',
            in_array($step, self::SECURITY_STEPS, true) => 'security',
            in_array($step, self::VERIFICATION_STEPS, true) => 'verification',
            default => 'installing',
        };
    }

    private static function stageIndex(string $key): int
    {
        return (int) array_search($key, array_keys(self::STAGES), true);
    }
}
