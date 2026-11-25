<?php

namespace App\Services\Provisioning;

use App\Models\Server;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

/**
 * Real SSH transport (phpseclib, pure PHP — no SSH binary or PHP extension needed on the host).
 *  - The host key is checked BEFORE the password/key is sent: a pinned fingerprint must match exactly.
 *  - Logs in with the encrypted Server::ssh_credential (root password, or an OpenSSH/PEM private key).
 *  - Each command runs through `bash -c … 2>&1`, streaming output to the caller; per-command timeout.
 * The worker only ever passes fixed, owner-approved recipe scripts (RecipeRegistry) — never customer text.
 */
class SshTransport implements RemoteTransport
{
    private ?SSH2 $ssh = null;

    public function connect(Server $server, ?string $pinnedFingerprint): string
    {
        $host = (string) $server->ip_address;
        $port = (int) ($server->ssh_port ?: 22);

        try {
            $ssh = new SSH2($host, $port, (int) config('provisioning.ssh_connect_timeout', 15));
            $hostKey = @$ssh->getServerPublicHostKey();
        } catch (\Throwable) {
            $hostKey = false;
        }
        if (! is_string($hostKey) || $hostKey === '') {
            throw new TransportException("Could not reach SSH on {$host}:{$port} (connection refused, timed out or blocked by a firewall).");
        }

        $fingerprint = self::fingerprint($hostKey);
        if ($pinnedFingerprint !== null && $pinnedFingerprint !== '' && ! hash_equals(rtrim($pinnedFingerprint, '='), $fingerprint)) {
            throw new TransportException('Host key mismatch: this server is not the one we connected to before. If the VPS was reinstalled, clear its saved fingerprint under Servers and deploy again.');
        }

        $secret = (string) $server->ssh_credential;
        if ($secret === '') {
            throw new TransportException('No root password or SSH key is saved for this server.');
        }
        try {
            $auth = str_contains($secret, 'PRIVATE KEY') ? PublicKeyLoader::load($secret) : $secret;
            $loggedIn = @$ssh->login($server->ssh_user ?: 'root', $auth);
        } catch (\Throwable) {
            $loggedIn = false;
        }
        if (! $loggedIn) {
            throw new TransportException('Login failed: check the username and root password (or SSH key).');
        }

        $this->ssh = $ssh;

        return $fingerprint;
    }

    public function exec(string $command, ?callable $onOutput = null): array
    {
        if (! $this->ssh) {
            throw new TransportException('Not connected.');
        }

        $this->ssh->setTimeout((int) config('provisioning.ssh_command_timeout', 1800));
        $output = '';
        $this->ssh->exec('bash -c '.RecipeRegistry::posixQuote($command).' 2>&1', function (string $chunk) use (&$output, $onOutput) {
            // Keep the tail only (probes are short; installers can be chatty).
            $output = substr($output.$chunk, -65536);
            if ($onOutput) {
                $onOutput($chunk);
            }
        });

        if ($this->ssh->isTimeout()) {
            throw new TransportException('The step did not finish in time.');
        }
        $exit = $this->ssh->getExitStatus();

        return ['exit' => $exit === false ? 1 : (int) $exit, 'stdout' => $output];
    }

    public function disconnect(): void
    {
        $this->ssh?->disconnect();
        $this->ssh = null;
    }

    /** OpenSSH-style fingerprint of a host key line ("ssh-ed25519 AAAA…") → "SHA256:…" (no padding). */
    public static function fingerprint(string $hostKeyLine): string
    {
        $parts = preg_split('/\s+/', trim($hostKeyLine));
        $blob = base64_decode($parts[1] ?? $parts[0], true) ?: '';

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $blob, true)), '=');
    }
}
