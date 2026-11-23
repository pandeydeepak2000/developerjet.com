<?php

namespace App\Services\Provisioning;

use App\Models\Deployment;

/**
 * Live worker. Runs recipe steps over a RemoteTransport using ONLY the fixed, owner-approved command catalogue.
 *  - Host identity is pinned: no fingerprint, or a mismatch, aborts before any command.
 *  - Steps with no approved command stop the run safely (nothing is guessed).
 *  - Completed steps are persisted, so a retry resumes instead of repeating work (idempotent).
 *  - Raw command output is never stored or shown; only the step name and a safe summary.
 * With the shipped SshTransport (inert) every live run fails safely at connection.
 */
class SshWorker implements ProvisioningWorker
{
    public function __construct(private RemoteTransport $transport, private RecipeRegistry $recipes) {}

    public function isLive(): bool
    {
        return true;
    }

    public function run(Deployment $deployment, array $steps): WorkerResult
    {
        $server = $deployment->server;
        $service = $deployment->service;
        $log = [];

        $fail = function (string $step, string $message, string $safe) use (&$log, $deployment) {
            $log[] = ['step' => $step, 'level' => 'error', 'message' => $message];
            $deployment->update(['failed_step' => $step]);

            return new WorkerResult(false, $log, $safe, true);
        };

        if (! $server->ssh_host_fingerprint) {
            return $fail('check_ssh', 'No pinned SSH host fingerprint is recorded for this server.', 'The server host identity is not verified.');
        }

        try {
            $this->transport->connect($server, $server->ssh_host_fingerprint);
        } catch (TransportException $e) {
            return $fail('check_ssh', 'Connection failed: '.mb_substr($e->getMessage(), 0, 200), 'Could not establish a verified connection to the server.');
        }

        $done = $deployment->completed_steps ?? [];
        $domain = $service->domain_verified ? $service->custom_domain : null;

        try {
            foreach ($steps as $step) {
                $meta = $this->recipes->step($step);

                if (in_array($step, $done, true)) {
                    $log[] = ['step' => $step, 'level' => 'info', 'message' => 'Skipped — already completed in a previous attempt.'];

                    continue;
                }
                if (($meta['needs_domain'] ?? false) && $domain === null) {
                    $log[] = ['step' => $step, 'level' => 'warn', 'message' => 'Skipped — no verified custom domain; the default address is arranged by the admin.'];

                    continue;
                }
                if (! $this->recipes->approved($step)) {
                    return $fail($step, 'Stopped: no owner-approved command exists for "'.$this->recipes->label($step).'".', 'This setup step has not been approved for automation yet.');
                }

                $result = $this->transport->exec($this->recipes->render($step, $domain));

                if ($result['exit'] !== 0) {
                    return $fail($step, 'Step "'.$this->recipes->label($step).'" failed (exit '.$result['exit'].').', 'A setup step failed; the team has been notified.');
                }
                if ($problem = $this->validateProbe($step, $result['stdout'])) {
                    return $fail($step, $problem, $problem);
                }

                $done[] = $step;
                $deployment->update(['completed_steps' => $done]);
                $log[] = ['step' => $step, 'level' => 'info', 'message' => $this->recipes->label($step).' — completed.'];
            }
        } catch (TransportException|\InvalidArgumentException $e) {
            return $fail('worker', 'Aborted: '.mb_substr($e->getMessage(), 0, 200), 'The setup was interrupted safely.');
        } finally {
            $this->transport->disconnect();
        }

        return new WorkerResult(true, $log, null, true);
    }

    /** Interprets the read-only preflight probes. Returns a safe problem message, or null when fine. */
    private function validateProbe(string $step, string $stdout): ?string
    {
        if ($step === 'check_os') {
            preg_match('/^ID="?([a-z0-9._-]+)"?/m', $stdout, $id);
            preg_match('/^VERSION_ID="?([0-9.]+)"?/m', $stdout, $ver);
            $supported = config('provisioning.supported_os')[$id[1] ?? ''] ?? [];

            return in_array($ver[1] ?? '', $supported, true) ? null : 'Unsupported operating system.';
        }

        if ($step === 'check_arch') {
            return in_array(trim($stdout), config('provisioning.supported_arch'), true) ? null : 'Unsupported CPU architecture.';
        }

        if ($step === 'check_resources') {
            $lines = preg_split('/\R/', trim($stdout));
            $cols = preg_split('/\s+/', trim(end($lines)));
            $freeGb = isset($cols[3]) ? ((int) $cols[3]) / 1048576 : 0;

            return $freeGb >= config('provisioning.min_free_disk_gb') ? null : 'Not enough free disk space.';
        }

        return null;
    }
}
