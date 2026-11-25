<?php

namespace App\Services\Provisioning;

use App\Models\Deployment;

/**
 * Live worker. Runs recipe steps over a RemoteTransport using ONLY the fixed, owner-approved recipe scripts.
 *  - Host identity: the first successful connection pins the server's SSH host key (trust on first use); every later
 *    connection must present the same key or the run stops before any command (and before the password is sent).
 *  - Steps with no approved command stop the run safely (nothing is guessed).
 *  - Completed steps are persisted, so a retry resumes instead of repeating work (scripts are idempotent too).
 *  - Output is streamed line by line into the deployment log for the live admin terminal. The server password is
 *    redacted from output, lines are length-capped and noisy steps are truncated.
 */
class SshWorker implements ProvisioningWorker
{
    private const MAX_LINES_PER_STEP = 300;

    public function __construct(private RemoteTransport $transport, private RecipeRegistry $recipes) {}

    public function isLive(): bool
    {
        return true;
    }

    public function run(Deployment $deployment, array $steps): WorkerResult
    {
        $server = $deployment->server;
        $service = $deployment->service;
        $kind = $deployment->recipe;
        $secret = (string) $server->ssh_credential;

        $emit = function (string $step, string $level, string $message) use ($deployment, $secret) {
            if ($secret !== '' && strlen($secret) >= 4) {
                $message = str_replace($secret, '••••', $message);
            }
            $deployment->logs()->create(['level' => $level, 'step' => $step, 'message' => mb_substr($message, 0, 500)]);
        };
        $fail = function (string $step, string $message, string $safe) use ($emit, $deployment) {
            $emit($step, 'error', $message);
            $deployment->update(['failed_step' => $step]);

            return new WorkerResult(false, [], $safe, true);
        };

        $emit('check_ssh', 'info', 'Connecting to '.$server->ssh_user.'@'.$server->ip_address.':'.$server->ssh_port.' …');
        try {
            $fingerprint = $this->transport->connect($server, $server->ssh_host_fingerprint ?: null);
        } catch (TransportException $e) {
            return $fail('check_ssh', mb_substr($e->getMessage(), 0, 300), 'Could not establish a verified connection to the server.');
        }
        if (! $server->ssh_host_fingerprint) {
            $server->forceFill(['ssh_host_fingerprint' => $fingerprint])->save();
            $emit('check_ssh', 'info', 'Connected. Server identity saved ('.$fingerprint.') — future runs must match it.');
        } else {
            $emit('check_ssh', 'info', 'Connected. Server identity verified.');
        }

        $done = $deployment->completed_steps ?? [];
        $domain = $service->appDomain(); // verified customer domain, else the staff-assigned subdomain

        try {
            foreach ($steps as $step) {
                $meta = $this->recipes->step($step) ?? [];
                $label = $this->recipes->label($step);

                if (in_array($step, $done, true)) {
                    $emit($step, 'info', '↷ '.$label.' — already done in an earlier attempt, skipped.');

                    continue;
                }
                if (($meta['needs_domain'] ?? false) && $domain === null) {
                    return $fail($step, 'Stopped: this app needs its web address (subdomain). Set it in step 1 and deploy again.', 'The app address is not set yet.');
                }
                if (! $this->recipes->approved($step, $kind)) {
                    return $fail($step, 'Stopped: "'.$label.'" has no approved install command yet for this product.', 'This setup step has not been approved for automation yet.');
                }

                $emit($step, 'step', '▶ '.$label);
                [$buffer, $lines, $hidden] = ['', 0, 0];
                $onOutput = function (string $chunk) use (&$buffer, &$lines, &$hidden, $emit, $step) {
                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = self::clean(substr($buffer, 0, $pos));
                        $buffer = substr($buffer, $pos + 1);
                        if ($line === '') {
                            continue;
                        }
                        if ($lines++ < self::MAX_LINES_PER_STEP) {
                            $emit($step, 'out', $line);
                        } else {
                            $hidden++;
                        }
                    }
                };

                $result = $this->transport->exec($this->recipes->render($step, $domain, $kind), $onOutput);
                if (($rest = self::clean($buffer)) !== '') {
                    $emit($step, 'out', $rest);
                }
                if ($hidden > 0) {
                    $emit($step, 'out', "… {$hidden} more lines not shown");
                }

                if ($result['exit'] !== 0) {
                    return $fail($step, '✗ '.$label.' failed (exit '.$result['exit'].'). See the lines above.', 'A setup step failed: '.$label.'.');
                }
                if ($problem = $this->validateProbe($step, $result['stdout'])) {
                    return $fail($step, '✗ '.$problem, $problem);
                }

                $done[] = $step;
                $deployment->update(['completed_steps' => $done]);
                $emit($step, 'info', '✓ '.$label);
            }
        } catch (TransportException|\InvalidArgumentException $e) {
            return $fail('worker', 'Aborted: '.mb_substr($e->getMessage(), 0, 300), 'The setup was interrupted safely.');
        } finally {
            $this->transport->disconnect();
        }

        return new WorkerResult(true, [], null, true);
    }

    /** Strip ANSI colour codes / carriage-return progress bars from a terminal line. */
    private static function clean(string $line): string
    {
        $line = preg_replace('/\x1B\[[0-9;?]*[ -\/]*[@-~]/', '', $line) ?? $line;
        if (str_contains($line, "\r")) {
            $parts = explode("\r", $line);
            $line = (string) end($parts);
        }

        return trim($line);
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
