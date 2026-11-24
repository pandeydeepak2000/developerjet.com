<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\Service;
use App\Services\WalletService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * One-step setup on the order page: staff enter the VPS IP, username and root password (plus the app address for
 * n8n / Hermes Workspace) and the server is created, sized from the plan and assigned — ready for Deploy.
 */
class QuickServerSetupTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const PASSWORD = 'Root-Pass-FAKE-98765';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function paidService(string $slug): Service
    {
        $user = $this->customer();
        app(WalletService::class)->credit($user, 5_000_000, 'admin_adjustment', 'adjust:'.uniqid(), 'Test funds');
        $plan = $this->approve($slug, 1000);
        $this->placeOrder($user, $slug, 'tok-'.uniqid(), ['use_wallet' => 1, 'setup' => [$plan->id => ['domain_mode' => 'default']]]);

        return Service::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_hermes_cli_needs_only_ip_and_password_and_is_sized_from_the_plan(): void
    {
        $service = $this->paidService('hermes-cli-launch');
        $profile = $service->plan->resourceProfile;
        $this->assertNotNull($profile);

        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.orders.show', $service->order_id))->assertOk()
            ->assertSee('VPS details')->assertSee('Root password')->assertDontSee('App address (subdomain)');

        $this->post(route('admin.services.setup', $service), ['ip_address' => '203.0.113.10'])->assertSessionHasErrors('ssh_password');
        $this->post(route('admin.services.setup', $service), ['ip_address' => 'not-an-ip', 'ssh_password' => self::PASSWORD])->assertSessionHasErrors('ip_address');

        $this->post(route('admin.services.setup', $service), ['ip_address' => '203.0.113.10', 'ssh_password' => self::PASSWORD])
            ->assertSessionHas('success');

        $service->refresh();
        $server = Server::findOrFail($service->server_id);
        $this->assertSame('203.0.113.10', $server->ip_address);
        $this->assertSame('root', $server->ssh_user);
        $this->assertSame(22, (int) $server->ssh_port);
        $this->assertSame('assigned', $server->status);
        $this->assertSame($service->user_id, $server->assigned_user_id);
        $this->assertSame((int) $profile->vcpu, (int) $server->cpu_capacity);
        $this->assertSame((int) $profile->ram_gb, (int) $server->ram_capacity_gb);
        $this->assertSame('unmanaged', $server->access_model);

        // Password is encrypted at rest, never logged, never shown back.
        $this->assertSame(self::PASSWORD, $server->ssh_credential);
        $this->assertStringNotContainsString(self::PASSWORD, (string) $server->getRawOriginal('ssh_credential'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'server.created_from_order']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'server.assigned']);
        $page = $this->get(route('admin.orders.show', $service->order_id))->assertOk()
            ->assertSee('password/key saved')->assertSee('Deploy')->assertSee('saved — leave blank to keep');
        $this->assertStringNotContainsString(self::PASSWORD, $page->getContent());
    }

    public function test_n8n_asks_for_the_app_address_and_plan_size_then_updates_keep_the_password(): void
    {
        Setting::set('n8n.resale_verified', '1');
        $service = $this->paidService('n8n-launch');
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get(route('admin.orders.show', $service->order_id))->assertOk()->assertSee('App address (subdomain)');

        $base = ['ip_address' => '203.0.113.20', 'ssh_password' => self::PASSWORD, 'vcpu' => 2, 'ram_gb' => 4, 'disk_gb' => 80];
        $this->post(route('admin.services.setup', $service), $base)->assertSessionHasErrors('assigned_domain');
        $this->post(route('admin.services.setup', $service), $base + ['assigned_domain' => 'Client7.DeveloperJet.com'])->assertSessionHas('success');

        $service->refresh();
        $this->assertSame('client7.developerjet.com', $service->appDomain());
        $this->assertSame(2, $service->config['confirmed_resources']['vcpu']);
        $server = $service->server;

        // Update without a password keeps the stored one; a new IP clears the old pinned host key.
        $server->forceFill(['ssh_host_fingerprint' => 'SHA256:old'])->save();
        $this->post(route('admin.services.setup', $service), ['ip_address' => '203.0.113.21', 'assigned_domain' => 'client7.developerjet.com', 'vcpu' => 2, 'ram_gb' => 4, 'disk_gb' => 80])
            ->assertSessionHas('success');
        $server->refresh();
        $this->assertSame('203.0.113.21', $server->ip_address);
        $this->assertSame(self::PASSWORD, $server->ssh_credential);
        $this->assertNull($server->ssh_host_fingerprint);
        $this->assertSame(1, Server::count());
    }

    public function test_an_ip_already_used_by_another_customer_is_refused(): void
    {
        $this->server(['ip_address' => '203.0.113.30', 'status' => 'assigned', 'assigned_user_id' => $this->customer()->id]);
        $service = $this->paidService('hermes-cli-launch');

        $this->actingAs($this->staff('admin'))
            ->post(route('admin.services.setup', $service), ['ip_address' => '203.0.113.30', 'ssh_password' => self::PASSWORD])
            ->assertSessionHasErrors('ip_address');
        $this->assertNull($service->fresh()->server_id);
    }

    public function test_staff_without_server_rights_cannot_use_setup(): void
    {
        $service = $this->paidService('hermes-cli-launch');

        $this->actingAs($this->staff('billing-staff'))
            ->post(route('admin.services.setup', $service), ['ip_address' => '203.0.113.40', 'ssh_password' => self::PASSWORD])
            ->assertForbidden();
        $this->assertSame(0, Server::count());
    }

    public function test_server_form_shows_essentials_first_with_sensible_defaults(): void
    {
        $this->actingAs($this->staff('admin'))->get(route('admin.resource.create', 'servers'))->assertOk()
            ->assertSee('More details (optional)')->assertSee('Root password or SSH key')
            ->assertSee('name="ssh_port" value="22"', false)->assertSee('name="ssh_user" value="root"', false);
    }
}
