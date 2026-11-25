<?php

namespace App\Services\Provisioning;

use App\Models\Server;

/** Remote command channel used by the live worker. Implementations MUST verify the host key before logging in. */
interface RemoteTransport
{
    /**
     * Connect and log in. With a pinned fingerprint the host key must match it; with null (first contact) the
     * server's key is accepted and its fingerprint returned so the caller can pin it ("trust on first use").
     *
     * @return string the server's host key fingerprint (OpenSSH "SHA256:…" format)
     *
     * @throws TransportException on connection, authentication or host-key mismatch
     */
    public function connect(Server $server, ?string $pinnedFingerprint): string;

    /**
     * Run one command (through bash, stderr merged into stdout).
     *
     * @param  (callable(string): void)|null  $onOutput  receives output chunks as they arrive (live terminal)
     * @return array{exit: int, stdout: string}
     */
    public function exec(string $command, ?callable $onOutput = null): array;

    public function disconnect(): void;
}
