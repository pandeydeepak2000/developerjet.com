<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class ProductPagesTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_navigation_has_the_required_groups(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['AI Products', 'Developer Hosting', 'Digital Services', 'Pricing', 'Documentation', 'Support', 'Customer Login'] as $label) {
            $this->assertStringContainsString($label, $html, $label);
        }
        $this->assertStringContainsString('aria-controls="primary-menu"', $html);
    }

    public static function products(): array
    {
        return [['claude-code'], ['docker-vps'], ['openclaw'], ['ai-complete-bundle']];
    }

    /** @dataProvider products */
    #[DataProvider('products')]
    public function test_product_pages_answer_the_required_questions_without_inventing_numbers(string $slug): void
    {
        $response = $this->get('/'.$slug)->assertOk();
        foreach (['What is it?', 'Who it is for', 'What you can do', 'Benefits', 'What is included', 'What is NOT included', 'Technical knowledge',
            'AI API subscription', 'Resources', 'How deployment works', 'How you access it', 'Support limits', 'Renewals', 'Configuration preview', 'Frequently asked questions'] as $heading) {
            $response->assertSee($heading);
        }
        $response->assertSee('not instant')->assertSee('Pricing to be confirmed')->assertSee('To be confirmed')->assertSee('Not open for orders yet');

        $html = $response->getContent();
        $this->assertStringNotContainsString('₹', $html);               // no invented prices
        $this->assertDoesNotMatchRegularExpression('/\d+(\.\d+)?\s?%\s?uptime|99\.\d/i', $html); // no uptime promises
        $this->assertStringNotContainsStringIgnoringCase('testimonial', $html);
        $this->assertSame(0, substr_count($html, 'data-plan-card'));
    }

    public function test_claude_code_page_states_customer_supplies_credentials_and_docker_is_unmanaged(): void
    {
        $this->get('/claude-code')->assertSee('your own eligible Claude subscription', false)->assertSee('does not resell', false)->assertSee('Self-hosted · full root access');
        $this->get('/docker-vps')->assertSee('Self-hosted · full root access')->assertSee('SSH/root')->assertSee('Not provided');
    }

    public function test_approved_price_appears_and_becomes_orderable_only_after_approval(): void
    {
        $this->approve('docker-vps-a', 1234);
        $this->get('/docker-vps')->assertSee('₹1,234')->assertSee('Add to cart');
    }

    public function test_all_comparison_pages_render(): void
    {
        $this->get('/compare')->assertOk();
        foreach (array_keys(config('comparisons')) as $slug) {
            $this->get('/compare/'.$slug)->assertOk()->assertSee('How to choose');
        }
        $this->get('/compare/unknown')->assertNotFound();
        foreach (['Hermes CLI vs Hermes Workspace', 'Hermes vs OpenClaw', 'n8n vs AI agent hosting', 'Claude Code Hosting vs Hermes CLI', 'Docker VPS vs ready-made AI apps', 'What self-hosted means: our part vs your part'] as $title) {
            $this->get('/compare')->assertSee($title);
        }
    }

    public function test_digital_services_page_lists_all_services_without_prices(): void
    {
        $r = $this->get('/digital-services')->assertOk();
        foreach (['WordPress Development', 'Laravel Development', 'Web Development', 'SEO Services', 'Custom AI Application Development', 'Website Maintenance', 'Website and Application Deployment'] as $s) {
            $r->assertSee($s);
        }
        $this->assertStringNotContainsString('₹', $r->getContent());
    }

    public function test_plans_page_lists_the_new_families(): void
    {
        $this->get('/plans')->assertOk()->assertSee('Claude Code Hosting')->assertSee('Docker VPS Hosting');
    }

    public function test_checkout_clearly_shows_managed_vs_unmanaged_and_manual_delivery(): void
    {
        $this->approve('docker-vps-a', 500);
        $user = $this->customer();
        $this->actingAs($user)->post('/cart', ['plan' => 'docker-vps-a', 'billing_interval' => 'monthly']);
        $this->get('/checkout')->assertOk()->assertSee('Self-hosted VPS with full root access')->assertSee('SSH/root access')->assertSee('not instant');

        $this->approve('hermes-cli-launch', 500);
        $this->post('/cart', ['plan' => 'hermes-cli-launch', 'billing_interval' => 'monthly']);
        $this->get('/checkout')->assertSee('Self-hosted VPS with full root access')->assertSee('backups are not included');
    }
}
