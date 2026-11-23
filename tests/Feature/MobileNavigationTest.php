<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * Structural guard for the mobile drawer. The real open/close behaviour is verified with browser clicks
 * (see docs/02_PROJECT_STATUS.md); these assertions stop the root cause from coming back: a
 * position:fixed drawer nested inside the header (which uses backdrop-filter) collapses to 0px height.
 */
class MobileNavigationTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_drawer_is_rendered_outside_the_header(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $headerEnd = strpos($html, '</header>');
        $drawer = strpos($html, 'id="primary-menu"');
        $this->assertNotFalse($headerEnd);
        $this->assertNotFalse($drawer);
        $this->assertGreaterThan($headerEnd, $drawer, 'The drawer must not be nested inside <header>.');
        $this->assertStringContainsString('data-menu-backdrop hidden', $html);
    }

    public function test_toggle_and_drawer_have_accessible_wiring(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button class="dj-burger"[^>]*aria-expanded="false"[^>]*aria-controls="primary-menu"[^>]*aria-label="Open menu"/', $html);
        $this->assertMatchesRegularExpression('/id="primary-menu"[^>]*role="dialog"[^>]*aria-modal="true"[^>]*hidden/', $html);
    }

    public function test_drawer_lists_every_product_group_and_account_actions(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $drawer = substr($html, strpos($html, 'id="primary-menu"'));

        foreach (array_keys(config('navigation.products')) as $group) {
            $this->assertStringContainsString('<summary>'.e($group), $drawer, $group);
            foreach (config('navigation.products')[$group]['links'] as $link) {
                $this->assertStringContainsString(e($link[0]), $drawer, $link[0]);
            }
        }
        foreach (['Pricing', 'Documentation', 'Support', 'Cart', 'Customer Login', 'Get Started'] as $label) {
            $this->assertStringContainsString($label, $drawer, $label);
        }
    }

    public function test_drawer_renders_on_every_public_page(): void
    {
        foreach (['/', '/plans', '/docs', '/cart', '/login', '/register', '/ai-all-access', '/digital-services', '/compare', '/contact'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('id="primary-menu"', $html, $path);
            $this->assertGreaterThan(strpos($html, '</header>'), strpos($html, 'id="primary-menu"'), $path);
        }
    }
}
