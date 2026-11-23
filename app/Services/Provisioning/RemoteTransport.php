<?php

namespace App\Services\Provisioning;

use App\Models\Server;

/** Remote command channel used by the live worker. Implementations MUST verify the pinned host key before auth. */
interface RemoteTransport
{
    /** @throws TransportException on connection, authentication or host-key mismatch */
    public function connect(Server $server, string $pinnedFingerprint): void;

    /** @return array{exit: int, stdout: string} */
    public function exec(string $command): array;

    public function disconnect(): void;
}
