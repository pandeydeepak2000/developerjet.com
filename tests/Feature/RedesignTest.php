<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Guards for the 2026-11 frontend redesign: structure, honesty rules and database-driven content. */
class RedesignTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public static function productPages(): array
    {
        return [
            ['/hermes-cli', 'hermes-cli'], ['/hermes-workspace', 'hermes-workspace'], ['/n8n', 'n8n'],
            ['/openclaw', 'openclaw'], ['/ai-complete-bundle', 'ai-complete-bundle'],
            ['/claude-code', 'claude-code'], ['/docker-vps', 'docker-vps'],
        ];
    }

    public function test_header_has_single_products_mega_menu_with_all_groups_and_links(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="mega-products"'));
        foreach (config('navigation.products') as $group => $def) {
            $this->assertStringContainsString('>'.$group.'</p>', $html);
            foreach ($def['links'] as $link) {
                $this->assertStringContainsString('<strong>'.e($link[0]).'</strong>', $html);
            }
        }
        foreach (['>Pricing<', '>Documentation<', '>Support<', '>Login<', 'Get Started', 'aria-label="Cart, 0 items"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringNotContainsString('announcement-bar', $html);
        $this->assertStringNotContainsString('Most popular', $html);
    }

    #[DataProvider('productPages')]
    public function test_every_product_page_follows_the_nine_section_structure(string $path, string $key): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertSame(9, substr_count($html, '<section'), $path);
        $positions = array_map(fn ($id) => strpos($html, $id), ['class="dj-phero"', 'id="pricing"', 'id="how"', 'id="features"', 'id="specs"', 'id="included"', 'id="compare"', 'id="faq"', 'class="dj-cta']);
        $this->assertNotContains(false, $positions, $path);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, "Sections out of order on {$path}");

        // Product-specific illustration with an accessible title, and the comparison table.
        $this->assertStringContainsString('aria-labelledby="ill-', $html);
        $this->assertStringContainsString(e(config("product_pages.$key.illustration_alt")), $html);
        $this->assertStringContainsString('class="dj-table"', $html);
        $this->assertStringContainsString('This page', $html);
        $this->assertStringNotContainsString('Most popular', $html);
    }

    public function test_illustrations_are_unique_per_product(): void
    {
        $names = array_column(config('product_pages'), 'illustration');
        $this->assertSame(count($names), count(array_unique($names)));
        foreach ($names as $name) {
            $this->assertFileExists(resource_path("views/illustrations/{$name}.blade.php"));
        }
    }

    public function test_pending_plans_show_pricing_to_be_confirmed_and_no_numeric_price(): void
    {
        foreach (['/claude-code', '/docker-vps', '/openclaw', '/ai-complete-bundle'] as $path) {
            $html = $this->get($path)->assertOk()->assertSee('Pricing to be confirmed')->assertSee('Contact Sales')->getContent();
            $this->assertStringNotContainsString('₹', $html, $path);
            $this->assertStringNotContainsString('class="plan-add-to-cart"', $html, $path);
        }
    }

    public function test_plan_cards_render_admin_managed_names_resources_and_features(): void
    {
        Plan::where('slug', 'docker-vps-a')->update(['name' => 'Admin Named Tier', 'features' => ['Feature from admin']]);

        $this->get('/docker-vps')->assertOk()
            ->assertSee('Admin Named Tier')->assertSee('Feature from admin')
            ->assertSee('Full root (SSH)')->assertSee('To be confirmed');

        // Resources come from the resource profile and are not repeated as features.
        $html = $this->get('/hermes-cli')->assertOk()->assertSee('80 GB NVMe')->assertSee('4 TB / month')->getContent();
        $this->assertStringNotContainsString('80 GB NVMe storage', $html);
    }

    public function test_plan_cards_carry_no_repeated_disclaimers_or_unsupported_badges(): void
    {
        $html = $this->get('/hermes-cli')->assertOk()->getContent();

        // One pricing explanation for the group, a short tag on each card.
        $this->assertSame(1, substr_count($html, 'class="dj-price-note'));
        $this->assertSame(3, substr_count($html, 'class="dj-plan__tag"'));
        $this->assertStringNotContainsString('Indicative price — confirmed before you order', $html);
        $this->assertStringNotContainsString('Recommended', $html);
        $this->assertStringNotContainsString('Most popular', $html);
    }

    public function test_plan_grids_never_exceed_three_columns(): void
    {
        foreach (['/', '/plans', '/hermes-cli', '/ai-all-access'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/dj-plan-grid--([4-9]|\d{2,})/', $html, $path);
        }
    }

    public function test_home_shows_product_family_cards_and_no_plan_cards(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(count(array_filter(config('product_families'), fn ($f) => $f['home'])), substr_count($html, 'class="dj-family"'));
        $this->assertStringNotContainsString('class="dj-plan"', $html);
        // No illustrative/pending price ever appears on a family card.
        $this->assertStringNotContainsString('₹', $html);
    }

    public function test_family_card_shows_a_starting_price_only_when_approved(): void
    {
        $this->get('/')->assertDontSee('From ₹');

        $plan = Plan::where('slug', 'docker-vps-a')->first();
        $plan->prices()->updateOrCreate(['billing_interval' => 'monthly'], ['amount_minor' => 123400, 'currency' => 'INR', 'is_active' => true]);
        $plan->update(['price_status' => 'approved']);

        $this->get('/')->assertSee('From <strong>₹1,234', false);
    }

    public function test_home_shows_the_five_step_customer_workflow_without_promising_instant_delivery(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSeeInOrder(['Choose a Plan', 'Configure Your Service', 'Complete Checkout', 'We Prepare Your Environment', 'Access Your Service'])
            ->assertSee('so delivery is not instant', false)
            ->assertSee('Explore Hosting')->assertSee('View All Plans')
            ->getContent();

        // Internal supplier purchasing/assignment belongs in admin docs, not public marketing.
        foreach (['approved supplier', 'Team assigns VPS', 'purchased and assigned'] as $internal) {
            $this->assertStringNotContainsString($internal, $html, $internal);
        }
    }

    public function test_draft_slots_use_proposed_placeholder_names(): void
    {
        $this->assertSame(['Starter', 'Growth', 'Business'], Plan::whereIn('slug', ['claude-code-a', 'claude-code-b', 'claude-code-c'])->orderBy('slug')->pluck('name')->all());
        $this->assertSame('pending', Plan::where('slug', 'docker-vps-a')->value('price_status'));
    }
}
