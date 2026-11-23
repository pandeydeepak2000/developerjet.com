<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\DeploymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * Customer hosting control panel + deployment progress UI: the timeline is derived only from recorded DB state,
 * the polling endpoint is owner-only and never leaks internal diagnostics or secrets.
 */
class ServicePanelProgressTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
    }

    private function paidService(string $slug = 'hermes-cli-launch'): Service
    {
        $this->approve($slug, 1000);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 900000, 'topup', 'seed'.$slug);
        $this->placeOrder($user, $slug, 'tok'.$slug, ['use_wallet' => 1]);

        return Service::latest('id')->firstOrFail();
    }

    public function test_setup_timeline_shows_six_stages_with_order_confirmed_done_for_a_paid_service(): void
    {
        $service = $this->paidService();

        $response = $this->actingAs($service->user)->get(route('dashboard.services.show', $service))
            ->assertOk()
            ->assertSee('Setup progress')
            ->assertSee('Order Confirmed')
            ->assertSee('Environment Preparing')
            ->assertSee('Application Installing')
            ->assertSee('Security Configuration')
            ->assertSee('Final Verification')
            ->assertSee('Ready to Use');

        // Paid, but no server assigned yet: the timeline is honest about waiting for a VPS.
        $response->assertSee('Awaiting VPS assignment');
        $this->assertSame('awaiting_provisioning', $service->order->fresh()->status);
    }

    public function test_progress_endpoint_returns_json_stages_and_is_owner_only(): void
    {
        $service = $this->paidService();
        $this->actingAs($service->user);

        $json = $this->getJson(route('dashboard.services.progress', $service))->assertOk()->json();

        $this->assertCount(6, $json['stages']);
        $this->assertSame('done', collect($json['stages'])->firstWhere('key', 'order')['state']);
        $this->assertSame('pending_provisioning', $json['service_status']);
        $this->assertTrue($json['poll']);
        $this->assertFalse($json['ready']);

        // Another customer cannot read this service's progress.
        $other = $this->customer();
        $this->actingAs($other)->getJson(route('dashboard.services.progress', $service))->assertForbidden();
    }

    public function test_progress_never_leaks_server_secrets_or_internal_details(): void
    {
        $service = $this->paidService();
        $admin = $this->staff();
        $server = $this->server(['ssh_credential' => 'PRIVATE-KEY-MATERIAL', 'ssh_host_fingerprint' => 'SHA256:secret']);
        app(DeploymentService::class)->assign($service, $server, $admin);

        $this->actingAs($service->user);
        $body = $this->get(route('dashboard.services.show', $service))->assertOk()->getContent();
        $this->assertStringNotContainsString('PRIVATE-KEY-MATERIAL', $body);
        $this->assertStringNotContainsString('SHA256:secret', $body);

        $json = json_encode($this->getJson(route('dashboard.services.progress', $service))->assertOk()->json());
        $this->assertStringNotContainsString('PRIVATE-KEY-MATERIAL', $json);
    }

    public function test_active_service_marks_timeline_ready_and_stops_polling(): void
    {
        $service = $this->paidService();
        $service->update(['status' => 'active', 'url' => 'https://app.example.test']);

        $json = $this->actingAs($service->user)->getJson(route('dashboard.services.progress', $service->fresh()))->assertOk()->json();
        $this->assertTrue($json['ready']);
        $this->assertFalse($json['poll']);
        $this->assertSame('done', collect($json['stages'])->firstWhere('key', 'ready')['state']);
        $this->assertSame('active', $json['service_status']);
    }

    public function test_dry_run_does_not_advance_the_customer_timeline(): void
    {
        $service = $this->paidService();
        $admin = $this->staff();
        $server = $this->server(['ssh_host_fingerprint' => 'SHA256:test']);
        app(DeploymentService::class)->assign($service, $server, $admin);
        app(DeploymentService::class)->deploy($service->fresh(), $admin, 'dry_run');

        $json = $this->actingAs($service->user)->getJson(route('dashboard.services.progress', $service))->assertOk()->json();
        // A dry run is a simulation: installing must NOT be reported as done.
        $this->assertNotSame('done', collect($json['stages'])->firstWhere('key', 'installing')['state']);
        $this->assertNotSame('done', collect($json['stages'])->firstWhere('key', 'ready')['state']);
    }
}
