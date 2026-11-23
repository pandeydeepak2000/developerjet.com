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

    public function test_real_ssh_transport_is_inert_until_approved(): void
    {
        $this->expectException(TransportException::class);
        (new SshTransport)->connect(new Server, 'SHA256:'.str_repeat('a', 43));
    }
}
