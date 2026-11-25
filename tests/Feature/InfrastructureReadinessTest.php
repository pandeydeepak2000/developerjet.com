<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Services\Provisioning\SshTransport;
use App\Services\Provisioning\TransportException;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Milestone 12: infrastructure stays gated — inert SSH transport, strictly formatted host-key pins. */
class InfrastructureReadinessTest extends TestCase
{
    public function test_ssh_host_fingerprint_must_be_an_openssh_sha256_pin(): void
    {
        $field = collect(config('admin_resources.servers.fields'))->firstWhere(0, 'ssh_host_fingerprint');
        $rules = explode('|', $field[3]);

        $this->assertTrue(Validator::make(['f' => 'SHA256:'.str_repeat('a', 43)], ['f' => $rules])->passes());
        $this->assertTrue(Validator::make(['f' => null], ['f' => $rules])->passes());
        foreach (['MD5:aa:bb', 'SHA256:short', 'ssh-ed25519 AAAA', 'SHA256:'.str_repeat('a', 43).' extra'] as $bad) {
            $this->assertFalse(Validator::make(['f' => $bad], ['f' => $rules])->passes(), $bad);
        }
    }

    public function test_real_ssh_transport_turns_connection_errors_into_a_clear_transport_error(): void
    {
        // Unreachable host (nothing listens on 127.0.0.1:1): no raw library exception, no password ever sent.
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not reach SSH');
        (new SshTransport)->connect(new Server(['ip_address' => '127.0.0.1', 'ssh_port' => 1]), 'SHA256:'.str_repeat('a', 43));
    }

    public function test_host_key_fingerprint_matches_openssh_format(): void
    {
        // ssh-keygen -lf of this ed25519 key prints SHA256:FxAd1iiarTEhzUP/PS9MefB+i2oqAxloMsVdM8AKj0Q (test VPS, 2026-11-25).
        $this->assertMatchesRegularExpression('#^SHA256:[A-Za-z0-9+/]{43}$#', SshTransport::fingerprint('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIEa1sJBecavFoIMops0yfGjVpMfKxzgugtPVPBBJtheQ'));
    }
}
