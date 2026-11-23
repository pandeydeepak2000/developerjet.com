<?php

namespace Tests\Concerns;

use App\Models\Server;
use App\Services\Provisioning\RemoteTransport;
use App\Services\Provisioning\TransportException;

/**
 * In-memory SSH transport for deployment tests: records every command it is asked to run and never opens a
 * connection to anything. The real SshTransport stays inert until it is approved on a disposable VPS.
 */
class FakeTransport implements RemoteTransport
{
    /** @var list<string> every command handed to exec(), in order */
    public array $commands = [];

    public bool $connected = false;

    /** @var list<string> commands that should exit non-zero */
    public array $failCommands = [];

    public bool $hostKeyMismatch = false;

    public string $os = "ID=ubuntu\nVERSION_ID=\"24.04\"\n";

    public string $arch = 'x86_64';

    public string $df = "Filesystem 1024-blocks Used Available Capacity Mounted on\n/dev/vda1 83886080 10485760 73400320 13% /";

    public function connect(Server $server, string $pinnedFingerprint): void
    {
        if ($this->hostKeyMismatch) {
            throw new TransportException('Host key mismatch');
        }
        $this->connected = true;
    }

    public function exec(string $command): array
    {
        $this->commands[] = $command;
        $out = match ($command) {
            'cat /etc/os-release' => $this->os,
            'uname -m' => $this->arch,
            'df -Pk /' => $this->df,
            default => 'ok',
        };

        return ['exit' => in_array($command, $this->failCommands, true) ? 1 : 0, 'stdout' => $out];
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }
}
