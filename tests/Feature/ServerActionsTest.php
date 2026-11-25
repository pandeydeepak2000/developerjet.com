<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Service;
use App\Services\DeploymentService;
use App\Services\Provisioning\RecipeRegistry;
use App\Services\Provisioning\RemoteTransport;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsFixtures;
use Tests\Concerns\FakeTransport;
use Tests\TestCase;

/** One-click server actions (reboot / restart / reset login / suspend / unsuspend / reinstall) over the fake SSH transport. */
class ServerActionsTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private FakeTransport $t;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        config(['provisioning.live_enabled' => true, 'provisioning.worker' => 'ssh']);
        $this->t = new FakeTransport;
        $this->app->instance(RemoteTransport::class, $this->t);
    }

    /** A delivered n8n service on a server with a saved password. */
    private function deliveredN8n(): Service
    {
        $user = $this->customer();
        app(WalletService::class)->credit($user, 5_000_000, 'admin_adjustment', 'adjust:'.uniqid(), 'Test funds');
        $plan = $this->approve('n8n-launch', 1000);
        $this->placeOrder($user, 'n8n-launch', 'tok-'.uniqid(), ['use_wallet' => 1, 'setup' => [$plan->id => ['domain_mode' => 'default']]]);
        $service = Service::where('user_id', $user->id)->latest('id')->firstOrFail();

        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.services.setup', $service), ['ip_address' => '203.0.113.120', 'ssh_password' => 'Root-Pass-ACT-1',
            'assigned_domain' => 'act1.developerjet.com', 'vcpu' => 2, 'ram_gb' => 4, 'disk_gb' => 40])->assertSessionHas('success');
        $this->post(route('admin.services.delivered', $service))->assertSessionHas('success');

        return $service->fresh();
    }

    public function test_admin_sees_actions_and_suspend_really_stops_the_app_then_unsuspend_starts_it(): void
    {
        $service = $this->deliveredN8n();
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get(route('admin.orders.show', $service->order_id))->assertOk()
            ->assertSee('Reboot server')->assertSee('Restart app')->assertSee('Reset app login')->assertSee('Suspend')
            ->assertSee('Reinstall fresh (delete everything)')->assertDontSee('Request suspension');

        $stop = app(RecipeRegistry::class)::script('n8n/stop');
        $this->post(route('admin.services.action', [$service, 'stop_app']))->assertSessionHas('success');
        $this->assertSame('suspended', $service->fresh()->status);
        $this->assertContains($stop, $this->t->commands);
        $this->assertDatabaseHas('audit_logs', ['action' => 'server.action']);

        // Customer sees it as suspended; app button and login box are hidden.
        $this->actingAs($service->user, 'web')->get(route('dashboard.services.show', $service))->assertOk()
            ->assertSee('This service is suspended.')->assertDontSee('id="access"', false);

        $this->actingAs($admin)->get(route('admin.orders.show', $service->order_id))->assertSee('Unsuspend');
        $this->post(route('admin.services.action', [$service, 'start_app']))->assertSessionHas('success');
        $this->assertSame('active', $service->fresh()->status);
    }

    public function test_customer_can_reboot_restart_and_reset_login_but_not_suspend(): void
    {
        $service = $this->deliveredN8n();
        $customer = $service->user;

        $this->actingAs($customer, 'web')->get(route('dashboard.services.show', $service))->assertOk()
            ->assertSee('Reboot server')->assertSee('Restart n8n')->assertSee('Forgot n8n password? Reset login');

        $this->post(route('dashboard.services.action', [$service, 'reset_login']))->assertSessionHas('success');
        $this->assertContains(RecipeRegistry::script('n8n/reset_login'), $this->t->commands);
        $this->post(route('dashboard.services.action', [$service, 'reboot']))->assertSessionHas('success');
        $this->assertContains(RecipeRegistry::script('common/reboot'), $this->t->commands);

        // Not offered to customers, and other customers can never act on it.
        $this->post('/dashboard/services/'.$service->id.'/action/stop_app')->assertNotFound();
        $this->actingAs($this->customer(), 'web')->post(route('dashboard.services.action', [$service, 'reboot']))->assertForbidden();
    }

    public function test_failed_action_is_reported_and_password_never_leaks(): void
    {
        $service = $this->deliveredN8n();
        $restart = RecipeRegistry::script('n8n/restart');
        $this->t->failCommands = [$restart];

        $this->actingAs($this->staff('admin'))->post(route('admin.services.action', [$service, 'restart_app']))->assertSessionHas('error');
        $this->assertSame('active', $service->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'server.action_failed']);
        $this->assertDatabaseMissing('audit_logs', ['meta' => 'Root-Pass-ACT-1']);

        // Billing staff cannot run server actions.
        $this->actingAs($this->staff('billing-staff'))->post(route('admin.services.action', [$service, 'reboot']))->assertForbidden();
    }

    public function test_reinstall_fresh_wipes_then_runs_a_full_new_install_without_resuming_old_steps(): void
    {
        Queue::fake();
        $service = $this->deliveredN8n();
        $admin = $this->staff('admin');

        // An older failed live attempt with "completed" steps must NOT be resumed after the wipe.
        Deployment::create(['service_id' => $service->id, 'server_id' => $service->server_id, 'order_id' => $service->order_id, 'requested_by' => $admin->id,
            'recipe' => 'n8n', 'recipe_version' => config('provisioning.recipes.n8n.version'), 'mode' => 'live', 'status' => 'failed', 'attempt' => 1,
            'completed_steps' => ['check_ssh', 'check_os', 'install_n8n'], 'idempotency_key' => 'deploy:old:1', 'created_at' => now()->subHour()]);

        $response = $this->actingAs($admin)->post(route('admin.services.reinstall', $service));
        $deployment = Deployment::latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.deployments.show', $deployment));
        $this->assertContains(RecipeRegistry::script('n8n/wipe'), $this->t->commands);
        $this->assertSame('pending_provisioning', $service->fresh()->status);
        $this->assertNull($deployment->completed_steps, 'A fresh install starts from the first step.');

        $done = app(DeploymentService::class)->execute($deployment->fresh());
        $this->assertSame('succeeded', $done->status, (string) $done->error_summary);
        $this->assertContains('install_n8n', $done->completed_steps);
        $this->assertSame('active', $service->fresh()->status);
        $this->assertSame('active', $service->order->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'service.reinstall_started']);
    }

    public function test_actions_are_hidden_while_automatic_install_is_off(): void
    {
        $service = $this->deliveredN8n();
        config(['provisioning.live_enabled' => false]);

        $this->actingAs($this->staff('admin'))->get(route('admin.orders.show', $service->order_id))->assertOk()
            ->assertDontSee('Reboot server')->assertSee('appear here when automatic install');
        $this->post(route('admin.services.action', [$service, 'reboot']))->assertNotFound();
    }
}
