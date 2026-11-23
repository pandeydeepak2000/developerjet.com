<?php

namespace Tests\Feature;

use Database\Seeders\BusinessAutomationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class NavigationStructureTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        $this->seed(BusinessAutomationSeeder::class);
    }

    /** @return list<string> hrefs inside the given HTML fragment */
    private function hrefs(string $html): array
    {
        preg_match_all('/href="([^"]+)"/', $html, $m);

        return $m[1];
    }

    private function headerNav(string $html): string
    {
        return Str::between($html, '<nav class="dj-nav"', '</nav>');
    }

    private function drawer(string $html): string
    {
        return Str::between($html, 'id="primary-menu"', '<main');
    }

    public function test_menu_has_three_product_groups_and_no_cloud_vps_or_managed_vs_unmanaged_entry(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $nav = $this->headerNav($html);

        foreach (['AI Products', 'Developer Hosting', 'Digital Services'] as $group) {
            $this->assertStringContainsString($group, $nav);
        }
        foreach (['AI All-Access', 'Hermes CLI', 'Hermes Workspace', 'n8n Automation Hosting', 'OpenClaw', 'AI Complete Bundle',
            'Claude Code Hosting', 'Docker VPS Hosting', 'AI Application Hosting', 'Compare Developer Products',
            'WordPress Development', 'Laravel Development', 'Web Development', 'SEO Services', 'Website Maintenance', 'Business Automation',
            'Pricing', 'Documentation', 'Support'] as $label) {
            $this->assertStringContainsString($label, $nav, $label);
        }
        $this->assertStringContainsString('Cart', $html);
        $this->assertStringContainsString('>Login<', $html);
        $this->assertStringContainsString('Get Started', $html);

        $this->assertStringNotContainsString('Cloud VPS', $html);
        $this->assertStringNotContainsString('Managed vs Unmanaged', $nav.$this->drawer($html));
        $this->assertArrayNotHasKey('Cloud VPS', config('navigation.products'));
        $this->assertCount(3, config('navigation.products'));
    }

    public function test_header_menu_and_mobile_drawer_links_are_unique(): void
    {
        $html = $this->get('/')->getContent();

        $nav = $this->hrefs($this->headerNav($html));
        $this->assertSame(array_values(array_unique($nav)), $nav, 'Duplicate destination in the header menu');

        $drawer = $this->hrefs($this->drawer($html));
        $this->assertSame(array_values(array_unique($drawer)), $drawer, 'Duplicate destination in the mobile drawer');

        $config = collect(config('navigation.products'))->flatMap(fn ($g) => $g['links'])->map(fn ($l) => $l[1].'|'.($l[2] ?? '').'|'.($l[5] ?? ''));
        $this->assertSame($config->count(), $config->unique()->count());
    }

    public function test_docker_vps_hosting_is_the_single_canonical_docker_link(): void
    {
        $html = $this->get('/')->getContent();
        $docker = route('product.show', 'docker-vps');

        $this->assertSame(1, substr_count($this->headerNav($html), 'href="'.$docker.'"'));
        $this->assertSame(1, substr_count($this->drawer($html), 'href="'.$docker.'"'));
        $this->assertSame(1, collect(config('navigation.products'))->flatMap(fn ($g) => $g['links'])->filter(fn ($l) => str_contains(strtolower($l[0]), 'docker'))->count());
    }

    public function test_every_menu_destination_resolves_and_existing_product_routes_are_preserved(): void
    {
        $html = $this->get('/')->getContent();
        foreach (array_unique($this->hrefs($this->headerNav($html))) as $href) {
            $path = parse_url($href, PHP_URL_PATH) ?: '/';
            $this->get($path)->assertOk();
        }

        // Routes that are no longer in the menu still work (bookmarks, docs, orders and customer services link to them).
        foreach (['/docker-vps', '/claude-code', '/openclaw', '/ai-complete-bundle', '/n8n', '/hermes', '/hermes-cli', '/hermes-workspace',
            '/compare', '/compare/managed-vs-unmanaged-vps', '/digital-services', '/plans', '/ai-all-access', '/business-automation', '/ai-applications'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_mobile_drawer_keeps_nested_groups_close_button_and_overlay_outside_header(): void
    {
        $html = $this->get('/')->getContent();
        $drawer = $this->drawer($html);

        $this->assertSame(3, substr_count($drawer, '<details class="dj-drawer__group"'));
        $this->assertStringContainsString('role="dialog"', Str::between($html, '<div class="dj-drawer"', '>'));
        $this->assertStringContainsString('data-menu-backdrop', $html);
        $this->assertStringContainsString('aria-controls="primary-menu"', $html);
        $this->assertStringContainsString('Business Automation', $drawer);
        $this->assertStringNotContainsString('id="primary-menu"', Str::between($html, '<header', '</header>'));
    }

    public function test_product_pages_explain_included_access_without_promising_root_on_managed_products(): void
    {
        $this->get('/docker-vps')->assertSee('Included access:')->assertSee('root (administrator) access')->assertSee('DEVELOPER HOSTING · UNMANAGED');
        foreach (['/claude-code', '/hermes-cli', '/hermes-workspace', '/n8n', '/openclaw', '/ai-complete-bundle'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $note = Str::between($html, 'data-included-access>', '</p>');
            $this->assertStringContainsString('Managed service', $note, $path);
            $this->assertMatchesRegularExpression('/root access is not included|no SSH or root access/', $note, $path);
        }
    }

    public function test_business_automation_is_listed_under_digital_services(): void
    {
        $this->get('/digital-services')->assertOk()->assertSee(route('business-automation'));
        $footer = Str::between($this->get('/')->getContent(), '<footer', '</footer>');
        $this->assertStringContainsString(route('business-automation'), $footer);
    }
}
