<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Deployment;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Server;
use App\Models\Service;
use App\Services\DeploymentService;
use App\Services\Infrastructure\AdapterManager;
use App\Services\Infrastructure\AdapterUnavailableException;
use App\Services\Infrastructure\ControlPanelCapabilities;
use App\Services\Infrastructure\ProviderAdapter;
use App\Services\Infrastructure\ProxmoxAdapter;
use App\Services\OrderException;
use App\Services\Provisioning\RecipeRegistry;
use App\Services\Provisioning\RemoteTransport;
use App\Services\ServerMonitor;
use App\Services\WalletService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\Concerns\FakeTransport;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function paidService(string $slug = 'hermes-cli-launch'): Service
    {
        $this->approve($slug, 1000);
        $user = $this->customer();
        app(WalletService::class)->credit($user, 900000, 'topup', 'seed'.$slug);
        $this->placeOrder($user, $slug, 'tok'.$slug, ['use_wallet' => 1]);

        return Service::latest('id')->firstOrFail();
    }

    /** Gives every step a (test-only) fixed command so a full recipe can complete against the fake transport. */
    private function approveAllCommands(): void
    {
        $catalog = config('provisioning.step_catalog');
        foreach ($catalog as $id => $step) {
            $catalog[$id]['command'] = $step['command'] ?? 'noop '.$id;
        }
        config(['provisioning.step_catalog' => $catalog]);
    }

    private function liveSetup(FakeTransport $transport): array
    {
        config(['provisioning.live_enabled' => true, 'provisioning.worker' => 'ssh']);
        $this->app->instance(RemoteTransport::class, $transport);
        Queue::fake(); // we execute explicitly below
        $service = $this->paidService();
        $admin = $this->staff();
        $server = $this->server(['ssh_host_fingerprint' => 'SHA256:testfingerprint']);
        app(DeploymentService::class)->assign($service, $server, $admin);

        return [$service->fresh(), $admin, $server];
    }

    private function runDeploy(Service $service, $admin): Deployment
    {
        $deployment = app(DeploymentService::class)->deploy($service->fresh(), $admin, 'live');

        return app(DeploymentService::class)->execute($deployment->fresh());
    }

    // ---- catalogue ----------------------------------------------------------------------------------------

    public function test_new_families_exist_as_unpriced_unpurchasable_draft_slots(): void
    {
        foreach (['claude-code' => 'unmanaged', 'docker-vps' => 'unmanaged'] as $cat => $access) {
            $plans = Plan::whereHas('category', fn ($q) => $q->where('slug', $cat))->get();
            $this->assertCount(3, $plans);
            foreach ($plans as $plan) {
                $this->assertSame('pending', $plan->price_status);
                $this->assertFalse($plan->canBePurchased());
                $this->assertSame($access, $plan->access_model);
                $this->assertNull($plan->resource_profile_id);
                $this->assertCount(0, $plan->prices);
            }
        }
        // The original 13 approved-structure records are untouched.
        $this->assertSame(13, Plan::where('kind', 'hosting')->whereHas('category', fn ($q) => $q->whereIn('slug', ['hermes-cli', 'hermes-workspace', 'n8n', 'ai-complete-bundle', 'openclaw']))->count());
    }

    public function test_unapproved_new_plan_cannot_be_ordered(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->post('/cart', ['plan' => 'docker-vps-a', 'billing_interval' => 'monthly']);
        $this->withSession(['checkout_token' => 'x'])->post('/checkout', ['accept_terms' => 1]);

        $this->assertSame(0, Order::count());
    }

    public function test_providers_are_seeded_manual_and_unverified(): void
    {
        foreach (['desivps', 'erichost', 'contabo-core', 'contabo-performance-plus', 'server-broker'] as $slug) {
            $p = Provider::where('slug', $slug)->firstOrFail();
            $this->assertSame('manual', $p->provisioning_method);
            $this->assertSame('unverified', $p->api_status);
            $this->assertFalse($p->supports('power'));
        }
        $this->assertFalse(Provider::where('slug', 'proxmox-internal')->value('is_active'));
        $this->assertFalse(Provider::where('slug', 'ovhcloud-dedicated')->value('is_active'));
    }

    // ---- recipes ------------------------------------------------------------------------------------------

    public function test_recipes_are_valid_versioned_and_cover_all_targets(): void
    {
        $registry = app(RecipeRegistry::class);
        $this->assertSame([], $registry->validate());
        foreach (['hermes_cli', 'hermes_workspace', 'n8n', 'openclaw', 'claude_code', 'docker_vps', 'bundle'] as $kind) {
            $this->assertNotNull($registry->recipe($kind), $kind);
            $this->assertContains('security_baseline', $registry->steps($kind));
            $this->assertContains('health_check', $registry->steps($kind));
        }
        $this->assertSame('unmanaged', $registry->recipe('docker_vps')['access_model']);
        $this->assertFalse($registry->recipe('hermes_cli')['needs_domain']);
        $this->assertTrue($registry->recipe('hermes_workspace')['needs_domain']);
        $this->assertArrayNotHasKey('gated', $registry->recipe('n8n'), 'Owner decision: n8n is self-hosted on the customer VPS, no licence gate.');
        $this->assertSame('claude', $registry->recipe('claude_code')['customer_credentials']);
    }

    public function test_only_reviewed_commands_are_approved_and_every_script_exists(): void
    {
        $registry = app(RecipeRegistry::class);
        $approved = collect(array_keys(config('provisioning.step_catalog')))->filter(fn ($s) => $registry->approved($s))->values()->all();
        // Tested on Ubuntu 24.04 on 2026-11-25 (owner-approved): the shared steps + n8n. Others stay unapproved.
        $this->assertSame(['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies',
            'install_docker', 'open_web_ports', 'install_hermes_cli', 'install_n8n', 'install_claude_code', 'record_state'], $approved);
        $this->assertFalse($registry->approved('install_hermes_workspace'));
        $this->assertTrue($registry->approved('health_check', 'n8n'));
        $this->assertTrue($registry->approved('health_check', 'hermes_cli'));
        $this->assertFalse($registry->approved('health_check', 'hermes_workspace'));

        foreach (config('provisioning.recipes') as $kind => $recipe) {
            foreach ($recipe['steps'] as $step) {
                $cmd = $registry->command($step, $kind);
                if ($cmd !== null && str_starts_with($cmd, '@')) {
                    $this->assertFileExists(resource_path('provisioning/'.substr($cmd, 1).'.sh'), "{$kind}: {$step}");
                }
            }
        }
        $this->assertStringContainsString("D='client9.example.com'", $registry->render('install_n8n', 'client9.example.com', 'n8n'));
    }

    public function test_domain_placeholder_rejects_shell_injection(): void
    {
        config(['provisioning.step_catalog.configure_domain.command' => 'setup-domain {domain}']);
        $registry = app(RecipeRegistry::class);

        $this->assertSame("setup-domain 'app.example.com'", $registry->render('configure_domain', 'app.example.com'));
        foreach (['a.com; rm -rf /', 'a.com && id', '$(id).example.com', "x.com\nid", 'a b.com'] as $bad) {
            try {
                $registry->render('configure_domain', $bad);
                $this->fail("Accepted: {$bad}");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    // ---- live worker (fake transport; nothing real is contacted) ------------------------------------------

    public function test_default_ssh_transport_fails_safely_and_changes_nothing(): void
    {
        config(['provisioning.live_enabled' => true, 'provisioning.worker' => 'ssh']);
        Queue::fake();
        $service = $this->paidService();
        $admin = $this->staff();
        app(DeploymentService::class)->assign($service, $this->server(['ssh_host_fingerprint' => 'SHA256:x']), $admin);

        $deployment = $this->runDeploy($service, $admin);

        $this->assertSame('failed', $deployment->status);
        $this->assertSame('pending_provisioning', $service->fresh()->status);
        $this->assertSame('failed', $service->order->fresh()->status);
        $this->assertStringNotContainsString('SHA256', (string) $deployment->error_summary);
    }

    public function test_first_connection_pins_the_host_key_and_a_changed_key_later_aborts(): void
    {
        $this->approveAllCommands();
        $t = new FakeTransport;
        [$service, $admin, $server] = $this->liveSetup($t);
        $server->update(['ssh_host_fingerprint' => null]);

        $deployment = $this->runDeploy($service, $admin);
        $this->assertSame('succeeded', $deployment->status);
        $this->assertSame('SHA256:testfingerprint', $server->fresh()->ssh_host_fingerprint, 'Trust on first use: the key is saved.');
        $this->assertStringContainsString('identity saved', $deployment->logs()->pluck('message')->implode(' '));

        // Same server reinstalled / swapped (different key): the next run stops before any command.
        $t2 = new FakeTransport;
        $t2->fingerprint = 'SHA256:someotherkey';
        $this->app->instance(RemoteTransport::class, $t2);
        $service->refresh()->update(['status' => 'pending_provisioning']);
        $service->order->forceFill(['status' => 'awaiting_provisioning'])->save();
        $second = $this->runDeploy($service->fresh(), $admin);
        $this->assertSame('failed', $second->status);
        $this->assertSame([], $t2->commands);
    }

    public function test_host_key_mismatch_aborts_before_any_command(): void
    {
        $t = new FakeTransport;
        $t->hostKeyMismatch = true;
        [$service, $admin] = $this->liveSetup($t);

        $deployment = $this->runDeploy($service, $admin);

        $this->assertSame('failed', $deployment->status);
        $this->assertSame('check_ssh', $deployment->failed_step);
        $this->assertSame([], $t->commands);
    }

    public function test_unapproved_install_step_stops_the_run_after_preflight(): void
    {
        $t = new FakeTransport;
        [$service, $admin] = $this->liveSetup($t);
        $service->forceFill(['kind' => 'hermes_workspace', 'config' => array_merge($service->config ?? [], ['assigned_domain' => 'ws1.example.com'])])->save();

        $deployment = $this->runDeploy($service->fresh(), $admin);

        // Hermes Workspace: the shared steps are approved, its own install step is not yet → stops there, nothing guessed.
        $this->assertSame('failed', $deployment->status);
        $this->assertSame('install_hermes_workspace', $deployment->failed_step);
        $this->assertSame(['check_ssh', 'check_os', 'check_arch', 'check_resources', 'security_baseline', 'install_dependencies', 'install_docker'], $deployment->completed_steps);
        $this->assertSame(['echo ok', 'cat /etc/os-release', 'uname -m', 'df -Pk /'], array_slice($t->commands, 0, 4));
        $this->assertCount(7, $t->commands); // 4 probes + 3 reviewed scripts, nothing else
        $this->assertSame('pending_provisioning', $service->fresh()->status);
        $this->assertFalse($t->connected);
    }

    public static function badHosts(): array
    {
        return [
            'unsupported os' => ['os', "ID=centos\nVERSION_ID=\"7\"\n", 'check_os'],
            'unsupported arch' => ['arch', 'aarch64', 'check_arch'],
            'low disk' => ['df', "h\n/dev/x 100 90 1024 99% /", 'check_resources'],
        ];
    }

    /** @dataProvider badHosts */
    #[DataProvider('badHosts')]
    public function test_unsupported_hosts_fail_preflight(string $prop, string $value, string $step): void
    {
        $t = new FakeTransport;
        $t->{$prop} = $value;
        [$service, $admin] = $this->liveSetup($t);

        $deployment = $this->runDeploy($service, $admin);

        $this->assertSame('failed', $deployment->status);
        $this->assertSame($step, $deployment->failed_step);
        $this->assertSame('pending_provisioning', $service->fresh()->status);
    }

    public function test_full_recipe_completes_activates_service_and_order(): void
    {
        $this->approveAllCommands();
        $t = new FakeTransport;
        [$service, $admin] = $this->liveSetup($t);

        $deployment = $this->runDeploy($service, $admin);

        $this->assertSame('succeeded', $deployment->status);
        $this->assertSame('active', $service->fresh()->status);
        $this->assertSame('active', $service->order->fresh()->status);
        $this->assertContains('health_check', $deployment->fresh()->completed_steps);
        $this->assertFalse($t->connected);
    }

    public function test_retry_resumes_after_confirmed_steps_instead_of_repeating_them(): void
    {
        $this->approveAllCommands();
        $t = new FakeTransport;
        $installCmd = app(RecipeRegistry::class)->render('install_hermes_cli', null, 'hermes_cli');
        $baselineCmd = app(RecipeRegistry::class)->render('security_baseline', null, 'hermes_cli');
        $t->failCommands = [$installCmd];
        [$service, $admin] = $this->liveSetup($t);

        $first = $this->runDeploy($service, $admin);
        $this->assertSame('failed', $first->status);
        $this->assertSame('install_hermes_cli', $first->failed_step);
        $this->assertSame('pending_provisioning', $service->fresh()->status);

        // Admin retries after fixing the cause.
        $t->failCommands = [];
        $t->commands = [];
        app(DeploymentService::class)->prepareRetry($service->fresh(), $admin);
        $second = $this->runDeploy($service->fresh(), $admin);

        $this->assertSame('succeeded', $second->status);
        $this->assertSame(2, $second->attempt);
        $this->assertNotContains($baselineCmd, $t->commands); // already confirmed — not repeated
        $this->assertNotContains('cat /etc/os-release', $t->commands);
        $this->assertContains($installCmd, $t->commands);
        $this->assertSame('active', $service->fresh()->status);
    }

    public function test_web_app_without_a_usable_address_is_not_deployed_and_unverified_input_never_runs(): void
    {
        $this->approveAllCommands();
        config(['provisioning.step_catalog.configure_domain.command' => 'setup-domain {domain}']);
        $t = new FakeTransport;
        [$service, $admin] = $this->liveSetup($t);
        $service->update(['kind' => 'hermes_workspace', 'custom_domain' => 'evil.com; id', 'domain_verified' => false]);

        try {
            app(DeploymentService::class)->deploy($service->fresh(), $admin, 'live');
            $this->fail('A web app without a usable address must not deploy live.');
        } catch (OrderException $e) {
            $this->assertStringContainsString('app address', $e->getMessage());
        }
        $this->assertSame([], $t->commands);
        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_worker_streams_output_but_never_the_server_password(): void
    {
        $this->approveAllCommands();
        $t = new FakeTransport;
        [$service, $admin, $server] = $this->liveSetup($t);
        $server->update(['ssh_credential' => 'Root-Pass-XYZ-123']);
        $t->os = "ID=ubuntu\nVERSION_ID=\"24.04\"\nECHOED Root-Pass-XYZ-123\n";

        $deployment = $this->runDeploy($service, $admin);
        $text = $deployment->logs->pluck('message')->implode(' ');

        $this->assertStringContainsString('VERSION_ID', $text, 'Command output is shown in the live terminal.');
        $this->assertStringNotContainsString('Root-Pass-XYZ-123', $text);
        $this->assertStringContainsString('ECHOED ••••', $text);
    }

    // ---- assignment guard-rails ----------------------------------------------------------------------------

    public function test_access_model_mismatch_blocks_assignment(): void
    {
        $service = $this->paidService();
        $server = $this->server(['access_model' => 'managed']); // legacy restricted server vs self-hosted plan

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('managed access but the plan is unmanaged');
        app(DeploymentService::class)->assign($service, $server, $this->staff());
    }

    public function test_unsupported_architecture_blocks_assignment(): void
    {
        $service = $this->paidService();
        $this->expectExceptionMessage('architecture');
        app(DeploymentService::class)->assign($service, $this->server(['architecture' => 'aarch64']), $this->staff());
    }

    public function test_cost_limit_and_margin_guards_only_apply_when_configured(): void
    {
        $service = $this->paidService(); // approved at ₹1,000 / month
        $costly = $this->server(['purchase_cost_minor' => 80000, 'billing_period' => 'monthly']); // ₹800

        // Nothing configured -> never blocks and nothing is invented.
        app(DeploymentService::class)->assign($service, $costly, $this->staff());
        $this->assertSame($costly->id, $service->fresh()->server_id);
        app(DeploymentService::class)->assign($service->fresh(), $this->server(), $this->staff()); // switching servers is allowed

        Setting::set('infrastructure.max_monthly_cost_minor', '50000');
        $s2 = $this->paidService('hermes-cli-grow');
        try {
            app(DeploymentService::class)->assign($s2, $this->server(['purchase_cost_minor' => 80000]), $this->staff());
            $this->fail('cost limit not enforced');
        } catch (OrderException $e) {
            $this->assertStringContainsString('cost limit', $e->getMessage());
        }

        Setting::set('infrastructure.max_monthly_cost_minor', '');
        Setting::set('infrastructure.min_margin_percent', '30');
        $this->expectExceptionMessage('below the configured minimum');
        app(DeploymentService::class)->assign($s2, $this->server(['purchase_cost_minor' => 80000]), $this->staff());
    }

    public function test_yearly_supplier_cost_is_normalised_to_monthly(): void
    {
        $server = $this->server(['purchase_cost_minor' => 1200000, 'billing_period' => 'yearly']);
        $this->assertSame(100000, app(DeploymentService::class)->monthlyCost($server));
        $this->assertNull(app(DeploymentService::class)->monthlyCost($this->server()));
    }

    // ---- capabilities & adapters ---------------------------------------------------------------------------

    public function test_manual_supplier_controls_are_all_unavailable_with_a_reason(): void
    {
        $service = $this->paidService();
        $service->update(['status' => 'active', 'server_id' => $this->server(['provider_id' => Provider::where('slug', 'desivps')->value('id')])->id]);

        foreach (app(ControlPanelCapabilities::class)->for($service->fresh()) as $key => $c) {
            $this->assertFalse($c['available'], $key);
            $this->assertNotEmpty($c['reason'], $key);
        }
    }

    public function test_controls_unavailable_until_service_is_active(): void
    {
        $service = $this->paidService();
        $caps = app(ControlPanelCapabilities::class)->for($service);
        $this->assertSame('Available after your service is active.', $caps['power']['reason']);
    }

    public function test_verified_provider_capability_requires_adapter_support_too(): void
    {
        $provider = Provider::where('slug', 'desivps')->firstOrFail();
        $provider->update(['provisioning_method' => 'api', 'api_status' => 'verified', 'verified_capabilities' => ['power', 'snapshots']]);
        $service = $this->paidService();
        $service->update(['status' => 'active', 'server_id' => $this->server(['provider_id' => $provider->id])->id]);

        // Provider says yes, but no adapter implements it -> still unavailable (no fake buttons).
        $caps = app(ControlPanelCapabilities::class)->for($service->fresh());
        $this->assertFalse($caps['power']['available']);

        // A (test) adapter that really implements `power` makes exactly that control available.
        $this->app->instance(AdapterManager::class, new class extends AdapterManager
        {
            public function for(?Provider $provider): ProviderAdapter
            {
                return new class implements ProviderAdapter
                {
                    public function key(): string
                    {
                        return 'test';
                    }

                    public function enabled(): bool
                    {
                        return true;
                    }

                    public function capabilities(): array
                    {
                        return ['power'];
                    }

                    public function perform(Server $server, string $capability, array $arguments = []): array
                    {
                        return [];
                    }
                };
            }
        });
        $caps = app(ControlPanelCapabilities::class)->for($service->fresh());
        $this->assertTrue($caps['power']['available']);
        $this->assertFalse($caps['snapshots']['available']);
        $this->assertFalse($caps['web_console']['available']);
    }

    public function test_unmanaged_service_has_no_application_controls(): void
    {
        config(['infrastructure.app_controls_enabled' => true]);
        $service = $this->paidService();
        $service->update(['status' => 'active', 'server_id' => $this->server(['access_model' => 'unmanaged'])->id]);
        $caps = app(ControlPanelCapabilities::class)->for($service->fresh());
        $this->assertFalse($caps['app_restart']['available']);
        $this->assertStringContainsString('SSH', $caps['app_restart']['reason']);
    }

    public function test_proxmox_adapter_is_hard_disabled(): void
    {
        config(['infrastructure.proxmox.enabled' => true]); // even if someone flips the flag
        $adapter = app(ProxmoxAdapter::class);
        $this->assertFalse($adapter->enabled());
        $this->assertSame([], $adapter->capabilities());
        $this->assertContains('vm_migration', ProxmoxAdapter::PLANNED_OPERATIONS);
        $this->expectException(AdapterUnavailableException::class);
        $adapter->perform($this->server(), 'power');
    }

    // ---- notifications & monitoring ------------------------------------------------------------------------

    public function test_paid_hosting_order_raises_vps_assignment_required_once(): void
    {
        $service = $this->paidService();
        $this->assertDatabaseHas('notification_logs', ['event' => 'vps_assignment_required', 'dedupe_key' => 'telegram:vps_assignment_required:order:'.$service->order_id]);
        $this->assertSame(1, NotificationLog::where('event', 'vps_assignment_required')->count());
    }

    public function test_monitor_alerts_dedupe_and_renewals_alert_once_per_date(): void
    {
        $server = $this->server(['renewal_date' => now()->addDays(3)->toDateString()]);
        $monitor = app(ServerMonitor::class);

        $monitor->record($server, 'disk', 'fail', 'disk 96%');
        $monitor->record($server, 'disk', 'fail', 'disk 96%');
        $monitor->record($server, 'cpu', 'ok');
        $this->assertSame(1, NotificationLog::where('event', 'server_monitoring_alert')->count());
        $this->assertSame(3, $server->healthChecks()->count());

        $this->assertSame(1, $monitor->checkRenewals(7));
        $monitor->checkRenewals(7);
        $this->assertSame(1, NotificationLog::where('event', 'server_renewal_due')->count());
        $this->assertSame(0, $this->server(['renewal_date' => now()->addDays(60)->toDateString()])->id > 0 ? $monitor->checkRenewals(1) - 0 : 1);
    }

    public function test_test_notification_controls_are_permissioned_and_safe(): void
    {
        Mail::fake();
        $super = $this->staff();
        $this->actingAs($super)->post('/admin/settings/test-notification', ['channel' => 'telegram'])->assertSessionHas('error'); // not configured
        $this->post('/admin/settings/test-notification', ['channel' => 'email'])->assertSessionHas('success');
        Mail::assertQueued(TemplatedMail::class, fn ($m) => $m->hasTo($super->email));

        $this->actingAs($this->staff('sales-staff'))->post('/admin/settings/test-notification', ['channel' => 'email'])->assertForbidden();
    }

    // ---- admin & customer pages ----------------------------------------------------------------------------

    public function test_admin_infrastructure_page_and_resources_render_and_are_permissioned(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin)->get('/admin/infrastructure')->assertOk()->assertSee('Provider capability matrix')->assertSee('DesiVPS')->assertSee('Hybrid KVM');
        $this->get('/admin/r/providers')->assertOk()->assertSee('Contabo Core');
        $this->get('/admin/r/provider_offers')->assertOk();
        $this->get('/admin/r/providers/create')->assertOk();
        $this->get('/admin/r/provider_offers/create')->assertOk();

        $this->actingAs($this->staff('sales-staff'))->get('/admin/infrastructure')->assertForbidden();
        $this->get('/admin/r/providers')->assertForbidden();
    }

    public function test_admin_can_record_provider_offer_with_cost_separate_from_selling_price(): void
    {
        $admin = $this->staff();
        $provider = Provider::where('slug', 'erichost')->firstOrFail();
        $this->actingAs($admin)->post('/admin/r/provider_offers', [
            'provider_id' => $provider->id, 'label' => 'Test offer', 'billing_period' => 'monthly',
            'purchase_cost_minor' => '600', 'selling_price_minor' => '1000', 'status' => 'draft',
        ])->assertRedirect();

        $offer = $provider->offers()->firstOrFail();
        $this->assertSame(60000, $offer->purchase_cost_minor);
        $this->assertSame(100000, $offer->selling_price_minor);
        $this->assertSame(40.0, $offer->marginPercent());
    }

    public function test_suspension_request_is_logged_and_permissioned_and_suspends_nothing(): void
    {
        $server = $this->server(['status' => 'assigned']);
        $this->actingAs($this->staff('technical-support'))->post(route('admin.servers.suspend', $server), ['reason' => 'abuse'])->assertForbidden();

        $this->actingAs($this->staff('developer'))->post(route('admin.servers.suspend', $server), ['reason' => 'abuse'])->assertForbidden(); // lacks servers.edit
        $this->actingAs($this->staff())->post(route('admin.servers.suspend', $server), ['reason' => 'abuse'])->assertSessionHas('success');

        $this->assertNotNull($server->fresh()->suspension_requested_at);
        $this->assertSame('assigned', $server->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'server.suspension_requested']);
    }

    public function test_customer_panel_shows_honest_server_details_and_unavailable_controls(): void
    {
        $service = $this->paidService();
        $server = $this->server(['ip_address' => '203.0.113.50', 'provider_id' => Provider::where('slug', 'desivps')->value('id'), 'ssh_credential' => 'TOPSECRETKEY']);
        $service->update(['server_id' => $server->id, 'status' => 'active']);

        $this->actingAs($service->user)->get(route('dashboard.services.show', $service))
            ->assertOk()->assertSee('203.0.113.50')->assertSee('Self-hosted VPS · full root access')->assertSee('Web console')->assertSee('Unavailable')
            // Owner decision (2026-11-25): the customer gets root, so the password is shown — only in the masked field.
            ->assertSee('type="password" value="TOPSECRETKEY"', false)->assertDontSee('DesiVPS')->assertDontSee('Awaiting VPS assignment');

        // Customer isolation.
        $this->actingAs($this->customer())->get(route('dashboard.services.show', $service))->assertForbidden();
    }

    public function test_customer_panel_before_assignment_says_awaiting_vps_assignment_and_hides_ip(): void
    {
        $service = $this->paidService();
        $this->actingAs($service->user)->get(route('dashboard.services.show', $service))->assertOk()->assertSee('Awaiting VPS assignment')->assertDontSee('Active — setup complete');
        $this->actingAs($service->user)->get(route('dashboard.orders.show', $service->order))->assertOk()->assertSee('Awaiting VPS assignment');
    }
}
