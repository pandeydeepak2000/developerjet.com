<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\AutomationRequest;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Project;
use App\Models\Provider;
use App\Models\ProviderOffer;
use App\Models\Role;
use App\Models\User;
use App\Services\AutomationLeadService;
use Database\Seeders\BusinessAutomationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class LeadWorkflowTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        $this->seed(BusinessAutomationSeeder::class);
        Mail::fake();
    }

    private function lead(?User $customer = null, array $attrs = []): AutomationRequest
    {
        [$lead] = app(AutomationLeadService::class)->submit(array_merge([
            'name' => 'Lead Person', 'email' => $customer?->email ?? 'lead'.Str::random(5).'@example.test', 'phone' => '+919876543210',
            'business_type' => 'Retail', 'requirements' => 'Automate order confirmations and stock alerts for our shop.',
        ], $attrs), $customer, Str::random(40), '127.0.0.1');

        return $lead;
    }

    public function test_full_quotation_lifecycle_with_audit_and_customer_updates_but_no_payment(): void
    {
        $customer = $this->customer();
        $lead = $this->lead($customer);
        $admin = $this->staff('admin');
        $sales = $this->staff('sales-staff');

        // Admin assigns a sales owner → status "assigned"
        $this->actingAs($admin)->post(route('admin.leads.assign', $lead), ['assigned_to' => $sales->id])->assertSessionHas('success');
        $this->assertSame('assigned', $lead->fresh()->status);

        // Sales works the lead
        $this->actingAs($sales)->post(route('admin.leads.note', $lead), ['type' => 'note', 'body' => 'PRIVATE: budget looks tight'])->assertSessionHas('success');
        $this->post(route('admin.leads.note', $lead), ['type' => 'contact', 'channel' => 'phone', 'body' => 'Called, discussed scope'])->assertSessionHas('success');
        $this->post(route('admin.leads.follow-up', $lead), ['owner_id' => $sales->id, 'follow_up_at' => now()->addDay()->format('Y-m-d H:i')])->assertSessionHas('success');
        $this->post(route('admin.leads.request-info', $lead), ['message' => 'Which e-commerce platform do you use?'])->assertSessionHas('success');
        $this->assertSame('requirements_discussion', $lead->fresh()->status);

        // Cannot send a quotation before one is prepared; cannot jump to converted
        $this->post(route('admin.leads.status', $lead), ['status' => 'quotation_sent'])->assertSessionHas('error');
        $this->post(route('admin.leads.status', $lead), ['status' => 'converted'])->assertSessionHas('error');

        $this->post(route('admin.leads.quote', $lead), ['amount' => '45000', 'summary' => 'Order confirmation + stock alert workflows in n8n.'])->assertSessionHas('success');
        $this->assertSame(4500000, $lead->fresh()->quote_minor);
        $this->post(route('admin.leads.status', $lead), ['status' => 'quotation_sent'])->assertSessionHas('success');

        // Customer sees the quote (not internal notes) and accepts — nothing charged
        $this->actingAs($customer, 'web')->get(route('dashboard.automation.show', $lead))->assertOk()
            ->assertSee('₹45,000', false)->assertSee('Which e-commerce platform')->assertDontSee('PRIVATE')->assertDontSee('Called, discussed');
        $this->post(route('dashboard.automation.decide', $lead), ['decision' => 'accept'])->assertSessionHas('success');
        $this->assertSame('accepted', $lead->fresh()->status);
        $this->assertSame('customer_dashboard', $lead->fresh()->decision_source);
        $this->post(route('dashboard.automation.decide', $lead), ['decision' => 'decline'])->assertSessionHas('error');

        // Convert → linked to the existing project workflow; still no order / invoice / payment
        $this->actingAs($sales)->post(route('admin.leads.convert', $lead))->assertSessionHas('success');
        $lead->refresh();
        $this->assertSame('converted', $lead->status);
        $project = Project::findOrFail($lead->project_id);
        $this->assertSame($customer->id, $project->user_id);
        $this->assertSame('automation', $project->kind);
        $this->assertSame('accepted', $lead->serviceRequest->status);
        $this->actingAs($sales)->post(route('admin.leads.convert', $lead)); // idempotent
        $this->assertSame(1, Project::count());
        $this->assertSame(0, Order::count() + Invoice::count() + PaymentAttempt::count());

        foreach (['lead.assigned', 'lead.status_changed', 'lead.quote_prepared', 'lead.customer_accepted', 'lead.converted', 'lead.note', 'lead.contact', 'lead.follow_up'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->template === 'automation_update' && $m->vars['status'] === 'Quotation sent');
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->template === 'automation_info_request');
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->template === 'automation_update' && $m->vars['status'] === 'Converted to project');
        Mail::assertNotSent(TemplatedMail::class, fn ($m) => str_contains(implode(' ', array_map('strval', $m->vars)), 'PRIVATE'));
    }

    public function test_guest_lead_needs_a_verified_matching_account_before_conversion(): void
    {
        $lead = $this->lead(null, ['email' => 'guest@example.test']);
        $admin = $this->staff('admin');
        $this->actingAs($admin);
        $this->post(route('admin.leads.assign', $lead), ['assigned_to' => $admin->id]);
        $this->post(route('admin.leads.quote', $lead), ['summary' => 'Scope only.']);
        $this->post(route('admin.leads.status', $lead), ['status' => 'quotation_sent']);
        $this->post(route('admin.leads.status', $lead), ['status' => 'accepted', 'note' => 'Customer confirmed by email'])->assertSessionHas('success');
        $this->assertSame('recorded_by_staff', $lead->fresh()->decision_source);

        $this->post(route('admin.leads.convert', $lead))->assertSessionHas('error');
        $this->post(route('admin.leads.link-account', $lead))->assertSessionHas('error');

        $unverified = $this->customer(['email' => 'guest@example.test', 'email_verified_at' => null]);
        $this->post(route('admin.leads.link-account', $lead))->assertSessionHas('error');
        $unverified->forceFill(['email_verified_at' => now()])->save();
        $this->post(route('admin.leads.link-account', $lead))->assertSessionHas('success');
        $this->post(route('admin.leads.convert', $lead))->assertSessionHas('success');
        $this->assertSame($unverified->id, Project::sole()->user_id);
    }

    public function test_sales_staff_see_only_their_own_leads_unless_they_can_assign(): void
    {
        $sales = $this->staff('sales-staff');
        $other = $this->staff('sales-staff');
        $mine = $this->lead(null, ['assigned_to' => $sales->id]);
        $theirs = $this->lead(null, ['assigned_to' => $other->id]);
        $unassigned = $this->lead();

        $this->actingAs($sales)->get(route('admin.leads.index'))->assertOk()->assertSee($mine->reference)->assertDontSee($theirs->reference)->assertDontSee($unassigned->reference);
        $this->get(route('admin.leads.show', $theirs))->assertForbidden();
        $this->post(route('admin.leads.note', $theirs), ['type' => 'note', 'body' => 'x'])->assertForbidden();
        $this->post(route('admin.leads.assign', $mine), ['assigned_to' => $sales->id])->assertForbidden(); // no leads.assign
        $this->post(route('admin.leads.follow-up', $mine), ['owner_id' => $other->id])->assertForbidden();

        $this->actingAs($this->staff('admin'))->get(route('admin.leads.index'))->assertSee($theirs->reference)->assertSee($unassigned->reference);
    }

    public function test_support_billing_and_technical_roles_cannot_work_leads(): void
    {
        $lead = $this->lead();
        foreach (['billing-staff', 'seo-manager'] as $role) {
            $this->actingAs($this->staff($role))->get(route('admin.leads.index'))->assertForbidden();
            $this->get(route('admin.leads.show', $lead))->assertForbidden();
        }

        $tech = $this->staff('technical-support');
        $this->actingAs($this->staff('admin'))->post(route('admin.leads.assign', $lead), ['technical_owner_id' => $tech->id])->assertSessionHas('success');
        $this->actingAs($tech)->get(route('admin.leads.show', $lead))->assertOk(); // assigned technical owner may read
        $this->post(route('admin.leads.note', $lead), ['type' => 'note', 'body' => 'x'])->assertForbidden(); // but not edit
        $this->post(route('admin.leads.status', $lead), ['status' => 'closed'])->assertForbidden();
    }

    public function test_customers_only_see_their_own_requests_and_guests_cannot_reach_dashboard(): void
    {
        $alice = $this->customer();
        $bob = $this->customer();
        $aliceLead = $this->lead($alice);

        $this->get(route('dashboard.automation.show', $aliceLead))->assertRedirect(route('login'));
        $this->actingAs($bob, 'web')->get(route('dashboard.automation.show', $aliceLead))->assertNotFound();
        $this->post(route('dashboard.automation.decide', $aliceLead), ['decision' => 'accept'])->assertNotFound();
        $this->get(route('dashboard.automation'))->assertOk()->assertDontSee($aliceLead->reference);
        $this->get(route('admin.leads.index'))->assertNotFound(); // customers never reach the staff area
    }

    public function test_leads_export_is_scoped_and_excludes_phone_numbers(): void
    {
        $sales = $this->staff('sales-staff');
        $this->lead();
        $this->actingAs($sales)->get(route('admin.exports.download', 'leads'))->assertForbidden();

        $response = $this->actingAs($this->staff('admin'))->get(route('admin.exports.download', 'leads'))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Reference', $csv);
        $this->assertStringNotContainsString('9876543210', $csv);
    }

    public function test_technical_support_cannot_see_supplier_costs_or_revenue(): void
    {
        $offer = ProviderOffer::query()->first() ?? ProviderOffer::create([
            'provider_id' => Provider::query()->value('id'), 'label' => 'Test offer', 'billing_period' => 'monthly',
            'purchase_cost_minor' => 123456, 'status' => 'draft',
        ]);
        $offer->update(['purchase_cost_minor' => 123456]);

        $tech = $this->staff('technical-support');
        $this->actingAs($tech)->get(route('admin.resource.index', 'provider_offers'))->assertOk()->assertDontSee('Our cost')->assertDontSee('1,234.56');
        $this->get(route('admin.resource.edit', ['provider_offers', $offer->id]))->assertForbidden(); // servers.edit not granted
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Verified revenue');

        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.resource.index', 'provider_offers'))->assertSee('Our cost');
        $this->get(route('admin.dashboard'))->assertSee('Verified revenue'); // admin template holds payments.view
        $this->actingAs($this->staff('sales-staff'))->get(route('admin.dashboard'))->assertDontSee('Verified revenue');
    }

    public function test_staff_without_cost_permission_cannot_overwrite_costs_through_the_form(): void
    {
        $role = Role::create(['name' => 'Infra editor', 'slug' => 'infra-editor', 'is_system' => false, 'is_super' => false]);
        foreach (['servers.view', 'servers.edit', 'dashboard.view'] as $p) {
            $role->permissions()->create(['permission' => $p]);
        }
        $editor = $this->staff('infra-editor');
        $offer = ProviderOffer::create(['provider_id' => Provider::query()->value('id'), 'label' => 'Offer', 'billing_period' => 'monthly', 'purchase_cost_minor' => 50000, 'status' => 'draft']);

        $this->actingAs($editor)->get(route('admin.resource.edit', ['provider_offers', $offer->id]))->assertOk()->assertDontSee('name="purchase_cost_minor"', false);
        $this->put(route('admin.resource.update', ['provider_offers', $offer->id]), [
            'provider_id' => $offer->provider_id, 'label' => 'Renamed', 'billing_period' => 'monthly', 'status' => 'draft', 'purchase_cost_minor' => '1',
        ]);
        $this->assertSame('Renamed', $offer->fresh()->label);
        $this->assertSame(50000, $offer->fresh()->purchase_cost_minor);
    }

    public function test_sales_dashboard_panel_shows_due_follow_ups(): void
    {
        $sales = $this->staff('sales-staff');
        $lead = $this->lead(null, ['assigned_to' => $sales->id, 'status' => 'assigned', 'follow_up_owner_id' => $sales->id, 'follow_up_at' => now()->subHour()]);

        $this->actingAs($sales)->get(route('admin.dashboard'))->assertOk()->assertSee('my Business Automation leads')->assertSee($lead->reference);
        $this->get(route('admin.leads.index', ['due' => 1]))->assertSee($lead->reference);
    }
}
