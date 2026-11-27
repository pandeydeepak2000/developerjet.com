<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Provisioning\RecipeRegistry;
use App\Services\Provisioning\RemoteTransport;
use App\Services\Provisioning\ServerStats;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsFixtures;
use Tests\Concerns\FakeTransport;
use Tests\TestCase;

/** Customer VPS panel: live usage over SSH, root password reset and customer "reinstall fresh". */
class CustomerVpsPanelTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private const PASSWORD = 'Root-Pass-VPS-1';

    private const STATS = "cpu_permille=125\ncpu_cores=2\nload=0.31 0.20 0.10\nmem_total_kb=4000000\nmem_used_kb=1000000\nswap_total_kb=0\nswap_used_kb=0\n"
        ."disk_total_kb=50000000\ndisk_used_kb=10000000\nuptime_s=93784\nnet_rx_bytes=6788436672\nnet_tx_bytes=907048974\nnet_rx_rate=352\nnet_tx_rate=740\n"
        ."os=Ubuntu 24.04.5 LTS\nhostname=vps1.example.test\n<script>=x\nevil_key=1";

    private FakeTransport $t;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        config(['provisioning.live_enabled' => true, 'provisioning.worker' => 'ssh']);
        $this->t = new FakeTransport;
        $this->t->outputs[RecipeRegistry::script('common/stats')] = self::STATS;
        $this->app->instance(RemoteTransport::class, $this->t);
        Cache::flush();
    }

    private function delivered(): Service
    {
        $user = $this->customer();
        app(WalletService::class)->credit($user, 5_000_000, 'admin_adjustment', 'adjust:'.uniqid(), 'Test funds');
        $plan = $this->approve('n8n-launch', 1000);
        $this->placeOrder($user, 'n8n-launch', 'tok-'.uniqid(), ['use_wallet' => 1, 'setup' => [$plan->id => ['domain_mode' => 'default']]]);
        $service = Service::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->actingAs($this->staff('admin'))->post(route('admin.services.setup', $service), ['ip_address' => '203.0.113.140', 'ssh_password' => self::PASSWORD,
            'assigned_domain' => 'vps1.developerjet.com', 'vcpu' => 2, 'ram_gb' => 4, 'disk_gb' => 40])->assertSessionHas('success');
        $this->post(route('admin.services.delivered', $service))->assertSessionHas('success');

        return $service->fresh();
    }

    public function test_panel_shows_tabs_live_usage_box_and_manage_actions(): void
    {
        $service = $this->delivered();

        $this->actingAs($service->user, 'web')->get(route('dashboard.services.show', $service))->assertOk()
            ->assertSeeInOrder(['Overview', 'Login &amp; SSH', 'Manage', 'Billing'], false)
            ->assertSee('Live server usage')->assertSee(route('dashboard.services.stats', $service))
            ->assertSee('Generate a strong password for me')->assertSee('Delete everything and reinstall')
            ->assertSee('Reboot server')->assertSee('Server details');
    }

    public function test_stats_endpoint_returns_parsed_real_readings_and_is_cached(): void
    {
        $service = $this->delivered();
        $stats = RecipeRegistry::script('common/stats');

        $json = $this->actingAs($service->user, 'web')->getJson(route('dashboard.services.stats', $service))->assertOk()
            ->assertJsonPath('ok', true)->assertJsonPath('cpu.percent', 12.5)->assertJsonPath('cpu.cores', 2)
            ->assertJsonPath('mem.total', 4000000 * 1024)->assertJsonPath('disk.used', 10000000 * 1024)
            ->assertJsonPath('net.rx_rate', 352)->assertJsonPath('uptime', 93784)->assertJsonPath('hostname', 'vps1.example.test')
            ->json();
        $this->assertArrayNotHasKey('evil_key', $json);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode($json));

        // A second request within the minute is served from cache: no second SSH call.
        $this->getJson(route('dashboard.services.stats', $service))->assertOk();
        $this->assertSame(1, collect($this->t->commands)->filter(fn ($c) => $c === $stats)->count());

        // Owner only.
        $this->actingAs($this->customer(), 'web')->getJson(route('dashboard.services.stats', $service))->assertForbidden();
    }

    public function test_unreachable_server_gives_a_friendly_message(): void
    {
        $service = $this->delivered();
        $this->t->failConnectAfterConnect = true;

        $this->actingAs($service->user, 'web')->getJson(route('dashboard.services.stats', $service))->assertOk()
            ->assertJsonPath('ok', false)->assertJsonMissingPath('cpu');
        $this->assertStringContainsString('could not reach your server', $this->getJson(route('dashboard.services.stats', $service))->json('message'));
    }

    public function test_stats_are_not_offered_while_live_install_is_off(): void
    {
        $service = $this->delivered();
        config(['provisioning.live_enabled' => false]);

        $this->actingAs($service->user, 'web')->get(route('dashboard.services.show', $service))->assertOk()->assertDontSee('Live server usage')
            ->assertDontSee('Generate a strong password for me');
        $this->getJson(route('dashboard.services.stats', $service))->assertNotFound();
    }

    public function test_customer_resets_root_password_new_one_is_saved_encrypted_and_never_logged(): void
    {
        $service = $this->delivered();
        $server = $service->server;

        $this->actingAs($service->user, 'web')->post(route('dashboard.services.root-password', $service))
            ->assertRedirect(route('dashboard.services.show', $service).'#access')->assertSessionHas('success');

        $new = (string) $server->fresh()->ssh_credential;
        $this->assertNotSame(self::PASSWORD, $new);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{20}$/', $new);
        $this->assertStringNotContainsString($new, (string) $server->fresh()->getRawOriginal('ssh_credential'));
        $this->assertTrue(collect($this->t->commands)->contains(fn ($c) => str_contains($c, "'".base64_encode($new)."' | base64 -d") && str_contains($c, 'chpasswd')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'server.root_password_reset']);
        $this->assertDatabaseMissing('audit_logs', ['meta' => $new]);

        // The panel shows the new password (masked until Show).
        $this->get(route('dashboard.services.show', $service))->assertSee('type="password" value="'.$new.'"', false);

        // Other customers cannot reset it.
        $this->actingAs($this->customer(), 'web')->post(route('dashboard.services.root-password', $service))->assertForbidden();
    }

    public function test_customer_sets_their_own_root_password_safely(): void
    {
        $service = $this->delivered();
        $mine = 'My$ecret\'Pass"; rm -rf / 42';
        $mine = str_replace(' ', '', $mine); // spaces are not allowed: My$ecret'Pass";rm-rf/42

        $this->actingAs($service->user, 'web')->get(route('dashboard.services.show', $service))->assertOk()
            ->assertSee('Generate a strong password for me')->assertSee('Generate a strong password for me');

        // Validation: too short, mismatch, spaces, no digits — nothing is sent to the server.
        foreach ([['short1A', 'short1A'], ['LongEnough12345', 'Different12345'], ['has space 12345', 'has space 12345'], ['NoDigitsHereAtAll', 'NoDigitsHereAtAll']] as [$a, $b]) {
            $this->post(route('dashboard.services.root-password', $service), ['mode' => 'custom', 'root_password' => $a, 'root_password_confirmation' => $b])
                ->assertStatus(302)->assertSessionHasErrorsIn('rootPassword', 'root_password');
        }
        $this->assertFalse(collect($this->t->commands)->contains(fn ($c) => str_contains($c, 'chpasswd')));

        $this->post(route('dashboard.services.root-password', $service), ['mode' => 'custom', 'root_password' => $mine, 'root_password_confirmation' => $mine])
            ->assertSessionHas('success', 'Root password changed. Use your new password for SSH from now on.');

        $this->assertSame($mine, (string) $service->server->fresh()->ssh_credential);
        $cmd = collect($this->t->commands)->first(fn ($c) => str_contains($c, 'chpasswd'));
        $this->assertStringContainsString(base64_encode($mine), $cmd);
        $this->assertStringNotContainsString($mine, $cmd, 'The typed password never appears raw in the shell line.');
        $this->assertDatabaseMissing('audit_logs', ['meta' => $mine]);
    }

    public function test_usage_polling_never_uses_up_the_password_change_allowance(): void
    {
        $service = $this->delivered();
        $this->actingAs($service->user, 'web');
        for ($i = 0; $i < 12; $i++) {
            $this->getJson(route('dashboard.services.stats', $service))->assertOk();
        }

        $this->post(route('dashboard.services.root-password', $service))->assertSessionHas('success');
    }

    public function test_failed_root_password_reset_keeps_the_old_password(): void
    {
        $service = $this->delivered();
        $this->t->failConnectAfterConnect = true;

        $this->actingAs($service->user, 'web')->post(route('dashboard.services.root-password', $service))->assertSessionHas('error');
        $this->assertSame(self::PASSWORD, (string) $service->server->fresh()->ssh_credential);
    }

    public function test_root_password_reset_is_not_offered_for_ssh_key_servers(): void
    {
        $service = $this->delivered();
        $service->server->update(['ssh_credential' => "-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n-----END OPENSSH PRIVATE KEY-----"]);

        $this->actingAs($service->user, 'web')->get(route('dashboard.services.show', $service))->assertOk()->assertDontSee('Generate a strong password for me');
        $this->post(route('dashboard.services.root-password', $service))->assertNotFound();
    }

    public function test_customer_reinstall_needs_typed_confirmation_then_wipes_and_reinstalls(): void
    {
        Queue::fake();
        $service = $this->delivered();
        $wipe = RecipeRegistry::script('n8n/wipe');

        $this->actingAs($service->user, 'web')->post(route('dashboard.services.reinstall', $service), ['confirm' => 'reinstall'])->assertSessionHasErrors('confirm');
        $this->assertNotContains($wipe, $this->t->commands);
        $this->assertSame('active', $service->fresh()->status);

        $this->post(route('dashboard.services.reinstall', $service), ['confirm' => 'REINSTALL'])
            ->assertRedirect(route('dashboard.services.show', $service))->assertSessionHas('success');
        $this->assertContains($wipe, $this->t->commands);
        $this->assertSame('pending_provisioning', $service->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'service.reinstall_started']);

        // Another customer can never reinstall it.
        $this->actingAs($this->customer(), 'web')->post(route('dashboard.services.reinstall', $service), ['confirm' => 'REINSTALL'])->assertForbidden();
    }

    public function test_parser_ignores_unknown_keys_and_bad_values(): void
    {
        $data = ServerStats::parse("cpu_permille=abc\nmem_total_kb=-5\nhostname=bad host;rm\nos=<b>Ubuntu</b>\nfoo=1");

        $this->assertNull($data['cpu']['percent']);
        $this->assertNull($data['mem']['total']);
        $this->assertNull($data['hostname']);
        $this->assertSame('Ubuntu', $data['os']);
    }
}
