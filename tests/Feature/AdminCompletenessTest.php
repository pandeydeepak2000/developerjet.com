<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\BillingOpsController;
use App\Models\Department;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestone 11: every admin page and generic resource form works for a Super Admin; limited roles stay limited. */
class AdminCompletenessTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
    }

    public function test_every_parameterless_admin_get_route_renders_for_super_admin(): void
    {
        $this->actingAs($this->staff());
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! str_starts_with($route->uri(), 'admin') || str_contains($route->uri(), '{')) {
                continue;
            }
            if (str_contains($route->uri(), 'staff-portal/check')) {
                continue; // JSON helper, covered in StaffPortalTest
            }
            $this->get('/'.$route->uri())->assertOk();
            $checked++;
        }

        $this->assertGreaterThan(12, $checked);
    }

    public function test_every_tab_and_generic_resource_form_renders(): void
    {
        app(TicketService::class)->open($this->customer(), Department::firstOrFail(), 'Sweep', 'Body', 'normal', null);
        $super = $this->staff();
        $this->actingAs($super);

        foreach (array_keys(BillingController::TABS) as $tab) {
            $this->get('/admin/billing?tab='.$tab)->assertOk();
        }
        foreach (array_keys(BillingOpsController::TABS) as $tab) {
            $this->get('/admin/billing-ops?tab='.$tab)->assertOk();
        }
        foreach (['status=all', 'assignee=unassigned', 'escalated=1', 'breached=1', 'priority=urgent'] as $q) {
            $this->get('/admin/tickets?'.$q)->assertOk();
        }
        $this->get('/admin/staff/'.$super->id)->assertOk();

        foreach (config('admin_resources') as $key => $cfg) {
            $this->get('/admin/r/'.$key)->assertOk();
            if (in_array('create', $cfg['actions'] ?? [], true)) {
                $this->get('/admin/r/'.$key.'/create')->assertOk();
            }
            if (in_array('edit', $cfg['actions'] ?? [], true) && ($row = $cfg['model']::query()->first())) {
                $this->get('/admin/r/'.$key.'/'.$row->getKey().'/edit')->assertOk();
            }
        }
    }

    public function test_limited_roles_get_403_not_500_on_every_admin_page(): void
    {
        $this->actingAs($this->staff('sales-staff'));

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! str_starts_with($route->uri(), 'admin') || str_contains($route->uri(), '{')) {
                continue;
            }
            $status = $this->get('/'.$route->uri())->getStatusCode();
            $this->assertContains($status, [200, 302, 403], $route->uri().' returned '.$status);
        }
    }
}
