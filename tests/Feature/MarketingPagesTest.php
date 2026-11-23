<?php

namespace Tests\Feature;

use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_marketing_pages_are_available(): void
    {
        $sectionCounts = [
            '/' => 8,          // Redesign: hero, categories, featured plans, how it works, platform, comparison, FAQ, CTA
            '/hermes' => 3,
            '/hermes-cli' => 9,  // Product template: hero, pricing, how, features, capabilities, scope, comparison, FAQ, CTA
            '/hermes-workspace' => 9,
            '/n8n' => 9,
            '/login' => 4,
            '/register' => 4,
            '/forgot-password' => 4,
            '/about' => 5,
            '/contact' => 5,
            '/terms' => 5,
            '/privacy' => 5,
            '/refund' => 5,
        ];

        foreach ($sectionCounts as $path => $expectedSections) {
            $response = $this->get($path)->assertOk();

            $this->assertSame(
                $expectedSections,
                substr_count($response->getContent(), '<section'),
                "Unexpected content section count for {$path}.",
            );
        }
    }

    public function test_home_page_links_to_separate_product_pages_without_pricing_cards(): void
    {
        $response = $this->get('/');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('Hermes AI Agent')
            ->assertSee('Explore plans')
            ->assertSee('n8n Automation Hosting')
            ->assertSee(route('hermes'))
            ->assertSee(route('n8n'));

        $this->assertStringNotContainsString('data-plan-card', $content);
        $this->assertStringNotContainsString('₹499', $content);
    }

    public function test_hermes_overview_links_to_cli_and_workspace_products(): void
    {
        $response = $this->get('/hermes');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('Hermes CLI')
            ->assertSee('Hermes Workspace')
            ->assertSee('Three plans are available for each.')
            ->assertSee(route('hermes.cli'))
            ->assertSee(route('hermes.workspace'))
            ->assertSee('data-hermes-dropdown-toggle', false);

        $this->assertSame(0, substr_count($content, 'data-plan-card'));
    }

    public function test_hermes_cli_page_displays_three_vps_plans_and_connection_setup(): void
    {
        $response = $this->get('/hermes-cli');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('Hermes CLI')
            ->assertSee('2 vCPU')->assertSee('4 GB')
            ->assertSee('4 vCPU')->assertSee('8 GB')
            ->assertSee('8 vCPU')->assertSee('16 GB')
            ->assertSee('SSH or PuTTY')
            ->assertSee('Hermes CLI installed and configured')
            ->assertSee('data-billing-toggle="annual"', false)
            ->assertSee('data-annual="₹2,083"', false)
            ->assertSee('80 GB NVMe')
            ->assertSee('4 TB / month')
            ->assertSee('₹24,990 billed for 12 months')
            ->assertSee('Save ₹4,998 compared with monthly billing')
            ->assertSee('Hermes Workspace is installed and configured with Docker');

        $this->assertSame(3, substr_count($content, 'data-plan-card'));
        $this->assertSame(3, substr_count($content, 'class="plan-get-started"'));
        $this->assertStringNotContainsString('>+91 76888 55600<', $content);
        $this->assertStringNotContainsString('Call sales', $content);
        $this->assertStringNotContainsString('WhatsApp <span', $content);
    }

    public function test_hermes_workspace_page_displays_three_docker_vps_plans(): void
    {
        $response = $this->get('/hermes-workspace');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('Hermes Workspace')
            ->assertSee('install Hermes with Docker')
            ->assertSee('2 vCPU')->assertSee('4 vCPU')->assertSee('8 vCPU')
            ->assertSee('Hermes installed with Docker')
            ->assertSee('data-billing-toggle="annual"', false)
            ->assertSee('₹24,990 billed for 12 months')
            ->assertSee('Hermes CLI is installed directly on a VPS');

        $this->assertSame(3, substr_count($content, 'data-plan-card'));
        $this->assertSame(3, substr_count($content, 'class="plan-get-started"'));
    }

    public function test_n8n_page_displays_three_plans_with_sales_actions(): void
    {
        $response = $this->get('/n8n');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('n8n Automation Hosting')
            ->assertSee('Launch')
            ->assertSee('Grow')
            ->assertSee('Scale')
            ->assertSee('data-billing-toggle="annual"', false)
            ->assertSee('n8n installed on a managed VPS')
            ->assertSee('Up to 5 active workflows')
            ->assertSee('Up to 25 active workflows')
            ->assertSee('Up to 100 active workflows')
            ->assertSee('VPS size confirmed before purchase.')
            ->assertSee('₹24,990 billed for 12 months')
            ->assertSee('Is n8n installed on a VPS?')
            ->assertSee('n8n instance is installed on a VPS configured for your plan');

        $this->assertSame(3, substr_count($content, 'data-plan-card'));
        $this->assertSame(3, substr_count($content, 'class="plan-get-started"'));
        $this->assertStringNotContainsString('>+91 76888 55600<', $content);
        $this->assertStringNotContainsString('Call sales', $content);
        $this->assertStringNotContainsString('WhatsApp <span', $content);
    }

    public function test_account_pages_are_functional_forms(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee('name="password"', false)
            ->assertSee(route('password.request'));

        $this->get('/register')->assertOk()
            ->assertSee('action="'.route('register').'"', false)
            ->assertSee('name="password_confirmation"', false);

        $this->get('/forgot-password')->assertOk()
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('Send reset link');
    }

    public function test_refund_policy_contains_required_statement(): void
    {
        $this->get('/refund')
            ->assertOk()
            ->assertSee('Activated digital services and VPS plans are non-refundable.')
            ->assertSee('VPS has been provisioned and set up for your plan')
            ->assertDontSee('hosting');
    }
}
