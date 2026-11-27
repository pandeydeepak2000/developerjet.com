<?php

namespace App\Services\Provisioning;

use App\Models\Deployment;
use App\Models\Service;
use App\Models\User;
use App\Services\DeploymentService;
use App\Services\OrderException;
use App\Support\Audit;
use Illuminate\Support\Str;

/**
 * One-click actions on a delivered server over SSH: reboot, restart app, reset app login, suspend (stop app),
 * unsuspend (start app) and reinstall fresh (delete the app + data, then a new install). Only reviewed scripts from
 * config('provisioning.server_actions') / recipes.<kind>.actions run; the server password is never logged.
 */
class ServerActions
{
    public function __construct(private RemoteTransport $transport, private RecipeRegistry $recipes, private DeploymentService $deployments) {}

    /** Script template for an action on this product, or null when not offered. */
    public function command(Service $service, string $action): ?string
    {
        $base = config('provisioning.server_actions.'.$action);
        if (! $base) {
            return null;
        }

        return $this->recipes->recipe($service->kind)['actions'][$action] ?? $base['command'];
    }

    /** @return array<string, string> action => label, offered for this service (optionally customer-facing only). */
    public function available(Service $service, bool $customer = false): array
    {
        if (! $this->liveOn() || ! $service->server_id) {
            return [];
        }
        $out = [];
        foreach (config('provisioning.server_actions') as $key => $meta) {
            if ($customer && ! ($meta['customer'] ?? false)) {
                continue;
            }
            if ($this->command($service, $key) !== null) {
                $out[$key] = $meta['label'];
            }
        }

        return $out;
    }

    public function liveOn(): bool
    {
        return (bool) config('provisioning.live_enabled') && config('provisioning.worker') === 'ssh';
    }

    /**
     * Run one action now and return [ok, message, output-tail]. Throws OrderException for things the user must fix.
     *
     * @return array{ok: bool, message: string, output: string}
     */
    public function run(Service $service, string $action, User $actor): array
    {
        $template = $this->command($service, $action);
        if ($template === null || ! $this->liveOn()) {
            throw new OrderException('This action is not available for this service.');
        }
        $server = $service->server;
        if (! $server) {
            throw new OrderException('No server is assigned to this service.');
        }

        $script = str_starts_with($template, '@') ? RecipeRegistry::script(substr($template, 1)) : $template;
        $secret = (string) $server->ssh_credential;

        try {
            $fingerprint = $this->transport->connect($server, $server->ssh_host_fingerprint ?: null);
            if (! $server->ssh_host_fingerprint) {
                $server->forceFill(['ssh_host_fingerprint' => $fingerprint])->save();
            }
            $result = $this->transport->exec($script);
        } catch (TransportException $e) {
            Audit::log('server.action_failed', $service, ['action' => $action, 'reason' => mb_substr($e->getMessage(), 0, 200)], $actor->id);
            throw new OrderException('Could not reach the server: '.$e->getMessage());
        } finally {
            $this->transport->disconnect();
        }

        $output = trim((string) $result['stdout']);
        if ($secret !== '' && strlen($secret) >= 4) {
            $output = str_replace($secret, '••••', $output);
        }
        $tail = mb_substr(implode("\n", array_slice(preg_split('/\R/', $output) ?: [], -6)), 0, 600);
        $ok = $result['exit'] === 0;
        Audit::log($ok ? 'server.action' : 'server.action_failed', $service, ['action' => $action, 'exit' => $result['exit']], $actor->id);

        if ($ok) {
            match ($action) {
                'stop_app' => $service->update(['status' => 'suspended']),
                'start_app' => $service->update(['status' => 'active']),
                default => null,
            };
        }

        $label = config('provisioning.server_actions.'.$action.'.label');

        return ['ok' => $ok, 'message' => $ok ? $label.' — done.' : $label.' failed (exit '.$result['exit'].').', 'output' => $tail];
    }

    /** Root password reset is offered for live, password-based root logins only (never for SSH-key servers). */
    public function canResetRootPassword(Service $service): bool
    {
        $server = $service->server;
        if (! $this->liveOn() || ! $server || $service->status !== 'active' || ($server->ssh_user ?: 'root') !== 'root') {
            return false;
        }
        $secret = (string) $server->ssh_credential;

        return $secret !== '' && ! str_contains($secret, 'PRIVATE KEY');
    }

    /**
     * Set a new root password on the server (chpasswd) and save it, encrypted, as the panel's login: the customer's
     * own choice, or a random 20-character one. The password travels base64-encoded (no shell quoting issues),
     * is never logged and is redacted from any error text.
     *
     * @return array{ok: bool, message: string}
     */
    public function resetRootPassword(Service $service, User $actor, ?string $chosen = null): array
    {
        if (! $this->canResetRootPassword($service)) {
            throw new OrderException('Root password reset is not available for this server.');
        }
        $server = $service->server;
        $new = $chosen ?? Str::password(20, symbols: false);
        $encoded = base64_encode($new);

        try {
            $fingerprint = $this->transport->connect($server, $server->ssh_host_fingerprint ?: null);
            if (! $server->ssh_host_fingerprint) {
                $server->forceFill(['ssh_host_fingerprint' => $fingerprint])->save();
            }
            $result = $this->transport->exec("set -e\nnew=\$(printf '%s' '{$encoded}' | base64 -d)\nprintf 'root:%s\\n' \"\$new\" | chpasswd\necho 'Root password changed.'");
        } catch (TransportException $e) {
            Audit::log('server.root_password_reset_failed', $service, ['reason' => mb_substr(str_replace($new, '••••', $e->getMessage()), 0, 200)], $actor->id);
            throw new OrderException('Could not reach the server: '.$e->getMessage());
        } finally {
            $this->transport->disconnect();
        }

        if ($result['exit'] !== 0) {
            Audit::log('server.root_password_reset_failed', $service, ['exit' => $result['exit']], $actor->id);

            return ['ok' => false, 'message' => 'The server did not accept the new password (exit '.$result['exit'].'). Your old password still works.'];
        }

        $server->update(['ssh_credential' => $new]);
        Audit::log('server.root_password_reset', $service, [], $actor->id);

        return ['ok' => true, 'message' => $chosen !== null
            ? 'Root password changed. Use your new password for SSH from now on.'
            : 'New root password set. Press Show under Server login to see it.'];
    }

    /** "Reinstall fresh" needs a reviewed wipe script for this product. */
    public function canReinstall(Service $service): bool
    {
        return $this->liveOn() && $service->server_id && $this->command($service, 'wipe_app') !== null
            && in_array($service->status, ['active', 'suspended', 'failed'], true);
    }

    /** Reinstall fresh: delete the app and its data, then start a brand-new install (returns the deployment to watch). */
    public function reinstall(Service $service, User $actor): Deployment
    {
        if (! in_array($service->status, ['active', 'suspended', 'failed'], true)) {
            throw new OrderException('Only a delivered service can be reinstalled.');
        }
        $wipe = $this->run($service, 'wipe_app', $actor);
        if (! $wipe['ok']) {
            throw new OrderException($wipe['message'].' '.$wipe['output']);
        }

        // wiped_at stops a later install from "resuming" steps that were done before the wipe.
        $service->update(['status' => 'pending_provisioning', 'config' => array_merge($service->config ?? [], ['wiped_at' => now()->toDateTimeString()])]);
        $service->server?->update(['deployment_status' => 'none']);
        Audit::log('service.reinstall_started', $service, [], $actor->id);

        return $this->deployments->deploy($service->fresh('order', 'server', 'plan.resourceProfile'), $actor, 'live');
    }
}
