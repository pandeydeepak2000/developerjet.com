<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class AdminDashboardSweepTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_super_admin_can_open_every_admin_page_with_responsive_shell(): void
    {
        $this->actingAs($this->staff('super-admin'));

        $pages = ['/admin', '/admin/orders', '/admin/plans', '/admin/plans/create', '/admin/wallet', '/admin/tickets',
            '/admin/staff', '/admin/settings', '/admin/projects', '/admin/seo'];
        foreach (config('admin_resources') as $resource => $cfg) {
            $pages[] = "/admin/r/{$resource}";
            if (in_array('create', $cfg['actions'] ?? [], true)) {
                $pages[] = "/admin/r/{$resource}/create";
            }
        }

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/admin')->assertSee('width=device-width', false);
    }

    public function test_admin_pages_are_never_cacheable_and_carry_security_headers(): void
    {
        $this->get('/staff/developerjet/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $this->assertStringContainsString('no-store', $this->get('/staff/developerjet/login')->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'self'", $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_limited_roles_cannot_reach_other_modules(): void
    {
        $this->actingAs($this->staff('billing-staff'));

        $this->get('/admin/staff')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
    }
}
