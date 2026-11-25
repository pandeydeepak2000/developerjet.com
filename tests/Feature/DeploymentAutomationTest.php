<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\DeploymentService;
use App\Services\OrderException;
use App\Services\Provisioning\RemoteTransport;
use App\Services\WalletService;
use App\Support\DeploymentProgress;
use App\Support\OrderStatus;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\Concerns\FakeTransport;
use Tests\TestCase;

/**
 * End-to-end automation coverage for EVERY product recipe (Hermes CLI, Hermes Workspace, n8n, AI Complete Bundle,
 * OpenClaw, Claude Code, Docker VPS) against the in-memory FakeTransport only. Nothing real is contacted; live SSH
 * stays disabled in production until it is proven on an explicitly authorised disposable VPS.
 */
class DeploymentAutomationTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
    }

    /** Representative plan per recipe + expected kind + access model + whether the plan has a catalogue profile. */
    public static function products(): array
    {
        return [
            'hermes-cli launch' => ['hermes-cli-launch', 'hermes_cli', 'unmanaged', true],
            'hermes-cli grow' => ['hermes-cli-grow', 'hermes_cli', 'unmanaged', true],
            'hermes-cli scale' => ['hermes-cli-scale', 'hermes_cli', 'unmanaged', true],
            'hermes-workspace launch' => ['hermes-workspace-launch', 'hermes_workspace', 'unmanaged', true],
            'hermes-workspace grow' => ['hermes-workspace-grow', 'hermes_workspace', 'unmanaged', true],
            'hermes-workspace scale' => ['hermes-workspace-scale', 'hermes_workspace', 'unmanaged', true],
            'n8n launch' => ['n8n-launch', 'n8n', 'unmanaged', false],
            'n8n grow' => ['n8n-grow', 'n8n', 'unmanaged', false],
            'n8n scale' => ['n8n-scale', 'n8n', 'unmanaged', false],
            'ai complete bundle' => ['ai-complete-bundle', 'bundle', 'unmanaged', false],
            'openclaw starter' => ['openclaw-starter', 'openclaw', 'unmanaged', false],
            'claude-code starter' => ['claude-code-a', 'claude_code', 'unmanaged', false],
            'docker-vps starter' => ['docker-vps-a', 'docker_vps', 'unmanaged', false],
            'docker-vps business' => ['docker-vps-c', 'docker_vps', 'unmanaged', false],
        ];
    }

    /** Test-only: approve every step command so a full recipe can complete against the fake transport. */
    private function approveAllCommands(): void
    {
        $catalog = config('provisioning.step_catalog');
        foreach ($catalog as $id => $step) {
            $catalog[$id]['command'] = $step['command'] ?? 'noop '.$id;
        }
        config(['provisioning.step_catalog' => $catalog]);
    }

    private function liftRecipeGates(): void
    {
        Setting::set('n8n.resale_verified', '1');
        Setting::set('openclaw.spec_approved', '1');
    }

    private function paidService(string $slug): Service
    {
        $this->approve($slug, 1000);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 5000000, 'topup', 'seed-'.$slug);
        $this->placeOrder($user, $slug, 'tok-'.$slug, ['use_wallet' => 1]);

        return Service::latest('id')->firstOrFail();
    }

    /** @dataProvider products */
    #[DataProvider('products')]
    public function test_full_automation_flow_per_product(string $slug, string $kind, string $access, bool $hasProfile): void
    {
        $this->liftRecipeGates();
        $this->approveAllCommands();
        config(['provisioning.live_enabled' => true, 'provisioning.worker' => 'ssh']);
        $transport = new FakeTransport;
        $this->app->instance(RemoteTransport::class, $transport);
        Queue::fake();

        // 1. Paid order creates the service with the correct recipe kind and access model.
        $service = $this->paidService($slug);
        $this->assertSame($kind, $service->kind, $slug);
        $this->assertSame($access, $service->plan->access_model, $slug);
        $this->assertSame('pending_provisioning', $service->status);
        $this->assertTrue(OrderStatus::isPaidState($service->order->status));

        // 2. Staff assign a correctly-sized server of the right access model.
        $admin = $this->staff();
        $server = $this->server(['access_model' => $access, 'ssh_host_fingerprint' => 'SHA256:testfingerprint']);
        $confirmed = $hasProfile ? null : ['vcpu' => 2, 'ram_gb' => 4, 'disk_gb' => 80];
        app(DeploymentService::class)->assign($service->fresh('plan.resourceProfile'), $server, $admin, $confirmed);
        $service->refresh();
        $this->assertSame($server->id, $service->server_id);
        if (! $hasProfile) {
            $this->assertSame(2, $service->config['confirmed_resources']['vcpu']);
            $this->assertSame($admin->id, $service->config['confirmed_resources']['confirmed_by']);
        }

        // Web apps need their address before a live install (HTTPS); staff set a DeveloperJet subdomain.
        if (config('provisioning.recipes.'.$kind.'.needs_domain')) {
            $service->refresh()->forceFill(['config' => array_merge($service->config ?? [], ['assigned_domain' => str_replace('_', '-', $kind).'.example.com'])])->save();
        }

        // 3. Dry run first: records the plan but NEVER runs anything or activates the service.
        $dry = app(DeploymentService::class)->deploy($service->fresh(), $admin, 'dry_run');
        $dry = app(DeploymentService::class)->execute($dry->fresh());
        $this->assertSame('dry_run_ok', $dry->status);
        $this->assertSame('pending_provisioning', $service->fresh()->status);
        $this->assertSame([], $transport->commands);

        // 4. Live deployment completes every recipe step and activates service + order.
        $live = app(DeploymentService::class)->deploy($service->fresh(), $admin, 'live');
        $live = app(DeploymentService::class)->execute($live->fresh());
        $this->assertSame('succeeded', $live->status, $slug.': '.($live->error_summary ?? ''));
        $this->assertSame('active', $service->fresh()->status);
        $this->assertSame(OrderStatus::ACTIVE, $service->order->fresh()->status);
        $this->assertContains('health_check', $live->completed_steps);
        $this->assertContains('security_baseline', $live->completed_steps);
        $this->assertFalse($transport->connected); // always disconnected in finally
        $this->assertSame('Ubuntu 24.04.5 LTS', $service->fresh()->server->os_name, 'OS name recorded for the customer page');

        foreach (config('provisioning.recipes.'.$kind.'.steps') as $step) {
            $this->assertContains($step, $live->completed_steps, "{$slug}: {$step}");
        }
        if (config('provisioning.recipes.'.$kind.'.needs_domain')) {
            $this->assertSame('https://'.str_replace('_', '-', $kind).'.example.com', $service->fresh()->url);
        }

        // 5. Customer timeline is Ready, polling stops, completion notifications and audit exist.
        $progress = DeploymentProgress::for($service->fresh()->load('order', 'deployments'));
        $this->assertTrue($progress['ready']);
        $this->assertFalse($progress['poll']);
        $this->assertDatabaseHas('notification_logs', ['event' => 'deployment_completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deployment.finished']);
    }

    public function test_profile_less_plan_requires_a_staff_confirmed_size(): void
    {
        $this->liftRecipeGates();
        $service = $this->paidService('n8n-launch');
        $admin = $this->staff();
        $server = $this->server(['access_model' => 'unmanaged']);

        // No catalogue profile and no staff-confirmed size -> assignment is refused; nothing is invented.
        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('no confirmed resource profile');
        app(DeploymentService::class)->assign($service, $server, $admin);
    }
}
