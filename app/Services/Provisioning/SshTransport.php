<?php

namespace App\Services\Provisioning;

use App\Models\Server;

/**
 * Deliberately inert. A real SSH transport needs an approved SSH library (e.g. phpseclib), host-key pinning proven
 * against a disposable VPS, and owner sign-off. Until then every connection attempt fails safely.
 */
class SshTransport implements RemoteTransport
{
    public function connect(Server $server, string $pinnedFingerprint): void
    {
        throw new TransportException('The SSH transport is not enabled in this build.');
    }

    public function exec(string $command): array
    {
        throw new TransportException('The SSH transport is not enabled in this build.');
    }

    public function disconnect(): void {}
}
