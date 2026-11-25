<?php

namespace Tests\Feature;

use App\Jobs\RunDeployment;
use App\Mail\TemplatedMail;
use App\Models\Deployment;
use App\Models\Plan;
use App\Models\ResourceProfile;
use App\Models\Server;
use App\Models\Service;
use App\Services\DeploymentService;
use App\Services\Notifier;
use App\Services\OrderException;
use App\Services\OrderService;
use App\Services\Provisioning\AutomaticProvisioner;
use App\Services\Provisioning\PlacementPlanner;
use App\Services\Provisioning\ProvisioningWorker;
use App\Services\Provisioning\WorkerResult;
use App\Services\WalletService;
use App\Support\OrderStatus;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    /** A paid order for a Hermes CLI plan whose resource profile is 2 vCPU / 4 GB / 80 GB. */
    private function paidService(string $slug = 'hermes-cli-launch'): Service
    {
        $this->approve($slug, 1000);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 900000, 'topup', 'seed'.$slug);
        $this->placeOrder($user, $slug, 'tok'.$slug, ['use_wallet' => 1]);

        return Service::latest('id')->firstOrFail();
    }

    private function fakeWorker(bool $success): void
    {
        config(['provisioning.live_enabled' => true]);
        $this->app->bind(ProvisioningWorker::class, fn () => null);
        $worker = new class($success) implements ProvisioningWorker
        {
            public function __construct(private bool $ok) {}

            public function isLive(): bool
            {
                return true;
            }

            public function run(Deployment $deployment, array $steps): WorkerResult
            {
                return new WorkerResult($this->ok, [['step' => 'install', 'level' => $this->ok ? 'info' : 'error', 'message' => $this->ok ? 'done' : 'boom']], $this->ok ? null : 'Install failed safely.', true);
            }
        };
        $this->app->instance('test.worker', $worker);
        $this->app->bind(DeploymentService::class, fn ($app) => new class($app->make(PlacementPlanner::class), $app->make(OrderService::class), $app->make(Notifier::class), $worker) extends DeploymentService
        {
            public function __construct($p, $o, $n, private $w)
            {
                parent::__construct($p, $o, $n);
            }

            public function worker(): ProvisioningWorker
            {
                return $this->w;
            }
        });
    }

    public function test_paid_order_creates_service_awaiting_provisioning(): void
    {
        $service = $this->paidService();

        $this->assertSame('pending_provisioning', $service->status);
        $this->assertSame(OrderStatus::AWAITING_PROVISIONING, $service->order->status);
        $this->assertNull($service->server_id);
    }

    public function test_assignment_validates_compatibility_and_resources(): void
    {
        $service = $this->paidService();
        $deployments = app(DeploymentService::class);
        $admin = $this->staff('super-admin');
        $small = $this->server(['label' => 'small', 'cpu_capacity' => 1, 'ram_capacity_gb' => 2, 'storage_capacity_gb' => 40]);
        $maint = $this->server(['label' => 'maint', 'status' => 'maintenance']);
        $shared = $this->server(['label' => 'shared', 'role' => 'shared']);

        foreach ([$small, $maint, $shared] as $bad) {
            try {
                $deployments->assign($service, $bad, $admin);
                $this->fail("{$bad->label} should be rejected");
            } catch (OrderException) {
                $this->assertNull($service->fresh()->server_id);
            }
        }

        $good = $this->server(['label' => 'good']);
        $deployments->assign($service, $good, $admin);
        $this->assertSame($good->id, $service->fresh()->server_id);
        $this->assertSame('assigned', $good->fresh()->status);

        // A second order cannot take the same server.
        $other = $this->paidService('hermes-cli-grow');
        $this->expectException(OrderException::class);
        $deployments->assign($other, $good->fresh(), $admin);
    }

    public function test_plan_without_confirmed_resources_cannot_be_assigned(): void
    {
        $this->approve('hermes-workspace-launch');
        Plan::where('slug', 'hermes-workspace-launch')->update(['resource_profile_id' => null]);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 900000, 'topup', 's');
        $this->placeOrder($user, 'hermes-workspace-launch', 't', ['use_wallet' => 1]);

        $this->expectExceptionMessage('no confirmed resource profile');
        app(DeploymentService::class)->assign(Service::firstOrFail(), $this->server(), $this->staff());
    }

    public function test_dry_run_records_steps_but_never_activates_service_or_changes_order(): void
    {
        $service = $this->paidService();
        $admin = $this->staff('developer');
        $server = $this->server();
        app(DeploymentService::class)->assign($service, $server, $this->staff('super-admin'));

        $this->actingAs($admin)->post(route('admin.services.deploy', $service), ['mode' => 'dry_run'])->assertRedirect();

        $deployment = Deployment::firstOrFail();
        $this->assertSame('dry_run_ok', $deployment->status);
        $this->assertGreaterThan(5, $deployment->logs()->count());
        $this->assertStringContainsString('[dry run]', $deployment->logs()->first()->message);
        $this->assertSame('pending_provisioning', $service->fresh()->status);
        $this->assertSame(OrderStatus::AWAITING_PROVISIONING, $service->order->fresh()->status);
        $this->assertSame('simulated', $server->healthChecks()->first()->status);
    }

    public function test_live_deploy_runs_as_a_test_run_while_live_provisioning_is_disabled(): void
    {
        $service = $this->paidService();
        app(DeploymentService::class)->assign($service, $this->server(), $this->staff());
        $admin = $this->staff('developer');

        $response = $this->actingAs($admin)->post(route('admin.services.deploy', $service), ['mode' => 'live']);
        $deployment = Deployment::firstOrFail();
        $response->assertRedirect(route('admin.deployments.show', $deployment))->assertSessionHas('success');
        $this->assertSame('dry_run', $deployment->mode, 'Nothing live may run while live provisioning is off.');
        $this->assertNotSame('queued', $deployment->fresh()->status, 'The test run finishes immediately, without a worker.');
        $this->assertSame('pending_provisioning', $service->fresh()->status);

        // The terminal page and its feed show the steps; nothing secret is in them.
        $this->get(route('admin.deployments.show', $deployment))->assertOk()->assertSee('Test run')->assertSee('[dry run]');
        $this->getJson(route('admin.deployments.log', $deployment))->assertOk()->assertJsonPath('finished', true)->assertJsonStructure(['status', 'lines' => [['id', 'time', 'level', 'step', 'message']]]);
        $this->actingAs($this->staff('billing-staff'))->getJson(route('admin.deployments.log', $deployment))->assertForbidden();
    }

    public function test_deploy_requires_deploy_permission_and_assignment_requires_assign_permission(): void
    {
        $service = $this->paidService();
        $server = $this->server();
        $support = $this->staff('technical-support');

        $this->actingAs($support)->post(route('admin.services.assign', $service), ['server_id' => $server->id])->assertForbidden();
        $this->post(route('admin.services.deploy', $service), ['mode' => 'dry_run'])->assertForbidden();
    }

    public function test_live_success_activates_service_and_order_with_notifications(): void
    {
        $this->fakeWorker(true);
        $service = $this->paidService();
        $admin = $this->staff();
        app(DeploymentService::class)->assign($service, $this->server(), $admin);
        Mail::fake();

        $deployment = app(DeploymentService::class)->deploy($service->fresh(), $admin, 'live');

        $this->assertSame('succeeded', $deployment->fresh()->status);
        $this->assertSame('active', $service->fresh()->status);
        $this->assertSame(OrderStatus::ACTIVE, $service->order->fresh()->status);
        Mail::assertQueued(TemplatedMail::class, fn ($m) => $m->template === 'deployment_completed');
        $this->assertDatabaseHas('notification_logs', ['event' => 'deployment_completed', 'channel' => 'mail']);
    }

    public function test_live_failure_keeps_service_inactive_and_allows_safe_retry(): void
    {
        $this->fakeWorker(false);
        $service = $this->paidService();
        $admin = $this->staff();
        app(DeploymentService::class)->assign($service, $this->server(), $admin);

        $first = app(DeploymentService::class)->deploy($service->fresh(), $admin, 'live');

        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame('Install failed safely.', $first->fresh()->error_summary);
        $this->assertSame('pending_provisioning', $service->fresh()->status);
        $this->assertSame(OrderStatus::FAILED, $service->order->fresh()->status);

        // Retry: order returns to awaiting provisioning, a NEW attempt is created.
        $this->actingAs($admin)->post(route('admin.services.deploy', $service), ['mode' => 'live']);
        $this->assertSame(2, Deployment::count());
        $this->assertSame(2, Deployment::max('attempt'));
        $this->assertSame(OrderStatus::FAILED, $service->order->fresh()->status); // still failing worker
        $this->assertSame('pending_provisioning', $service->fresh()->status);
    }

    public function test_deploy_is_idempotent_while_one_is_in_flight(): void
    {
        $service = $this->paidService();
        $admin = $this->staff();
        app(DeploymentService::class)->assign($service, $this->server(), $admin);
        Queue::fake();

        $a = app(DeploymentService::class)->deploy($service->fresh(), $admin, 'dry_run');
        $b = app(DeploymentService::class)->deploy($service->fresh(), $admin, 'dry_run');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Deployment::count());
        Queue::assertPushed(RunDeployment::class, 1);
    }

    public function test_automatic_mode_cannot_bypass_safety_checks(): void
    {
        $service = $this->paidService();
        $admin = $this->staff();
        app(DeploymentService::class)->assign($service, $this->server(), $admin);

        $this->actingAs($admin)->post(route('admin.provisioning.mode'), ['mode' => 'automatic'])->assertSessionHas('success');
        $this->assertSame('automatic', Setting::get('provisioning.mode'));
        $this->post(route('admin.services.deploy', $service), ['mode' => 'dry_run'])->assertSessionHas('error');
        $this->assertDatabaseCount('deployments', 0);

        $this->expectException(\RuntimeException::class);
        app(AutomaticProvisioner::class)->allocate($service->order);
    }

    public function test_n8n_deploys_without_the_old_licence_gate(): void
    {
        $this->approve('n8n-launch');
        Plan::where('slug', 'n8n-launch')->update(['resource_profile_id' => ResourceProfile::where('slug', 'displayed-2c-4g')->value('id')]);
        Setting::set('n8n.resale_verified', '1'); // allow purchase...
        $user = $this->customer();
        app(WalletService::class)->credit($user, 900000, 'topup', 's');
        $this->placeOrder($user, 'n8n-launch', 't', ['use_wallet' => 1]);
        $service = Service::firstOrFail();
        app(DeploymentService::class)->assign($service, $this->server(), $this->staff());
        Setting::set('n8n.resale_verified', '0'); // the old licence setting no longer blocks anything

        $deployment = app(DeploymentService::class)->deploy($service->fresh(), $this->staff(), 'dry_run');
        $this->assertSame('dry_run', $deployment->mode);
    }

    public function test_server_credentials_are_encrypted_hidden_and_never_shown(): void
    {
        $admin = $this->staff('super-admin');
        $this->actingAs($admin)->post('/admin/r/servers', [
            'label' => 'vps-x', 'ip_address' => '203.0.113.9', 'ssh_port' => 22, 'ssh_user' => 'root', 'role' => 'customer_vps',
            'cpu_capacity' => 4, 'ram_capacity_gb' => 8, 'storage_capacity_gb' => 160, 'status' => 'available', 'ssh_credential' => 'PRIVATE-KEY-MATERIAL', 'access_model' => 'managed', 'architecture' => 'x86_64', 'billing_period' => 'monthly',
        ])->assertRedirect();

        $server = Server::firstOrFail();
        $this->assertStringNotContainsString('PRIVATE-KEY-MATERIAL', $server->getRawOriginal('ssh_credential'));
        $this->assertSame('PRIVATE-KEY-MATERIAL', $server->ssh_credential);
        $this->assertArrayNotHasKey('ssh_credential', $server->toArray());
        $this->get('/admin/r/servers')->assertDontSee('PRIVATE-KEY-MATERIAL');
        $this->get('/admin/r/servers/'.$server->id.'/edit')->assertDontSee('PRIVATE-KEY-MATERIAL')->assertSee('stored');

        // Blank credential on edit keeps the stored value.
        $this->put('/admin/r/servers/'.$server->id, ['label' => 'vps-x', 'ip_address' => '203.0.113.9', 'ssh_port' => 22, 'ssh_user' => 'root', 'role' => 'customer_vps', 'cpu_capacity' => 4, 'ram_capacity_gb' => 8, 'storage_capacity_gb' => 160, 'status' => 'available', 'ssh_credential' => '', 'access_model' => 'managed', 'architecture' => 'x86_64', 'billing_period' => 'monthly'])->assertRedirect();
        $this->assertSame('PRIVATE-KEY-MATERIAL', $server->fresh()->ssh_credential);
    }

    public function test_placement_planner_picks_smallest_sufficient_server(): void
    {
        $profile = ResourceProfile::where('slug', 'displayed-4c-8g')->firstOrFail();
        $big = $this->server(['label' => 'big', 'cpu_capacity' => 16, 'ram_capacity_gb' => 64, 'storage_capacity_gb' => 1000]);
        $fit = $this->server(['label' => 'fit', 'cpu_capacity' => 4, 'ram_capacity_gb' => 8, 'storage_capacity_gb' => 160]);
        $tiny = $this->server(['label' => 'tiny', 'cpu_capacity' => 2, 'ram_capacity_gb' => 4, 'storage_capacity_gb' => 80]);

        $pick = app(PlacementPlanner::class)->pick(collect([$big, $fit, $tiny]), $profile);
        $this->assertSame('fit', $pick->label);
        $this->assertNull(app(PlacementPlanner::class)->pick(collect([$tiny]), $profile));
    }

    public function test_customer_service_panel_isolates_data_and_hides_ai_key(): void
    {
        $service = $this->paidService();
        $owner = $service->user;
        $this->actingAs($owner)->post(route('dashboard.services.ai', $service), ['ai_provider' => 'openai', 'ai_api_key' => 'sk-VERY-SECRET-KEY'])->assertSessionHas('success');

        $this->assertStringNotContainsString('sk-VERY-SECRET-KEY', $service->fresh()->getRawOriginal('ai_api_key'));
        $this->get(route('dashboard.services.show', $service))->assertOk()->assertDontSee('sk-VERY-SECRET-KEY')->assertSee('Add your AI API key')->assertSee('Usage metrics');
        $this->assertArrayNotHasKey('ai_api_key', $service->fresh()->toArray());

        $this->actingAs($this->customer())->get(route('dashboard.services.show', $service))->assertForbidden();
        $this->post(route('dashboard.services.ai', $service), ['ai_provider' => 'openai', 'ai_api_key' => 'sk-12345678'])->assertForbidden();
    }

    public function test_skipping_ai_config_marks_configuration_incomplete(): void
    {
        $service = $this->paidService();
        $this->actingAs($service->user)->post(route('dashboard.services.ai', $service), ['skip' => 1]);

        $this->get(route('dashboard.services.show', $service))->assertSee('Once your server is delivered');
        $this->assertFalse($service->fresh()->aiConfigured());
    }

    public function test_custom_domain_validation_rejects_injection(): void
    {
        $this->approve('hermes-workspace-launch');
        $user = $this->customer();
        app(WalletService::class)->credit($user, 900000, 'topup', 's');
        $this->placeOrder($user, 'hermes-workspace-launch', 't', ['use_wallet' => 1]);
        $service = Service::firstOrFail();

        $this->post(route('dashboard.services.domain', $service), ['custom_domain' => 'example.com; rm -rf /'])->assertSessionHasErrors('custom_domain');
        $this->post(route('dashboard.services.domain', $service), ['custom_domain' => 'app.example.com'])->assertSessionHas('success');
        $this->assertFalse($service->fresh()->domain_verified);
        $this->post(route('dashboard.services.domain', $this->paidService('hermes-cli-launch')), ['custom_domain' => 'a.example.com'])->assertNotFound(); // CLI plans have no domain
    }
}
